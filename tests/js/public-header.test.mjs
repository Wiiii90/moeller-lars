import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack } from '../../resources/js/public-header.js';

test('Inside fits a natural-width title, a fixed breathing gap and the minimum navigation rail', () => {
    const gap = 24;
    assert.equal(headerNeedsStack(640, 130, gap), false);
    assert.equal(headerNeedsStack(360, 130, gap), true);
    assert.equal(headerNeedsStack(420, 70, gap), false);
    assert.equal(headerNeedsStack(640, 320, gap), false);
    assert.equal(headerNeedsStack(470, 100, gap), false);
    assert.equal(headerNeedsStack(540, 100, gap), false);
    assert.equal(headerNeedsStack(560, 320, gap), true);
    assert.equal(headerNeedsStack(800, 145, gap), false);
});

test('Inside stack threshold follows actual title width, not a percentage of available space', () => {
    const gap = 24;
    // 260px title + 24px breathing room + 240px navigation = 524px.
    assert.equal(headerNeedsStack(524, 260, gap), false);
    assert.equal(headerNeedsStack(523, 260, gap), true);
    assert.equal(headerNeedsStack(600, 320, gap), false);
    assert.equal(headerNeedsStack(583, 320, gap), true);
});

test('Inside becomes an Above Menu row throughout the responsive viewport', () => {
    assert.equal(headerNeedsStack(520, 145, 24, true), true);
    assert.equal(headerNeedsStack(640, 70, 24, true), true);
    assert.equal(headerNeedsStack(800, 145, 24, false), false);
    assert.equal(headerNeedsStack(300, 145, 24, false), true);
});
