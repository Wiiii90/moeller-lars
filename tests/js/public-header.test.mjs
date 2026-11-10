import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack } from '../../resources/js/public-header.js';

test('Inside retains enough title and navigation width when aligned left or right', () => {
    assert.equal(headerNeedsStack(640, 130), false);
    assert.equal(headerNeedsStack(360, 130), true);
    assert.equal(headerNeedsStack(420, 70), true);
    assert.equal(headerNeedsStack(640, 320), true);
    assert.equal(headerNeedsStack(470, 100), true);
    assert.equal(headerNeedsStack(540, 100), false);
    assert.equal(headerNeedsStack(560, 320), true);
    assert.equal(headerNeedsStack(800, 145), false);
});

test('Inside becomes an Above Menu row throughout the responsive viewport', () => {
    assert.equal(headerNeedsStack(520, 145, true), true);
    assert.equal(headerNeedsStack(640, 70, true), true);
    assert.equal(headerNeedsStack(800, 145, false), false);
    assert.equal(headerNeedsStack(300, 145, false), true);
});
