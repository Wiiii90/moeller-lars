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
        throw new Error(errors[0] || payload.message || 'Server rejected the upload (' + response.status + ').');
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
        xhr.onerror = () => reject(new Error('Connection interrupted during transfer.'));
        xhr.ontimeout = () => reject(new Error('The chunk transfer timed out.'));
        xhr.onload = () => {
            let payload = {};
            try { payload = JSON.parse(xhr.responseText); } catch {}
            if (xhr.status < 200 || xhr.status >= 300) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [];
                reject(new Error(errors[0] || payload.message || 'Chunk rejected (' + xhr.status + ').'));
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
            if (++failures > 3) throw error;
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
            // If the response was lost after saving, query the durable session.
            const recheck = await jsonRequest('/start', {
                filename: file.name, size: file.size, resume_id: session.id,
            });
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

    try {
        if (context === 'gallery') await wire.beginDirectPrimaryMediaBatch();

        for (const file of files) {
            try {
                const result = await transfer(file, state, state.completed, files.length);
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
                errors.push(file.name + ' — ' + (error?.message || 'Upload failed.'));
            }
            state.completed++;
            state.progress = Math.round(state.completed * 100 / files.length);
        }

        state.phase = 'processing';
        if (context === 'gallery') {
            const result = await wire.finishDirectPrimaryMediaBatch(failed, duplicates, errors);
            state.result = result?.summary || 'Upload finished.';
        } else {
            if (added > 0) await wire.refreshAfterResumableMediaUpload();
            await wire.notifyDirectMediaBatch(added, duplicates, failed, errors.slice(0, 2).join('; '));
            state.result = added + ' uploaded, ' + duplicates + ' already in Storage, ' + failed + ' failed';
            if (errors.length) state.result += ' · ' + errors.slice(0, 2).join('; ');
        }
    } catch (error) {
        state.result = error?.message || 'Upload could not be completed. Select the same file to resume.';
    } finally {
        input.value = '';
        state.phase = 'result';
    }
}
