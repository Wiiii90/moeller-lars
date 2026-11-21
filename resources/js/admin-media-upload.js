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

// On a resumed transfer verify EVERY already acknowledged chunk against the
// selected local file. This is the canonical staging receipt, not a guess
// based on filename, timestamp or a small beginning/end sample.
async function resumedBytesMatch(file, session) {
    const offset = Number(session.offset);
    if (offset === 0) return !session.complete;

    const chunkSize = Number(session.chunk_size);
    const hashes = session.chunk_hashes;
    if (!Array.isArray(hashes) || chunkSize < 1
        || hashes.length !== Math.ceil(offset / chunkSize)) return false;

    for (let i = 0; i < hashes.length; i++) {
        const start = i * chunkSize;
        const end = Math.min(offset, start + chunkSize);
        if (end > file.size || !/^[a-f0-9]{64}$/.test(String(hashes[i]))) return false;
        const digest = await crypto.subtle.digest('SHA-256', await file.slice(start, end).arrayBuffer());
        const actual = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
        if (actual !== hashes[i]) return false;
    }

    return true;
}

async function verifyOrRestart(file, session, key, state) {
    if (!Number(session.offset) && !session.complete) return session;

    state.result = 'Checking previously uploaded file data before resuming…';
    if (await resumedBytesMatch(file, session)) {
        state.result = '';
        return session;
    }

    // A completed upload is immutable and belongs to Storage. If a file
    // selected later has the same metadata but different bytes, do not reuse
    // its receipt and NEVER attempt to delete the completed Storage asset.
    if (!session.complete) {
        await jsonRequest('/' + session.id + '/cancel', {});
    }
    forget(key);
    state.result = 'The selected file changed; starting a fresh verified transfer.';
    const fresh = await jsonRequest('/start', { filename: file.name, size: file.size });
    remember(key, fresh.id);

    return fresh;
}

// Progress is based on acknowledged workflow checkpoints, not elapsed-time
// extrapolation. A thumbnail subprocess has no finer native progress signal.
const processingMilestones = {
    verifying: { percent: 82, label: 'Verifying transferred file' },
    checking: { percent: 87, label: 'Checking existing Storage media' },
    preparing: { percent: 91, label: 'Generating media preview' },
    saving: { percent: 96, label: 'Saving original and preview' },
    finalizing: { percent: 98, label: 'Finalizing Storage entry' },
    complete: { percent: 99, label: 'Media ready in Storage' },
};

async function finishWithProgress(session, state) {
    const controller = new AbortController();
    const signal = controller.signal;
    const progressUrl = endpoint + '/' + encodeURIComponent(session.id) + '/status';

    // The server finalizes under a session lock. Its existing atomic manifest
    // can be safely observed by a separate authenticated status request.
    const poll = (async () => {
        while (!signal.aborted) {
            try {
                const response = await fetch(progressUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: requestHeaders(),
                    cache: 'no-store',
                    signal,
                });
                if (response.ok && !signal.aborted) {
                    const report = await response.json();
                    const step = processingMilestones[report.phase];
                    if (step && !signal.aborted) {
                        state.progress = Math.max(state.progress, step.percent);
                        state.processingStep = step.label;
                    }
                }
            } catch {
                // The finalization request owns success and error handling.
                // A transient status-read failure does not fail the ingest.
            }
            if (!signal.aborted) {
                await new Promise((resolve) => setTimeout(resolve, 550));
            }
        }
    })();

    try {
        return await jsonRequest('/' + session.id + '/finish', {});
    } finally {
        controller.abort();
        // Do not await the poll's remaining delay before opening Stage.
        void poll;
    }
}

// Exact transferred bytes remain useful when a small local file races
// through the transfer phase before the first visible progress event.
function transferBytesLabel(bytes, total) {
    const unit = total >= 1048576 ? 1048576 : total >= 1024 ? 1024 : 1;
    const label = unit === 1048576 ? 'MiB' : unit === 1024 ? 'KiB' : 'bytes';
    const digits = unit === 1 ? 0 : 1;
    return (Math.min(bytes, total) / unit).toFixed(digits) + ' / ' + (total / unit).toFixed(digits) + ' ' + label;
}

