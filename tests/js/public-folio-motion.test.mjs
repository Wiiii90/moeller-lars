import test from 'node:test';
import assert from 'node:assert/strict';
import { FOLIO_VARIANTS, FOLIO_PROPERTIES, chooseNextFolioVariant, advancePublicFolio } from '../../resources/js/public-folio-motion.js';

test('every folio destination stays valid and differs from the prior variant', () => {
    assert.equal(FOLIO_VARIANTS.length, 10);
    for (let prior = 0; prior < FOLIO_VARIANTS.length; prior += 1) {
        for (let choice = 0; choice < FOLIO_VARIANTS.length - 1; choice += 1) {
            const next = chooseNextFolioVariant(prior, () => (choice + .5) / 9);
            assert.ok(next >= 0 && next < FOLIO_VARIANTS.length);
            assert.notEqual(next, prior);
        }
    }
});

test('folios keep four complete edge endpoints and two connected return widths', () => {
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, 6);
        for (const length of variant) {
            assert.match(length, /^(?:\d+(?:\.\d+)?|\.\d+)rem$/);
            assert.ok(parseFloat(length) > 0);
        }
    }
});

test('extreme random draws and missing prior state cannot select an invalid variant', () => {
    assert.equal(chooseNextFolioVariant(0, () => 0), 1);
    assert.equal(chooseNextFolioVariant(9, () => 1), 8);
    assert.ok(chooseNextFolioVariant(-1, () => .5) > 0);
});

test('first folio is the exact approved original rather than an arbitrary replacement', () => {
    assert.deepEqual(FOLIO_VARIANTS[0], ['1.10rem', '4.00rem', '3.80rem', '1.15rem', '.55rem', '.55rem']);
});

test('folio variants never remove return corners or greatly displace the accepted silhouette', () => {
    const original = FOLIO_VARIANTS[0].map(parseFloat);
    FOLIO_VARIANTS.forEach((values) => {
        values.forEach((value, position) => {
            assert.ok(Math.abs(parseFloat(value) - original[position]) <= (position < 4 ? .85 : .5),
                'outside contour end must stay close to the accepted position');
        });
    });
});

test('a completed visit activates all six transition properties on the same node', () => {
    const updated = new Map();
    const attributes = new Set();
    const element = {
        dataset: {},
        hasAttribute: (name) => attributes.has(name),
        setAttribute: (name) => attributes.add(name),
        getBoundingClientRect: () => ({ width: 100 }),
        style: { setProperty: (name, value) => updated.set(name, value) },
    };
    const root = { querySelector: () => element };
    const priorWindow = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: false }) };
    try {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(attributes.has('data-folio-active'), true);
        assert.deepEqual([...updated.keys()], FOLIO_PROPERTIES);
        const first = element.dataset.folioVariant;
        assert.ok(first && first !== '1');
        assert.equal(advancePublicFolio(root), true);
        assert.notEqual(element.dataset.folioVariant, first);
    } finally {
        globalThis.window = priorWindow;
    }
});

test('reduced-motion users retain the original untouched drawing', () => {
    let wrote = false;
    const element = {
        hasAttribute: () => false,
        setAttribute: () => { wrote = true; },
    };
    const priorWindow = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: true }) };
    try {
        assert.equal(advancePublicFolio({ querySelector: () => element }), false);
        assert.equal(wrote, false);
    } finally {
        globalThis.window = priorWindow;
    }
});
