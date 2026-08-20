import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    MIN_SCALE,
    MAX_SCALE,
    MIN_ATTENTION_MS,
    adjacentIndex,
    calculateFittedSize,
    calculatePanBounds,
    clamp,
    clampPan,
    meaningfulAttentionSeconds,
    pinchFromGestureStart,
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

test('calculateFittedSize fits portrait and landscape images completely inside the safe stage', () => {
    const portrait = calculateFittedSize(1200, 2400, 1000, 700);
    assert.equal(portrait.width, 350);
    assert.equal(portrait.height, 700);

    const landscape = calculateFittedSize(2000, 1000, 1000, 700);
    assert.equal(landscape.width, 1000);
    assert.equal(landscape.height, 500);

    const small = calculateFittedSize(400, 300, 1000, 700);
    assert.equal(small.width, 400);
    assert.equal(small.height, 300);
    assert.equal(small.scale, 1);
});

test('fitted portrait pan bounds can expose both top and bottom edges after zoom', () => {
    const fitted = calculateFittedSize(1200, 2400, 1000, 700);
    const bounds = calculatePanBounds(fitted.width, fitted.height, 1000, 700, 1.5);

    assert.equal(bounds.maxY, 175);
    assert.equal(fitted.height * 1.5 / 2 - bounds.maxY, 350);
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

test('meaningfulAttentionSeconds ignores flashes and rounds meaningful active time', () => {
    assert.equal(meaningfulAttentionSeconds(MIN_ATTENTION_MS - 1), null);
    assert.equal(meaningfulAttentionSeconds(MIN_ATTENTION_MS), 3);
    assert.equal(meaningfulAttentionSeconds(4499), 4);
    assert.equal(meaningfulAttentionSeconds(4500), 5);
    assert.equal(meaningfulAttentionSeconds(Number.NaN), null);
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

test('pinchFromGestureStart preserves a fixed anchor while scaling', () => {
    const state = { scale: 1, x: 0, y: 0 };
    const result = pinchFromGestureStart(state, 2, 100, 40, 100, 40);
    assert.ok(Math.abs(result.x + 100 * result.scale - 100) < 1e-10);
    assert.ok(Math.abs(result.y + 40 * result.scale - 40) < 1e-10);
});

test('pinchFromGestureStart preserves a translated midpoint while scaling', () => {
    const state = { scale: 1.5, x: 20, y: -10 };
    const result = pinchFromGestureStart(state, 3, 80, 30, 110, 55);
    const localX = (80 - state.x) / state.scale;
    const localY = (30 - state.y) / state.scale;
    assert.ok(Math.abs(result.x + localX * result.scale - 110) < 1e-10);
    assert.ok(Math.abs(result.y + localY * result.scale - 55) < 1e-10);
});

test('pinchFromGestureStart clamps minimum and maximum scales', () => {
    const state = { scale: 2, x: 12, y: -8 };
    for (const nextScale of [0, MIN_SCALE - 1]) {
        const result = pinchFromGestureStart(state, nextScale, 50, 20, 70, 45);
        const localX = (50 - state.x) / state.scale;
        const localY = (20 - state.y) / state.scale;
        assert.equal(result.scale, MIN_SCALE);
        assert.ok(Math.abs(result.x + localX * result.scale - 70) < 1e-10);
        assert.ok(Math.abs(result.y + localY * result.scale - 45) < 1e-10);
    }
    const result = pinchFromGestureStart(state, MAX_SCALE + 1, 50, 20, 70, 45);
    const localX = (50 - state.x) / state.scale;
    const localY = (20 - state.y) / state.scale;
    assert.equal(result.scale, MAX_SCALE);
    assert.ok(Math.abs(result.x + localX * result.scale - 70) < 1e-10);
    assert.ok(Math.abs(result.y + localY * result.scale - 45) < 1e-10);
});

test('pinchFromGestureStart translates a midpoint without changing scale', () => {
    const state = { scale: 2, x: 10, y: -5 };
    const result = pinchFromGestureStart(state, state.scale, 30, 25, 50, 70);
    assert.equal(result.scale, state.scale);
    assert.equal(result.x - state.x, 20);
    assert.equal(result.y - state.y, 45);
});

test('pinchFromGestureStart returns finite values', () => {
    const result = pinchFromGestureStart({ scale: 1.5, x: 2, y: -3 }, 2.5, 40, 20, 60, 50);
    assert.ok(Number.isFinite(result.scale));
    assert.ok(Number.isFinite(result.x));
    assert.ok(Number.isFinite(result.y));
});

test('pointer continuation uses the current pan state after pinch', () => {
    const source = readFileSync(new URL('../../resources/js/artwork-viewer.js', import.meta.url), 'utf8');
    const releaseStart = source.indexOf('const releasePointer');
    const releaseEnd = source.indexOf('stage.addEventListener(\'pointerup\'', releaseStart);
    const releaseBlock = source.slice(releaseStart, releaseEnd);
    assert.match(releaseBlock, /panX: state\.x/);
    assert.match(releaseBlock, /panY: state\.y/);
    assert.doesNotMatch(releaseBlock, /\.\.\.dragStart/);
});

test('viewer panning follows actual bounds instead of requiring an arbitrary zoom threshold', () => {
    const source = readFileSync(new URL('../../resources/js/artwork-viewer.js', import.meta.url), 'utf8');

    assert.match(source, /stage\.dataset\.viewerPannable = pannable \? 'true' : 'false'/);
    assert.match(source, /stage\.dataset\.viewerPannable === 'true'/);
    assert.doesNotMatch(source, /dragStart && state\.scale > 1/);
});

test('viewer fits the loaded original before reset and refits on resize', () => {
    const source = readFileSync(new URL('../../resources/js/artwork-viewer.js', import.meta.url), 'utf8');

    assert.match(source, /const fitImageToStage = \(\) =>/);
    assert.match(source, /image\.style\.maxWidth = 'none'/);
    assert.match(source, /image\.style\.maxHeight = 'none'/);
    assert.match(source, /fitImageToStage\(\);\n        resetState\(\);/);
    assert.match(source, /if \(!dialog\.open \|\| image\.hidden\) return;\n        fitImageToStage\(\);\n        updateTransform\(\);/);
});

test('viewer analytics use stable artwork keys and one bounded attention event', () => {
    const source = readFileSync(new URL('../../resources/js/artwork-viewer.js', import.meta.url), 'utf8');

    assert.match(source, /artwork_open', normalized\[start\]\.analyticsKey/);
    assert.match(source, /artwork_zoom_used', item\.analyticsKey/);
    assert.match(source, /artwork_attention', attentionKey, seconds/);
    assert.match(source, /visibilitychange/);
    assert.match(source, /pagehide/);
    assert.doesNotMatch(source, /artwork_open', normalized\[start\]\.title/);
    assert.doesNotMatch(source, /setInterval/);
});
