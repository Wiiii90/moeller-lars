import test from 'node:test';
import assert from 'node:assert/strict';
import { FOLIO_VARIANTS, chooseNextFolioVariant } from '../../resources/js/public-folio-motion.js';

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

test('folios use four complete, strictly positive corner inset lengths', () => {
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, 4);
        for (const length of variant) {
            assert.match(length, /^\d+(?:\.\d+)?rem$/);
            assert.ok(parseFloat(length) > 0);
        }
    }
});

test('extreme random draws and missing prior state cannot select an invalid variant', () => {
    assert.equal(chooseNextFolioVariant(0, () => 0), 1);
    assert.equal(chooseNextFolioVariant(9, () => 1), 8);
    assert.ok(chooseNextFolioVariant(-1, () => .5) > 0);
});
