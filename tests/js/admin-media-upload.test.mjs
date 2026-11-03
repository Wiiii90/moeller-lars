import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { File } from 'node:buffer';
import { adminMediaUploader } from '../../resources/js/admin-media-upload.js';

/**
 * Exercise the actual transport with a simulated HTTP boundary, not a copy
 * of its file identity/checksum routines. The two selected files share the
 * same name, byte length, modification timestamp, first and last 64 KiB.
 */
test('changed middle bytes invalidate a resumable receipt even with identical file metadata', async () => {
    const length = 200_000;
    const original = Buffer.alloc(length, 7);
    const modified = Buffer.from(original);
    modified[70_000] = 42;
    const meta = { lastModified: 1_700_000_000_000, type: 'image/jpeg' };
    const oldFile = new File([original], 'same-artwork.jpg', meta);
    const changedFile = new File([modified], 'same-artwork.jpg', meta);

    const previous = Object.fromEntries(
        ['document', 'localStorage', 'fetch', 'XMLHttpRequest'].map(key => [
            key, Object.getOwnPropertyDescriptor(globalThis, key),
        ]),
    );
    const storage = new Map();
    const sessions = new Map();
    const starts = [];
    const cancellations = [];
    const saved = [];
    let sessionCounter = 0;

    globalThis.document = {
        cookie: '',
        querySelector: () => ({ content: 'test-csrf-token' }),
    };
    globalThis.localStorage = {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: key => storage.delete(key),
    };

    const response = (body, status = 200) => ({
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    });

    globalThis.fetch = async (url, options) => {
        const path = new URL(url, 'http://test.local').pathname;
        const data = JSON.parse(options.body || '{}');

        if (path.endsWith('/start')) {
            starts.push(data);
            if (data.resume_id) {
                const existing = sessions.get(data.resume_id);
                return existing ? response({ ...existing, chunk_hashes: [...existing.chunk_hashes] })
                    : response({ errors: { media: ['Upload session not found or expired.'] } }, 422);
            }
            const id = 'session-' + (++sessionCounter);
            const session = {
                id, offset: 0, chunk_size: 100_000, chunk_hashes: [],
                complete: false, asset_id: null, duplicate: false,
            };
            sessions.set(id, session);
            return response({ ...session });
        }

        const cancel = path.match(/\/([^/]+)\/cancel$/);
        if (cancel) {
            cancellations.push(cancel[1]);
            sessions.delete(cancel[1]);
            return response({ cancelled: true });
        }

        const finish = path.match(/\/([^/]+)\/finish$/);
        if (finish) {
            const session = sessions.get(finish[1]);
            assert.ok(session);
            assert.equal(session.offset, length);
            session.complete = true;
            session.asset_id = 27;
            saved.push(session.id);
            return response({ ...session });
        }

        throw new Error('Unexpected upload endpoint: ' + path);
    };

    globalThis.XMLHttpRequest = class {
        constructor() {
            this.headers = {};
            this.upload = {};
            this.status = 0;
            this.responseText = '';
        }
        open(method, url) {
            this.method = method;
            this.url = url;
        }
        setRequestHeader(name, value) {
            this.headers[name] = value;
        }
        async send(blob) {
            try {
                const id = this.url.match(/\/([^/]+)\/chunk$/)?.[1];
                const session = sessions.get(id);
                assert.ok(session);
                const offset = Number(this.headers['X-Upload-Offset']);
                const bytes = Buffer.from(await blob.arrayBuffer());
                assert.equal(offset, session.offset);

                // The first selected file loses its second chunk and leaves a
                // genuine acknowledged prefix in the resumable session.
                if (id === 'session-1' && offset > 0) {
                    this.status = 422;
                    this.responseText = JSON.stringify({
                        errors: { media: ['Transfer was interrupted.'] },
                    });
                } else {
                    session.offset += bytes.length;
                    session.chunk_hashes.push(createHash('sha256').update(bytes).digest('hex'));
                    this.status = 200;
                    this.responseText = JSON.stringify({ ...session });
                }
                this.onload?.();
            } catch (error) {
                this.onerror?.(error);
            }
        }
    };

    const wire = {
        refreshAfterResumableMediaUpload: async () => {},
        notifyDirectMediaBatch: async (added, duplicate, failed) =>
            'saved=' + added + ', reused=' + duplicate + ', failed=' + failed,
    };
    const fileInput = file => ({ files: [file], value: file.name });
    const state = () => ({ phase: 'idle', progress: 0, result: '' });

    try {
        const interrupted = state();
        await adminMediaUploader(interrupted, fileInput(oldFile), wire, 'storage');
        assert.equal(interrupted.phase, 'result');
        assert.equal(sessions.get('session-1').offset, 100_000);
        assert.equal(storage.size, 1);

        const retried = state();
        await adminMediaUploader(retried, fileInput(changedFile), wire, 'storage');

        assert.ok(starts.some(call => call.resume_id === 'session-1'));
        assert.deepEqual(cancellations, ['session-1']);
        assert.deepEqual(saved, ['session-2']);
        assert.equal(retried.phase, 'result');
        assert.equal(retried.result, 'saved=1, reused=0, failed=0');
        assert.equal(storage.size, 0);
    } finally {
        for (const [key, descriptor] of Object.entries(previous)) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    }
});
