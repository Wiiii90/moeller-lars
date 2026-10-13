import test from 'node:test';
import assert from 'node:assert/strict';
import { FOLIO_VARIANTS, advancePublicFolio, chooseNextFolioVariant } from '../../resources/js/public-folio-motion.js';

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

test('folios use four complete, strictly positive corner inset lengths', () => {
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, 4);
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
    assert.deepEqual(FOLIO_VARIANTS[0], ['1.10rem', '4.00rem', '3.80rem', '1.15rem']);
});

test('folio navigation changes connected corner properties once without overriding reduced motion', () => {
    const previousWindow = globalThis.window;
    const properties = new Map();
    const attributes = new Set();
    let measured = 0;
    let reduceMotion = true;
    const element = {
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
        advancePublicFolio(root);
        assert.equal(attributes.size, 0);
        assert.equal(properties.size, 0);
        assert.equal(measured, 0);

        reduceMotion = false;
        advancePublicFolio(root);
        assert.equal(attributes.has('data-folio-active'), true);
        assert.equal(measured, 1);
        assert.deepEqual([...properties.keys()], [
            '--public-folio-left-top',
            '--public-folio-left-bottom',
            '--public-folio-right-top',
            '--public-folio-right-bottom',
        ]);
        const previousValues = [...properties.values()];
        assert.ok(FOLIO_VARIANTS.some((variant) =>
            variant.every((value, index) => value === previousValues[index])));

        advancePublicFolio(root);
        assert.equal(measured, 1);
        assert.notDeepEqual([...properties.values()], previousValues);

        advancePublicFolio({ querySelector: () => null });
        assert.equal(measured, 1);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});