async function transfer(file, state, fileIndex, total) {
    state.transferDetail = transferBytesLabel(0, file.size);
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

    session = await verifyOrRestart(file, session, key, state);
    state.transferDetail = transferBytesLabel(Number(session.offset || 0), file.size);
    remember(key, session.id);
    let failures = 0;
    while (!session.complete && session.offset < file.size) {
        const offset = Number(session.offset);
        const next = Math.min(file.size, offset + Number(session.chunk_size));
        state.phase = 'uploading';
        state.progress = Math.max(state.progress, Math.round((fileIndex + offset / file.size) * 800 / total) / 10);
        state.transferDetail = transferBytesLabel(offset, file.size);

        try {
            session = await sendChunk(session.id, offset, file.slice(offset, next), (sent) => {
                state.progress = Math.max(state.progress, Math.min(80, Math.round((fileIndex + (offset + sent) / file.size) * 800 / total) / 10));
                state.transferDetail = transferBytesLabel(offset + sent, file.size);
            });
            state.transferDetail = transferBytesLabel(Number(session.offset || next), file.size);
            failures = 0;
        } catch (error) {
            if (!error?.retryable || ++failures > 3) throw error;
            state.result = 'Connection interrupted; resuming from the last saved chunk…';
            session = await jsonRequest('/start', {
                filename: file.name, size: file.size, resume_id: session.id,
            });
            session = await verifyOrRestart(file, session, key, state);
        }
    }

    state.phase = 'processing';
    state.transferDetail = transferBytesLabel(file.size, file.size);
    state.progress = Math.max(state.progress, 80);
    state.processingStep = 'Verifying transferred file';
    state.result = '';
    if (!session.complete) {
        try {
            session = await finishWithProgress(session, state);
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
    state.progress = Math.max(state.progress, 99);
    state.processingStep = 'Media ready in Storage';
    return { ...session, resumeKey: key };
}

/**
 * State is the existing Alpine dropzone. The only per-consumer differences
 * are the canonical post-ingest Storage refresh or Gallery Artwork queue.
 */
export async function adminMediaUploader(state, input, wire, context) {
    // Copy File references before clearing the input; each new selection joins
    // this one queue even while a previous file is still transferring.
    const selected = Array.from(input.files || []);
    input.value = '';
    if (!selected.length) return;

    state.pendingUploads ??= [];
    state.pendingUploads.push(...selected);
    state.fileCount += selected.length;
    if (state.uploadRunning) return;

    state.uploadRunning = true;
    state.completed = 0;
    state.progress = 0;
    state.processingStep = '';
    state.transferDetail = '';
    state.result = '';
    state.phase = 'uploading';
    let added = 0;
    let duplicates = 0;
    let failed = 0;
    const errors = [];
    let notificationAttempted = false;
    let batchStarted = context !== 'gallery';

    try {
        if (context === 'gallery') {
            await wire.beginDirectPrimaryMediaBatch();
            batchStarted = true;
        }

        // Serial file ingest owns exactly one active progress indicator. New
        // selections can extend the queue while the loop is awaiting the server.
        while (state.pendingUploads.length) {
            const file = state.pendingUploads.shift();
            let savedToStorage = false;
            state.progress = 0;
            state.processingStep = '';
            state.transferDetail = '';
            state.phase = 'uploading';
            state.result = '';
            try {
                const result = await transfer(file, state, 0, 1);
                savedToStorage = true;
                // Gallery receives the file as soon as Storage acknowledges it,
                // not after all subsequent files finish uploading.
                if (context === 'gallery') {
                    state.processingStep = 'Adding media to Gallery Stage';
                    await wire.queueResumablePrimaryMediaAsset(result.asset_id, Boolean(result.duplicate));
                }
                // 100% means that the file is ready to use in its consumer.
                state.progress = 100;
                state.processingStep = context === 'gallery' ? 'Ready in Gallery Stage' : 'Ready in Storage';
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
                    refreshWarning = ' The Storage list did not refresh. Reload the page to see the saved files.';
                }
            }
            notificationAttempted = true;
            state.result = await wire.notifyDirectMediaBatch(added, duplicates, failed, errors.join('\n'));
            state.result += refreshWarning;
        }
    } catch (error) {
        if (notificationAttempted) {
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
                // The inline message remains accessible if the connection is down.
            }
        }
    } finally {
        state.uploadRunning = false;
        state.phase = 'result';
        // If the server refused the initial batch, do not recursively retry
        // the same request. The user can reselect the files after recovery.
        if (!batchStarted) state.pendingUploads.length = 0;
        // A user may append files during the final status round-trip. Never
        // leave those selections stranded: process them in the next batch.
        if (state.pendingUploads.length) {
            const pending = state.pendingUploads.splice(0);
            state.fileCount = 0;
            state.completed = 0;
            state.pendingUploads = [];
            const nextInput = { files: pending, value: '' };
            await adminMediaUploader(state, nextInput, wire, context);
        } else {
            state.fileCount = 0;
            state.completed = 0;
        }
    }
}

/** Resumable field shared by Artwork create/edit, replacement and extra images. */
export async function adminMediaUploadField(state, input, wire, statePath, imagesOnly = false) {
    if (state.phase === 'uploading' || state.phase === 'processing') return;
    const file = input.files?.[0];
    if (!file) return;
    state.phase = 'uploading';
    state.progress = 0;
    state.processingStep = '';
    state.result = '';
    try {
        // The transfer updates this Alpine state through the shared transport.
        if (imagesOnly && !file.type.startsWith('image/') && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
            throw new Error('This field accepts images only.');
        }
        const outcome = await transfer(file, state, 0, 1);
        try {
            state.processingStep = 'Linking media to field';
            await wire.set(statePath, outcome.asset_id);
        } catch {
            throw new Error('File saved to Storage, but could not be linked to this field. Select it from Storage or contact the site administrator.');
        }
        state.progress = 100;
        state.processingStep = 'Ready in Artwork field';
        forget(outcome.resumeKey);
        state.result = file.name + ' — uploaded to Storage and ready to save with the Artwork';
    } catch (error) {
        const displayName = file.name.length > 140 ? file.name.slice(0, 137) + '…' : file.name;
        state.result = displayName + ' — ' + (error?.message || 'Upload failed. Re-select the same file to resume.');
        try { await jsonRequest('/failure', { message: state.result.slice(0, 400) }); } catch {
            // The inline message remains accessible if the connection is down.
        }
    } finally {
        state.phase = 'result';
        input.value = '';
    }
}
