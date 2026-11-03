/**
 * One upload transport for Storage and Gallery.
 *
 * File bytes are transferred in bounded chunks; successful completion always
 * enters the existing canonical media ingest owner on the server. Reloading the
 * page and selecting the same file resumes from its verified server offset.
 */
const endpoint = '/admin/media-upload';

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.content;
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return meta || (cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '');
}

function requestHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return {
        'Accept': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : { 'X-XSRF-TOKEN': csrfToken() }),
    };
}

async function jsonRequest(path, body) {
    const response = await fetch(endpoint + path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { ...requestHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    let payload = {};
    try { payload = await response.json(); } catch {}
    if (!response.ok) {
        const errors = payload.errors ? Object.values(payload.errors).flat() : [];
        const serverMessage = errors[0] || (payload.message && !/^server error$/i.test(payload.message) ? payload.message : '');
        const stage = path.endsWith('/finish') ? 'processing the uploaded file'
            : path.endsWith('/start') ? 'starting the upload' : 'handling the upload';
        throw new Error(serverMessage || 'The server failed while ' + stage + ' (HTTP ' + response.status
            + '). The file was not confirmed in Storage. Try again later or contact the site administrator.');
    }
    return payload;
}

function sendChunk(id, offset, blob, progress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', endpoint + '/' + encodeURIComponent(id) + '/chunk');
        xhr.timeout = 5 * 60 * 1000;
        xhr.withCredentials = true;
        for (const [name, value] of Object.entries(requestHeaders())) xhr.setRequestHeader(name, value);
        xhr.setRequestHeader('Content-Type', 'application/octet-stream');
        xhr.setRequestHeader('X-Upload-Offset', String(offset));
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) progress(event.loaded);
        };
        xhr.onerror = () => {
            const error = new Error('The connection was interrupted during file transfer. Select the same file to resume.');
            error.retryable = true;
            reject(error);
        };
        xhr.ontimeout = () => {
            const error = new Error('The file transfer timed out. Select the same file to resume.');
            error.retryable = true;
            reject(error);
        };
        xhr.onload = () => {
            let payload = {};
            try { payload = JSON.parse(xhr.responseText); } catch {}
            if (xhr.status < 200 || xhr.status >= 300) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [];
                const error = new Error(errors[0] || payload.message
                    || 'The server rejected a file chunk (HTTP ' + xhr.status + '). Select the same file to resume.');
                error.retryable = [502, 503, 504].includes(xhr.status);
                reject(error);
            } else {
                resolve(payload);
            }
        };
        xhr.send(blob);
    });
}

