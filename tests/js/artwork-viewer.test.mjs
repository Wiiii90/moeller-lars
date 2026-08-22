import test from 'node:test';
import assert from 'node:assert/strict';
import {
    adjacentIndex,
    calculateFittedSize,
    pinchFromGestureStart,
    zoomAroundPoint,
} from '../../resources/js/artwork-viewer.js';

test('fits portrait and landscape artwork completely inside the viewer stage', () => {
    const portrait = calculateFittedSize(1200, 2400, 1000, 700);
    assert.equal(portrait.width, 350);
    assert.equal(portrait.height, 700);

    const landscape = calculateFittedSize(2000, 1000, 1000, 700);
    assert.equal(landscape.width, 1000);
    assert.equal(landscape.height, 500);
});

test('keeps previous and next navigation bounded instead of wrapping', () => {
    assert.equal(adjacentIndex(0, 1, 3), 1);
    assert.equal(adjacentIndex(2, -1, 3), 1);
    assert.equal(adjacentIndex(0, -1, 3), null);
    assert.equal(adjacentIndex(2, 1, 3), null);
});

test('keeps the selected artwork point stable while zooming', () => {
    const state = { scale: 1.5, x: -20, y: 12 };
    const point = { x: 80, y: -40 };
    const localX = (point.x - state.x) / state.scale;
    const localY = (point.y - state.y) / state.scale;
    const next = zoomAroundPoint(state, 3, point.x, point.y);

    assert.ok(Math.abs(next.x + localX * next.scale - point.x) < 1e-10);
    assert.ok(Math.abs(next.y + localY * next.scale - point.y) < 1e-10);
});

test('keeps the pinch anchor stable while scaling', () => {
    const state = { scale: 1, x: 0, y: 0 };
    const result = pinchFromGestureStart(state, 2, 100, 40, 100, 40);

    assert.ok(Math.abs(result.x + 100 * result.scale - 100) < 1e-10);
    assert.ok(Math.abs(result.y + 40 * result.scale - 40) < 1e-10);
});
