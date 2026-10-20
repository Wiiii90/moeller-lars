import test from 'node:test';
import assert from 'node:assert/strict';
import {
    advanceNavigationWindow,
    fitNavigationWindow,
    navigationWindowParts,
    needsNavigationOverflow,
    restoreNavigationWindow,
} from '../../resources/js/public-navigation.js';

test('fits complete entries and reports actual navigation overflow', () => {
    for (const { widths, available, start = 0, visible } of [
        { widths: [60, 60, 95, 60], available: 190, visible: [0, 1] },
        { widths: [60, 60, 95, 60], available: 190, start: 2, visible: [2, 3] },
        { widths: [300, 35, 35], available: 90, visible: [0] },
        { widths: [60, 50], available: 200, visible: [0, 1] },
        { widths: [], available: 200, visible: [] },
    ]) {
        assert.deepEqual(fitNavigationWindow(widths, available, start), visible);
    }

    const widths = [62, 81, 72];
    for (const [available, overflow] of [[320, false], [215, false], [214, true], [180, true]]) {
        assert.equal(needsNavigationOverflow(widths, available), overflow);
    }
    assert.deepEqual(fitNavigationWindow(widths, 180), [0, 1]);
    assert.equal(needsNavigationOverflow([900], 180), false);
    assert.deepEqual(advanceNavigationWindow([], [], 200, 1), []);
    assert.deepEqual(advanceNavigationWindow([40, 50], [0, 1], 150, 1), [0, 1]);
    assert.throws(() => advanceNavigationWindow([100], [0], 200, 0), RangeError);
});

test('cycles complete navigation stacks and refills their space', () => {
    const equal = [40, 40, 40, 40];
    const first = fitNavigationWindow(equal, 120);
    assert.deepEqual(advanceNavigationWindow(equal, first, 120, -1), [3, 0, 1]);
    assert.deepEqual(advanceNavigationWindow(equal, [3, 0, 1], 120, 1), first);
    assert.deepEqual(fitNavigationWindow(equal, 120, 2), [2, 3, 0]);
    assert.deepEqual(advanceNavigationWindow(equal, [2, 3, 0], 120, 1), [3, 0, 1]);

    const variable = [40, 40, 40, 120, 40];
    assert.deepEqual(fitNavigationWindow(variable, 130), [0, 1, 2]);
    assert.deepEqual(advanceNavigationWindow(variable, [0, 1, 2], 130, 1), [3]);
    assert.deepEqual(advanceNavigationWindow(variable, [3], 130, 1), [4, 0, 1]);

    const children = [126, 186, 74, 124, 208];
    let visible = fitNavigationWindow(children, 330);
    assert.deepEqual(visible, [0, 1]);
    for (const [direction, expected] of [
        [1, [1, 2]], [1, [2, 3]], [-1, [1, 2]],
    ]) {
        visible = advanceNavigationWindow(children, visible, 330, direction);
        assert.deepEqual(visible, expected);
    }

    const overwide = [300, 35, 35];
    assert.deepEqual(advanceNavigationWindow(overwide, [2], 90, 1), [0]);
    assert.deepEqual(advanceNavigationWindow(overwide, [0], 90, 1), [1, 2]);

    const longFirst = [180, 40, 40, 40, 40, 40];
    const fit = fitNavigationWindow(longFirst, 260);
    const right = advanceNavigationWindow(longFirst, fit, 260, 1);
    assert.deepEqual(fit, [0, 1, 2]);
    assert.deepEqual(right, [1, 2, 3, 4, 5]);
    assert.deepEqual(advanceNavigationWindow(longFirst, right, 260, -1), fit);
    assert.deepEqual(advanceNavigationWindow(longFirst, [2, 3, 4, 5], 260, -1), [1, 2, 3, 4, 5]);
});

test('keeps navigation windows unique, bounded and cyclically ordered', () => {
    for (let count = 2; count <= 9; count += 1) {
        const widths = Array.from({ length: count }, (_, index) => 30 + ((index * 41 + count * 17) % 130));
        for (let available = 48; available <= 420; available += 19) {
            let visible = fitNavigationWindow(widths, available);
            for (let step = 0; step < count * 3; step += 1) {
                visible = advanceNavigationWindow(widths, visible, available, step % 4 === 0 ? -1 : 1);
                assert.equal(visible.length, new Set(visible).size);
                assert.ok(visible.every(index => index >= 0 && index < count));
                assert.ok(visible.length === 1 || visible.reduce((sum, index) => sum + widths[index], 0) <= available);
                assert.ok(visible.every((index, position) =>
                    position === 0 || index === (visible[position - 1] + 1) % count));
            }
        }
    }
});

