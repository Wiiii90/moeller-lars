import test from 'node:test';
import assert from 'node:assert/strict';
import { FOLIO_PROPERTIES, FOLIO_VARIANTS, advancePublicFolio, chooseNextFolioVariant } from '../../resources/js/public-folio-motion.js';

test('every folio destination stays valid and differs from the prior variant', () => {
    assert.ok(FOLIO_VARIANTS.length >= 2);
    for (let prior = 0; prior < FOLIO_VARIANTS.length; prior += 1) {
        for (let choice = 0; choice < FOLIO_VARIANTS.length - 1; choice += 1) {
            const next = chooseNextFolioVariant(prior, () => (choice + .5) / (FOLIO_VARIANTS.length - 1));
            assert.ok(next >= 0 && next < FOLIO_VARIANTS.length);
            assert.notEqual(next, prior);
        }
    }
});

test('folios keep all connected corner and return inset lengths', () => {
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, FOLIO_PROPERTIES.length);
        for (const length of variant) {
            assert.match(length, /^(?:\d+(?:\.\d+)?|\.\d+)rem$/);
            assert.ok(parseFloat(length) > 0);
        }
    }
});

test('extreme random draws and missing prior state cannot select an invalid variant', () => {
    assert.equal(chooseNextFolioVariant(0, () => 0), 1);
    const last = FOLIO_VARIANTS.length - 1;
    assert.equal(chooseNextFolioVariant(last, () => 1), last - 1);
    assert.ok(chooseNextFolioVariant(-1, () => .5) > 0);
});

test('first folio is the exact approved original rather than an arbitrary replacement', () => {
    assert.deepEqual(FOLIO_VARIANTS[0].slice(0, 4), ['1.10rem', '4.00rem', '3.80rem', '1.15rem']);
});

test('folio navigation changes connected corner properties once without overriding reduced motion', () => {
    const previousWindow = globalThis.window;
    const properties = new Map();
    const attributes = new Set();
    const dataset = {};
    let measured = 0;
    let reduceMotion = true;
    const element = {
        dataset,
        hasAttribute: (name) => attributes.has(name),
        setAttribute: (name) => attributes.add(name),
        getBoundingClientRect: () => { measured += 1; return {}; },
        style: { setProperty: (name, value) => properties.set(name, value) },
    };
    const root = { querySelector: (selector) => {
        assert.equal(selector, '.public-folio-decoration');
        return element;
    } };
    globalThis.window = { matchMedia: (query) => {
        assert.equal(query, '(prefers-reduced-motion: reduce)');
        return { matches: reduceMotion };
    } };
    try {
        assert.equal(advancePublicFolio(root), false);
        assert.equal(attributes.size, 0);
        assert.equal(properties.size, 0);
        assert.equal(measured, 0);

        reduceMotion = false;
        assert.equal(advancePublicFolio(root), true);
        assert.equal(attributes.has('data-folio-active'), true);
        assert.equal(measured, 1);
        assert.deepEqual([...properties.keys()], FOLIO_PROPERTIES);
        assert.match(dataset.folioVariant, /^\\d+$/);
        const previousValues = [...properties.values()];
        assert.ok(FOLIO_VARIANTS.some((variant) =>
            variant.every((value, index) => value === previousValues[index])));

        const firstVariant = dataset.folioVariant;
        assert.equal(advancePublicFolio(root), true);
        assert.equal(measured, 1);
        assert.notEqual(dataset.folioVariant, firstVariant);
        assert.notDeepEqual([...properties.values()], previousValues);

        assert.equal(advancePublicFolio({ querySelector: () => null }), false);
        assert.equal(measured, 1);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});
