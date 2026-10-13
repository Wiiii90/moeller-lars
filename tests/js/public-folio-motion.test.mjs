import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, chooseNextFolioVariant, capturePublicFolio, advancePublicFolio,
    isPublicFolioDebugEnabled, syncPublicFolioDebug,
} from '../../resources/js/public-folio-motion.js';

function fixture({ active = false, computed = ['20px', '64px', '60px', '18px', '9px', '9px'] } = {}) {
    const animations = [];
    const makeEdge = (side, values) => ({
        side,
        style: {},
        computed: { top: values[0], bottom: values[1], width: values[2] },
        animate: (frames, options) => { animations.push({ side, frames, options }); },
    });
    const left = makeEdge('left', [computed[0], computed[1], computed[4]]);
    const right = makeEdge('right', [computed[2], computed[3], computed[5]]);
    const attributes = new Set(active ? ['data-folio-active'] : []);
    const folio = {
        dataset: {},
        hasAttribute: (name) => attributes.has(name),
        setAttribute: (name) => attributes.add(name),
        querySelector: (selector) => selector.endsWith('__left') ? left : right,
    };
    return {
        root: { querySelector: () => folio },
        left, right, folio, animations, attributes,
    };
}

function withBrowser(run, reduced = false) {
    const previousWindow = globalThis.window;
    const previousComputed = globalThis.getComputedStyle;
    globalThis.window = { matchMedia: () => ({ matches: reduced }) };
    globalThis.getComputedStyle = (element) => element.computed;
    try { run(); } finally {
        globalThis.window = previousWindow;
        globalThis.getComputedStyle = previousComputed;
    }
}

test('all ten variants contain two complete contours, with exact original geometry first', () => {
    assert.equal(FOLIO_VARIANTS.length, 10);
    assert.deepEqual(FOLIO_VARIANTS[0], [
        '1.10rem', '4.00rem', '3.80rem', '1.15rem', '.55rem', '.55rem',
    ]);
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, 6);
        assert.ok(variant.every((value) => /^\d*\.?\d+rem$/.test(value) && parseFloat(value) > 0));
    }
});

test('the next variant always differs, including extreme random values', () => {
    for (let prior = 0; prior < 10; prior += 1) {
        for (const random of [0, .1, .3, .5, .8, .99, 1]) {
            const next = chooseNextFolioVariant(prior, () => random);
            assert.ok(next >= 0 && next < 10 && next !== prior);
        }
    }
});

test('a real SPA visit animates ordinary top/bottom/width properties on both attached corners', () => {
    withBrowser(() => {
        const f = fixture();
        assert.equal(advancePublicFolio(f.root), true);
        assert.equal(f.attributes.has('data-folio-active'), true);
        assert.equal(f.animations.length, 2);
        assert.deepEqual(f.animations[0].frames[0], {
            top: '1.10rem', bottom: '4.00rem', width: '.55rem',
        });
        assert.deepEqual(f.animations[1].frames[0], {
            top: '3.80rem', bottom: '1.15rem', width: '.55rem',
        });
        assert.equal(f.animations[0].options.duration, 920);
        assert.equal(f.left.style.top, f.animations[0].frames[1].top);
        assert.equal(f.right.style.bottom, f.animations[1].frames[1].bottom);
    });
});

test('interrupted motion resumes from the rendered intermediate values after body replacement', () => {
    withBrowser(() => {
        const oldPage = fixture({ active: true, computed: [
            '24px', '73px', '61px', '22px', '12px', '13px',
        ] });
        assert.equal(capturePublicFolio(oldPage.root), true);
        const nextPage = fixture();
        assert.equal(advancePublicFolio(nextPage.root), true);
        assert.deepEqual(nextPage.animations[0].frames[0], {
            top: '24px', bottom: '73px', width: '12px',
        });
        assert.deepEqual(nextPage.animations[1].frames[0], {
            top: '61px', bottom: '22px', width: '13px',
        });
    });
});

test('the original fallback remains untouched when reduced motion is requested', () => {
    withBrowser(() => {
        const f = fixture();
        assert.equal(advancePublicFolio(f.root), false);
        assert.equal(f.animations.length, 0);
        assert.equal(f.attributes.has('data-folio-active'), false);
    }, true);
});

test('a missing overlay never crashes an interrupted or completed page visit', () => {
    withBrowser(() => {
        assert.equal(capturePublicFolio({ querySelector: () => null }), false);
        assert.equal(advancePublicFolio({ querySelector: () => null }), false);
    });
});

test('debug mode can override reduced motion only after explicit local opt-in', () => {
    const previousWindow = globalThis.window;
    const previousComputed = globalThis.getComputedStyle;
    const data = new Map();
    const storage = {
        setItem: (key, value) => data.set(key, value),
        getItem: (key) => data.get(key) ?? null,
        removeItem: (key) => data.delete(key),
    };
    const browser = {
        location: { hostname: '127.0.0.1', href: 'http://127.0.0.1:8001/paintings?folio-motion=debug' },
        sessionStorage: storage,
        matchMedia: () => ({ matches: true }),
    };
    globalThis.window = browser;
    globalThis.getComputedStyle = (el) => el.computed;
    try {
        assert.equal(isPublicFolioDebugEnabled(), true);
        const attrs = new Set();
        const body = {
            setAttribute: (key) => attrs.add(key),
            removeAttribute: (key) => attrs.delete(key),
        };
        assert.equal(syncPublicFolioDebug({ body }), true);
        assert.ok(attrs.has('data-public-folio-debug'));

        // The SPA destination no longer has the parameter, yet this browser
        // tab remains explicitly opted into the strong preview animation.
        browser.location.href = 'http://127.0.0.1:8001/prints';
        assert.equal(isPublicFolioDebugEnabled(), true);
        const first = fixture();
        assert.equal(advancePublicFolio(first.root), true);
        assert.equal(first.animations.length, 2);
        assert.equal(first.animations[0].options.duration, 1800);
        assert.equal(first.folio.dataset.folioMotion, 'debug');

        browser.location.href = 'http://127.0.0.1:8001/exhibitions?folio-motion=off';
        assert.equal(syncPublicFolioDebug({ body }), false);
        assert.equal(attrs.has('data-public-folio-debug'), false);
        const restored = fixture();
        assert.equal(advancePublicFolio(restored.root), false);
        assert.equal(restored.animations.length, 0);
    } finally {
        globalThis.window = previousWindow;
        globalThis.getComputedStyle = previousComputed;
    }
});

test('production hosts cannot bypass reduced motion with debug query or saved storage', () => {
    const browser = {
        location: { hostname: 'www.artist.example', href: 'https://www.artist.example/paintings?folio-motion=debug' },
        sessionStorage: { getItem: () => '1' },
    };
    assert.equal(isPublicFolioDebugEnabled(browser), false);
});