test('resolves navigation controls within their own menu level', () => {
    const node = selector => ({ matches: query => query === selector });
    const previous = node('button[data-direction="previous"]');
    const next = node('button[data-direction="next"]');
    const back = node('button[data-navigation-back]');
    const rail = node('nav[data-navigation-window-scroll]');
    const content = { ...node('[data-navigation-content]'), children: [back, rail] };
    assert.deepEqual(navigationWindowParts({ children: [previous, content, next] }), { rail, previous, next });

    const childNext = node('button[data-direction="next"]');
    const nestedRail = { ...node('nav[data-navigation-window-scroll]'), children: [childNext] };
    const surface = { children: [previous, nestedRail, next], querySelector: () => childNext };
    assert.deepEqual(navigationWindowParts(surface), { rail: nestedRail, previous, next });
});

test('restores a saved navigation window without moving the current selection', () => {
    const cases = [
        { widths: [110, 60, 125, 80, 90, 115], keys: ['home', 'prints', 'painting', 'cv', 'navigation', 'exhibitions'], visible: [2, 3, 4], available: 300, selections: [3, 4] },
        { widths: [110, 140, 105, 120, 100], keys: ['first', 'second', 'third', 'fourth', 'fifth'], visible: [1, 2], available: 250, selections: [2] },
        { widths: Array(8).fill(80), keys: Array.from({ length: 8 }, (_, i) => String(i)), available: 240, windows: [[2, 3, 4], [0, 1, 2], [5, 6, 7], [6, 7, 0]], selections: [0, 1, 3, 5, 7] },
        { widths: Array(6).fill(75), keys: ['one', 'two', 'three', 'four', 'five', 'six'], visible: [4, 5, 0], available: 225, selections: [0, 5] },
        { widths: [40, 40, 40, 180, 40, 40, 40, 40], keys: Array.from({ length: 8 }, (_, i) => String(i)), visible: [4, 5, 6, 7, 0, 1], available: 260, selections: [3] },
    ];
    for (const { widths, keys, visible, windows = [visible], available, selections } of cases) {
        for (const window of windows) {
            for (const selected of selections) {
                assert.deepEqual(restoreNavigationWindow(widths, available, selected, {
                    keys, visible: window, width: available,
                }, keys), window);
            }
        }
    }

    const keys = Array.from({ length: 6 }, (_, i) => String(i));
    const widths = Array(6).fill(110);
    assert.deepEqual(restoreNavigationWindow(widths, 250, 4, null, keys), [4, 5]);
    assert.deepEqual(restoreNavigationWindow(widths, 250, 1, null, keys), [0, 1]);
});

test('rejects stale windows and resizes only when the actual space requires it', () => {
    const keys = ['a', 'b', 'c', 'd', 'e'];
    const widths = [130, 90, 130, 90, 130];
    const saved = { keys, visible: [2, 3], width: 220 };
    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, saved, keys), [2, 3]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, saved, ['a', 'b', 'changed', 'd', 'e']), [3, 4]);
    assert.deepEqual(restoreNavigationWindow(widths, 170, 3, saved, keys), [2]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 4, saved, keys), [2, 3]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, {
        keys, visible: [2, 2], width: 220,
    }, keys), [3, 4]);
    assert.deepEqual(restoreNavigationWindow([60, 50], 200, 1,
        { keys: ['a', 'b'], visible: [1], width: 200 }, ['a', 'b']), [0, 1]);

    const resizeKeys = Array.from({ length: 5 }, (_, i) => String(i));
    const resizeWidths = [70, 90, 65, 75, 90];
    const resizeSaved = { keys: resizeKeys, visible: [1, 2, 3], width: 245 };
    assert.deepEqual(restoreNavigationWindow(resizeWidths, 240, 0, resizeSaved, resizeKeys), [1, 2, 3]);
    assert.deepEqual(restoreNavigationWindow(resizeWidths, 260, 4, resizeSaved, resizeKeys), [1, 2, 3]);
    assert.deepEqual(restoreNavigationWindow(resizeWidths, 165, 4, resizeSaved, resizeKeys), [1, 2]);

    const longKeys = Array.from({ length: 8 }, (_, i) => String(i));
    const longWidths = [40, 40, 40, 180, 40, 40, 40, 40];
    assert.deepEqual(advanceNavigationWindow(longWidths, [4, 5, 6, 7, 0, 1], 260, -1), [3, 4, 5]);
});
