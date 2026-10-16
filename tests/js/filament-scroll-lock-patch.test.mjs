import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const patch = readFileSync(
    new URL('../../scripts/patch-filament-scroll-lock.php', import.meta.url),
    'utf8',
);
const source = patch.split("$replacement = <<<'JS'\n")[1]?.split('\nJS;')[0];

assert.ok(source?.endsWith(',qo='), 'Versioned replacement source must exist');

function modalLock({ height, viewportHeight, scrollY = 0 }) {
    const originalRoot = { overflow: '', overflowX: '', overflowY: '', minHeight: '' };
    const originalBody = { position: '', top: '', left: '', width: '' };
    const root = { style: { ...originalRoot }, scrollHeight: height, clientHeight: viewportHeight };
    const body = { style: { ...originalBody } };
    const listeners = new Map();
    const window = {
        scrollX: 0,
        scrollY,
        addEventListener(name, listener) { listeners.set(name, listener); },
        removeEventListener(name, listener) {
            if (listeners.get(name) === listener) listeners.delete(name);
        },
        scrollTo({ left, top }) { this.scrollX = left; this.scrollY = top; },
    };
    const environment = { document: { documentElement: root, body }, window };
    const sourceWithoutNextDeclaration = source.slice(0, -',qo='.length);
    const { acquire, release } = runInNewContext(
        `${sourceWithoutNextDeclaration}; ({ acquire: Ns, release: js })`,
        environment,
    );

    return { acquire, release, root, body, window, listeners, originalRoot, originalBody };
}

test('long pages retain their native scrollbar and frozen scroll position', () => {
    const state = modalLock({ height: 2400, viewportHeight: 800, scrollY: 560 });
    state.acquire();
    assert.equal(state.root.style.overflowY, 'scroll');
    assert.equal(state.root.style.minHeight, '2400px');
    assert.equal(state.body.style.position, 'fixed');
    assert.equal(state.body.style.top, '-560px');

    state.window.scrollY = 640;
    state.listeners.get('scroll')();
    assert.equal(state.window.scrollY, 560);

    state.release();
    assert.deepEqual(state.root.style, state.originalRoot);
    assert.deepEqual(state.body.style, state.originalBody);
    assert.equal(state.listeners.size, 0);
    assert.equal(state.window.scrollY, 560);
});

test('short pages do not gain a native scrollbar or reserved gutter', () => {
    const state = modalLock({ height: 800, viewportHeight: 800 });
    state.acquire();
    // This fixture uses plain style objects, not CSSStyleDeclaration: the
    // overflow shorthand does not populate overflowY here as it does in a DOM.
    assert.equal(state.root.style.overflow, 'hidden');
    assert.equal(state.root.style.minHeight, '');
    assert.equal(state.body.style.position, '');
    assert.equal(state.listeners.size, 0);
    state.release();
    assert.deepEqual(state.root.style, state.originalRoot);
    assert.deepEqual(state.body.style, state.originalBody);
    assert.equal(state.listeners.size, 0);
    assert.equal(state.window.scrollY, 0);
});

test('nested short-page modals keep the body in flow until final unlock', () => {
    const state = modalLock({ height: 700, viewportHeight: 800 });
    state.acquire();
    state.acquire();
    assert.equal(state.root.style.overflow, 'hidden');
    assert.deepEqual(state.body.style, state.originalBody);
    assert.equal(state.listeners.size, 0);
    state.release();
    assert.equal(state.root.style.overflow, 'hidden');
    state.release();
    assert.deepEqual(state.root.style, state.originalRoot);
    assert.deepEqual(state.body.style, state.originalBody);
});

test('nested Filament modals release the shared lock only after the last close', () => {
    const state = modalLock({ height: 2400, viewportHeight: 800, scrollY: 440 });
    state.acquire();
    state.acquire();
    state.release();
    assert.equal(state.body.style.position, 'fixed');
    assert.equal(state.listeners.size, 1);
    state.release();
    assert.deepEqual(state.body.style, state.originalBody);
    assert.equal(state.listeners.size, 0);
});