async function fileIdentity(file) {
    const parts = [
        new TextEncoder().encode(file.name + '\n' + file.size + '\n' + file.lastModified),
        new Uint8Array(await file.slice(0, 65536).arrayBuffer()),
        new Uint8Array(await file.slice(Math.max(0, file.size - 65536)).arrayBuffer()),
    ];
    const size = parts.reduce((sum, item) => sum + item.byteLength, 0);
    const combined = new Uint8Array(size);
    let cursor = 0;
    for (const part of parts) {
        combined.set(part, cursor);
        cursor += part.byteLength;
    }
    const digest = await crypto.subtle.digest('SHA-256', combined);
    return 'admin-media-upload:' + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

function readResume(key) {
    try { return localStorage.getItem(key); } catch { return null; }
}
function remember(key, id) {
    try { localStorage.setItem(key, id); } catch {}
}
function forget(key) {
    try { localStorage.removeItem(key); } catch {}
}

async function transfer(file, state, fileIndex, total) {
    const key = await fileIdentity(file);
    let resumeId = readResume(key);
    let session;

    try {
        session = await jsonRequest('/start', { filename: file.name, size: file.size, resume_id: resumeId });
    } catch (error) {
        // Expired or invalid token: create a new session; quota checks still apply.
        if (!resumeId || !/previous upload|not found|expired/i.test(String(error.message))) throw error;
        forget(key);
        resumeId = null;
        session = await jsonRequest('/start', { filename: file.name, size: file.size });
    }

    remember(key, session.id);
    let failures = 0;
    while (!session.complete && session.offset < file.size) {
        const offset = Number(session.offset);
        const next = Math.min(file.size, offset + Number(session.chunk_size));
        state.phase = 'uploading';
        state.progress = Math.round((fileIndex + offset / file.size) * 100 / total);

        try {
            session = await sendChunk(session.id, offset, file.slice(offset, next), (sent) => {
                state.progress = Math.min(100, Math.round((fileIndex + (offset + sent) / file.size) * 100 / total));
            });
            failures = 0;
        } catch (error) {
            if (!error?.retryable || ++failures > 3) throw error;
            state.result = 'Connection interrupted; resuming from the last saved chunk…';
            session = await jsonRequest('/start', {
                filename: file.name, size: file.size, resume_id: session.id,
            });
        }
    }

    state.phase = 'processing';
    state.result = '';
    if (!session.complete) {
        try {
            session = await jsonRequest('/' + session.id + '/finish', {});
        } catch (error) {
            // If the final acknowledgment was lost after saving, consult the
            // durable session. Never overwrite the actual processing failure
            // with a secondary session-status error.
            let recheck;
            try {
                recheck = await jsonRequest('/start', {
                    filename: file.name, size: file.size, resume_id: session.id,
                });
            } catch {
                throw error;
            }
            if (!recheck.complete) throw error;
            session = recheck;
        }
    }

    if (!session.complete || !session.asset_id) throw new Error('File was not confirmed in Storage.');
    return { ...session, resumeKey: key };
}

/**
 * State is the existing Alpine dropzone. The only per-consumer differences
 * are the canonical post-ingest Storage refresh or Gallery Artwork queue.
 */
export async function adminMediaUploader(state, input, wire, context) {
    if (state.phase === 'uploading' || state.phase === 'processing') return;
    const files = Array.from(input.files || []);
    if (files.length === 0) return;

    state.fileCount = files.length;
    state.completed = 0;
    state.progress = 0;
    state.result = '';
    state.phase = 'uploading';
    let added = 0;
    let duplicates = 0;
    let failed = 0;
    const errors = [];
    let notificationAttempted = false;

    try {
        if (context === 'gallery') await wire.beginDirectPrimaryMediaBatch();

        for (const file of files) {
            let savedToStorage = false;
            try {
                const result = await transfer(file, state, state.completed, files.length);
                savedToStorage = true;
                // Acknowledgment in the consumer comes before the local resume
                // token is cleared; a lost response can be recovered on retry.
                if (context === 'gallery') {
                    await wire.queueResumablePrimaryMediaAsset(result.asset_id);
                }
                forget(result.resumeKey);
                if (result.duplicate) duplicates++;
                else added++;
            } catch (error) {
                failed++;
                const reason = savedToStorage && context === 'gallery'
                    ? 'Saved to Storage, but could not be added to the Artwork selection: ' + (error?.message || 'Selection failed.')
                    : (error?.message || 'The upload failed. Select the same file to retry.');
                errors.push(file.name + ' — ' + reason);
            }
            state.completed++;
            state.progress = Math.round(state.completed * 100 / files.length);
        }

        state.phase = 'processing';
        if (context === 'gallery') {
            notificationAttempted = true;
            const result = await wire.finishDirectPrimaryMediaBatch(failed, duplicates, errors);
            state.result = result?.summary || 'The Gallery upload finished. Review your Artwork selection.';
        } else {
            let refreshWarning = '';
            if (added > 0) {
                try {
                    await wire.refreshAfterResumableMediaUpload();
                } catch {
                    // Upload acceptance precedes the library refresh.
                    // A stale view is not evidence that the upload failed.
                    refreshWarning = ' The Storage list did not refresh. Reload the page to see the saved files.';
                }
            }
            notificationAttempted = true;
            state.result = await wire.notifyDirectMediaBatch(added, duplicates, failed, errors.join('\n'));
            state.result += refreshWarning;
        }
    } catch (error) {
        if (notificationAttempted) {
            // A lost response is not evidence that the server did not notify
            // or save the uploaded originals. Avoid a duplicate failure notice.
            state.result = 'The final upload status could not be confirmed. Check Storage before retrying. '
                + (error?.message || '');
        } else {
            state.result = error?.message || 'Upload could not be completed. Select the same file to resume.';
            try {
                const details = errors.length ? errors.join('\n') : state.result;
                if (context === 'gallery') {
                    await wire.notifyDirectPrimaryTransferFailure(details);
                } else {
                    state.result = await wire.notifyDirectMediaBatch(added, duplicates, Math.max(1, failed), details);
                }
            } catch {
                // When the connection is down, the inline result remains visible.
            }
        }
    } finally {
        input.value = '';
        state.phase = 'result';
    }
}

/** Resumable field shared by Artwork create/edit, replacement and extra images. */
export async function adminMediaUploadField(state, input, wire, statePath, imagesOnly = false) {
    if (state.phase === 'uploading' || state.phase === 'processing') return;
    const file = input.files?.[0];
    if (!file) return;
    state.phase = 'uploading';
    state.progress = 0;
    state.result = '';
    try {
        // The transfer updates this Alpine state through the shared transport.
        if (imagesOnly && !file.type.startsWith('image/') && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
            throw new Error('This field accepts images only.');
        }
        const outcome = await transfer(file, state, 0, 1);
        try {
            await wire.set(statePath, outcome.asset_id);
        } catch {
            throw new Error('File saved to Storage, but could not be linked to this field. Select it from Storage or contact the site administrator.');
        }
        forget(outcome.resumeKey);
        state.result = file.name + ' — uploaded to Storage and ready to save with the Artwork';
    } catch (error) {
        state.result = file.name + ' — ' + (error?.message || 'Upload failed. Re-select the same file to resume.');
        try { await jsonRequest('/failure', { message: state.result.slice(0, 400) }); } catch {
            // The inline message remains accessible if the connection is down.
        }
    } finally {
        state.phase = 'result';
        input.value = '';
    }
}
