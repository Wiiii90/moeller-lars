import test from 'node:test';
import assert from 'node:assert/strict';
import {
    advanceNavigationWindow,
    fitNavigationWindow,
} from '../../resources/js/public-navigation.js';

test('fits only complete navigation items into the available rail', () => {
    assert.deepEqual(fitNavigationWindow([60, 60, 95, 60], 190), [0, 1]);
    assert.deepEqual(fitNavigationWindow([60, 60, 95, 60], 190, 2), [2, 3]);
});

test('makes room for the next complete item by dropping multiple left items', () => {
    const widths = [40, 40, 40, 120, 40];
    assert.deepEqual(fitNavigationWindow(widths, 130), [0, 1, 2]);
    assert.deepEqual(advanceNavigationWindow(widths, [0, 1, 2], 130, 1), [3]);
    assert.deepEqual(advanceNavigationWindow(widths, [3], 130, 1), [4, 0, 1]);
});

test('wraps cyclically in both directions and keeps menu order', () => {
    const widths = [40, 40, 40, 40];
    const first = fitNavigationWindow(widths, 120);
    const backwards = advanceNavigationWindow(widths, first, 120, -1);
    assert.deepEqual(backwards, [3, 0, 1]);
    assert.deepEqual(advanceNavigationWindow(widths, backwards, 120, 1), first);

    const last = fitNavigationWindow(widths, 120, 2);
    assert.deepEqual(last, [2, 3, 0]);
    assert.deepEqual(advanceNavigationWindow(widths, last, 120, 1), [3, 0, 1]);
});

test('keeps an overwide label as one item so the presentation can wrap its text', () => {
    const widths = [300, 35, 35];
    assert.deepEqual(fitNavigationWindow(widths, 90), [0]);
    assert.deepEqual(advanceNavigationWindow(widths, [2], 90, 1), [0]);
    assert.deepEqual(advanceNavigationWindow(widths, [0], 90, 1), [1, 2]);
});

test('handles an empty navigation list and rejects invalid movement direction', () => {
    assert.deepEqual(fitNavigationWindow([], 200), []);
    assert.deepEqual(advanceNavigationWindow([], [], 200, 1), []);
    assert.throws(() => advanceNavigationWindow([100], [0], 200, 0), RangeError);
});
