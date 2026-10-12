import test from 'node:test';
import assert from 'node:assert/strict';
import { adjacentHeroIndex } from '../../resources/js/home-hero.js';

test('Hero artwork navigation wraps within the resolved group in both directions', () => {
    assert.equal(adjacentHeroIndex(0, 1, 3), 1);
    assert.equal(adjacentHeroIndex(2, 1, 3), 0);
    assert.equal(adjacentHeroIndex(0, -1, 3), 2);
    assert.equal(adjacentHeroIndex(1, -1, 3), 0);
    assert.equal(adjacentHeroIndex(0, -1, 2), 1);
});

test('Hero navigation rejects invalid group indices and directions', () => {
    assert.throws(() => adjacentHeroIndex(0, 1, 0), RangeError);
    assert.throws(() => adjacentHeroIndex(3, 1, 3), RangeError);
    assert.throws(() => adjacentHeroIndex(0, 0, 3), RangeError);
    assert.throws(() => adjacentHeroIndex(-1, -1, 3), RangeError);
});
