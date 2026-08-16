import test from 'node:test';
import assert from 'node:assert/strict';
import {
    MIN_SCALE,
    MAX_SCALE,
    adjacentIndex,
    calculatePanBounds,
    clamp,
    clampPan,
    zoomAroundPoint,
} from '../../resources/js/artwork-viewer.js';

test('clamp handles lower, upper, and in-range values', () => {
    assert.equal(clamp(-1, 0, 2), 0);
    assert.equal(clamp(3, 0, 2), 2);
    assert.equal(clamp(1, 0, 2), 1);
});

test('calculatePanBounds handles fitting and overflowing dimensions', () => {
    assert.deepEqual(calculatePanBounds(100, 100, 200, 200, 1), { maxX: 0, maxY: 0 });
    assert.deepEqual(calculatePanBounds(400, 300, 200, 200, 2), { maxX: 300, maxY: 200 });
    assert.deepEqual(calculatePanBounds(400, 100, 200, 300, 1), { maxX: 100, maxY: 0 });
});

test('clampPan constrains both axes', () => {
    assert.deepEqual(clampPan(4, -8, { maxX: 3, maxY: 6 }), { x: 3, y: -6 });
    assert.deepEqual(clampPan(2, -4, { maxX: 3, maxY: 6 }), { x: 2, y: -4 });
});

test('adjacentIndex has bounded non-wrapping navigation', () => {
    assert.equal(adjacentIndex(0, 1, 3), 1);
    assert.equal(adjacentIndex(2, -1, 3), 1);
    assert.equal(adjacentIndex(0, -1, 3), null);
    assert.equal(adjacentIndex(2, 1, 3), null);
    assert.throws(() => adjacentIndex(0, 0, 3), RangeError);
});

test('zoomAroundPoint preserves center and off-center image points', () => {
    assert.deepEqual(zoomAroundPoint({ scale: 1, x: 0, y: 0 }, 2, 0, 0), { scale: 2, x: 0, y: 0 });
    const state = { scale: 1.5, x: -20, y: 12 };
    const point = { x: 80, y: -40 };
    const localX = (point.x - state.x) / state.scale;
    const localY = (point.y - state.y) / state.scale;
    const next = zoomAroundPoint(state, 3, point.x, point.y);
    assert.ok(Math.abs(next.x + localX * next.scale - point.x) < 1e-10);
    assert.ok(Math.abs(next.y + localY * next.scale - point.y) < 1e-10);
});

test('zoomAroundPoint clamps scales and remains finite under repetition', () => {
    assert.equal(zoomAroundPoint({ scale: 1, x: 0, y: 0 }, 0, 0, 0).scale, MIN_SCALE);
    assert.equal(zoomAroundPoint({ scale: 1, x: 0, y: 0 }, Infinity, 0, 0).scale, MAX_SCALE);
    let state = { scale: 1, x: 0, y: 0 };
    for (let i = 0; i < 100; i += 1) state = zoomAroundPoint(state, state.scale * 1.25, 10, 10);
    for (let i = 0; i < 100; i += 1) state = zoomAroundPoint(state, state.scale / 1.25, -10, -10);
    assert.ok(Number.isFinite(state.scale) && Number.isFinite(state.x) && Number.isFinite(state.y));
});
