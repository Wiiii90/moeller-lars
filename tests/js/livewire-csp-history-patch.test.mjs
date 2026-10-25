import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { runInNewContext } from 'node:vm';

const root = resolve(import.meta.dirname, '../..');
const script = readFileSync(join(root, 'scripts/patch-livewire-csp-history.php'), 'utf8');
const serializer = script.match(/\$serializer = <<<'JS'\n([\s\S]*?)\nJS;/)?.[1];
assert.ok(serializer, 'Versioned patch must expose its actual serializer');

test('history serializer preserves nonce properties and excludes transient progress without touching the live document', () => {
    const sources = [{ nonce: 'active-document-nonce' }, { nonce: 'active-document-nonce' }, { nonce: '' }];
    const attributes = ['', null, null];
    let progressRemoved = false;
    const snapshot = {
        querySelectorAll: (selector) => {
            assert.equal(selector, 'script,style,link');
            return attributes.map((_, index) => ({
            setAttribute(name, value) { assert.equal(name, 'nonce'); attributes[index] = value; },
            }));
        },
        querySelector(selector) { assert.equal(selector, '#nprogress'); return { remove() { progressRemoved = true; } }; },
        get outerHTML() { return JSON.stringify({ attributes, progress: !progressRemoved }); },
    };
    const document = {
        documentElement: { cloneNode(deep) { assert.equal(deep, true); return snapshot; } },
        querySelectorAll(selector) { assert.equal(selector, 'script,style,link'); return sources; },
    };
    const result = JSON.parse(runInNewContext(`${serializer}\nserializeCspHistoryDocument()`, { document }));
    assert.deepEqual(result, { attributes: ['active-document-nonce', 'active-document-nonce', null], progress: false });
    assert.deepEqual(sources, [{ nonce: 'active-document-nonce' }, { nonce: 'active-document-nonce' }, { nonce: '' }]);
});

function fixture(callback) {
    const parent = join(root, 'storage/framework/testing');
    mkdirSync(parent, { recursive: true });
    const directory = mkdtempSync(join(parent, 'livewire-patch-'));
    assert.ok(directory.startsWith(`${parent}${process.platform === 'win32' ? '\\' : '/'}livewire-patch-`));
    const dist = join(directory, 'vendor/livewire/livewire/dist');
    mkdirSync(dist, { recursive: true });
    mkdirSync(join(directory, 'scripts'));
    writeFileSync(join(directory, 'scripts/patch-livewire-csp-history.php'), script);
    const lock = { packages: [{ name: 'livewire/livewire', version: 'v4.4.0' }] };
    writeFileSync(join(directory, 'composer.lock'), JSON.stringify(lock));
    for (const file of ['livewire.csp.js', 'livewire.csp.min.js']) {
        const current = readFileSync(join(root, 'vendor/livewire/livewire/dist', file), 'utf8');
        const original = current.includes(serializer)
            ? current.replace(serializer, '').replaceAll('serializeCspHistoryDocument()', 'document.documentElement.outerHTML')
            : current;
        writeFileSync(join(dist, file), original);
    }
    writeFileSync(join(dist, 'livewire.min.js'), 'unchanged-admin-runtime');
    writeFileSync(join(dist, 'manifest.json'), '{"/livewire.js":"original"}');
    const apply = () => spawnSync('php', [join(directory, 'scripts/patch-livewire-csp-history.php')], { encoding: 'utf8' });
    try { callback({ directory, dist, lock, apply }); }
    finally { rmSync(directory, { recursive: true, force: true }); }
}

test('public debug and production bundle patch is idempotent and leaves the Admin runtime untouched', () => fixture(({ dist, apply }) => {
    const first = apply();
    assert.equal(first.status, 0, first.stderr);
    const before = ['livewire.csp.js', 'livewire.csp.min.js', 'manifest.json'].map(file => readFileSync(join(dist, file), 'utf8'));
    for (const bundle of before.slice(0, 2)) {
        assert.equal(bundle.split('serializeCspHistoryDocument()').length - 1, 3);
        assert.equal(bundle.includes('document.documentElement.outerHTML'), false);
    }
    assert.equal(apply().status, 0);
    assert.deepEqual(['livewire.csp.js', 'livewire.csp.min.js', 'manifest.json'].map(file => readFileSync(join(dist, file), 'utf8')), before);
    assert.equal(readFileSync(join(dist, 'livewire.min.js'), 'utf8'), 'unchanged-admin-runtime');
}));

test('unreviewed Livewire versions or modified upstream assets fail before writing any bundle', () => fixture(({ directory, dist, lock, apply }) => {
    const debug = readFileSync(join(dist, 'livewire.csp.js'), 'utf8');
    lock.packages[0].version = 'v4.5.0';
    writeFileSync(join(directory, 'composer.lock'), JSON.stringify(lock));
    assert.notEqual(apply().status, 0);
    assert.equal(readFileSync(join(dist, 'livewire.csp.js'), 'utf8'), debug);
    lock.packages[0].version = 'v4.4.0';
    writeFileSync(join(directory, 'composer.lock'), JSON.stringify(lock));
    writeFileSync(join(dist, 'livewire.csp.min.js'), 'unreviewed upstream source');
    assert.notEqual(apply().status, 0);
    assert.equal(readFileSync(join(dist, 'livewire.csp.js'), 'utf8'), debug);
}));
