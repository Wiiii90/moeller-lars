import test from 'node:test';
import assert from 'node:assert/strict';
import {
    advanceNavigationWindow,
    fitNavigationWindow,
    navigationWindowParts,
    needsNavigationOverflow,
    restoreNavigationWindow,
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
    assert.deepEqual(advanceNavigationWindow([40, 50], [0, 1], 150, 1), [0, 1]);
    assert.throws(() => advanceNavigationWindow([100], [0], 200, 0), RangeError);
});

test('child navigation shares the cyclic complete-item contract', () => {
    const widths = [126, 186, 74, 124, 208];
    const available = 330;
    let visible = fitNavigationWindow(widths, available);

    assert.deepEqual(visible, [0, 1]);
    visible = advanceNavigationWindow(widths, visible, available, 1);
    assert.deepEqual(visible, [1, 2]);
    visible = advanceNavigationWindow(widths, visible, available, 1);
    assert.deepEqual(visible, [2, 3]);
    visible = advanceNavigationWindow(widths, visible, available, -1);
    assert.deepEqual(visible, [1, 2]);
});

test('navigation windows never duplicate items or exceed the available width', () => {
    for (let count = 2; count <= 9; count += 1) {
        const widths = Array.from({ length: count }, (_, index) => 30 + ((index * 41 + count * 17) % 130));

        for (let limit = 48; limit <= 420; limit += 19) {
            let visible = fitNavigationWindow(widths, limit);
            for (let step = 0; step < count * 3; step += 1) {
                const direction = step % 4 === 0 ? -1 : 1;
                visible = advanceNavigationWindow(widths, visible, limit, direction);
                assert.equal(visible.length, new Set(visible).size);
                assert.ok(visible.every((index) => index >= 0 && index < count));
                assert.ok(visible.length === 1
                    || visible.reduce((sum, index) => sum + widths[index], 0) <= limit);
                assert.ok(visible.every((index, position) =>
                    position === 0 || index === (visible[position - 1] + 1) % count));
            }
        }
    }
});

test('child level finds its own rail inside the breadcrumb content, not the main rail', () => {
    const node = (selector) => ({ matches: (query) => query === selector });
    const previous = node('button[data-direction="previous"]');
    const next = node('button[data-direction="next"]');
    const back = node('button[data-navigation-back]');
    const rail = node('nav[data-navigation-window-scroll]');
    const content = { ...node('[data-navigation-content]'), children: [back, rail] };
    const surface = { children: [previous, content, next] };
    assert.deepEqual(navigationWindowParts(surface), { rail, previous, next });
});

test('main controls never resolve to right-arrow buttons inside child submenus', () => {
    const node = (selector) => ({ matches: (query) => query === selector });
    const previous = node('button[data-direction="previous"]');
    const next = node('button[data-direction="next"]');
    const childNext = node('button[data-direction="next"]');
    const rail = {
        ...node('nav[data-navigation-window-scroll]'),
        children: [childNext],
    };
    const surface = {
        children: [previous, rail, next],
        // A descendant-wide query would incorrectly return the child arrow.
        querySelector: () => childNext,
    };

    const parts = navigationWindowParts(surface);
    assert.strictEqual(parts.rail, rail);
    assert.strictEqual(parts.previous, previous);
    assert.strictEqual(parts.next, next);
});

test('arrows are absent when all pages fit, including short menus', () => {
    const widths = [62, 81, 72];
    assert.equal(needsNavigationOverflow(widths, 320), false);
    assert.deepEqual(fitNavigationWindow(widths, 320), [0, 1, 2]);
    assert.equal(needsNavigationOverflow(widths, 215), false);
    assert.equal(needsNavigationOverflow(widths, 214), true);
    assert.equal(needsNavigationOverflow(widths, 180), true);
    assert.deepEqual(fitNavigationWindow(widths, 180), [0, 1]);
    assert.equal(needsNavigationOverflow([900], 180), false);
});

test('overflow restores smart refill on both sides after an oversized item drops out', () => {
    const widths = [180, 40, 40, 40, 40, 40];
    const available = 260;
    const first = fitNavigationWindow(widths, available);
    assert.deepEqual(first, [0, 1, 2]);

    // A single click beyond the long first page pulls three more short pages
    // from the right, rather than leaving most of the rail empty.
    const right = advanceNavigationWindow(widths, first, available, 1);
    assert.deepEqual(right, [1, 2, 3, 4, 5]);
    assert.deepEqual(advanceNavigationWindow(widths, right, available, -1), first);

    // Leftward movement likewise drops the long page and refills from the left.
    const fromMiddle = fitNavigationWindow(widths, available, 2);
    assert.deepEqual(fromMiddle, [2, 3, 4, 5]);
    assert.deepEqual(
        advanceNavigationWindow(widths, fromMiddle, available, -1),
        [1, 2, 3, 4, 5],
    );
});


test('selection reload preserves exact main window including its cyclic order', () => {
    const widths = [110, 60, 125, 80, 90, 115];
    const keys = ['home', 'prints', 'painting', 'cv', 'navigation', 'exhibitions'];
    const saved = { keys, visible: [2, 3, 4], width: 300 };

    // The current page changes, but its selected index stays in the same slot
    // instead of advancing repeatedly until that item reaches the right edge.
    assert.deepEqual(restoreNavigationWindow(widths, 300, 3, saved, keys), [2, 3, 4]);
    assert.deepEqual(restoreNavigationWindow(widths, 300, 4, saved, keys), [2, 3, 4]);
});

test('selected child retains its whole-item window on reload', () => {
    const widths = [110, 140, 105, 120, 100];
    const keys = ['first', 'second', 'third', 'fourth', 'fifth'];
    const saved = { keys, visible: [1, 2], width: 250 };
    assert.deepEqual(restoreNavigationWindow(widths, 250, 2, saved, keys), [1, 2]);
});

test('direct entries select the parent or child without advancing it to the right edge', () => {
    const widths = [110, 110, 110, 110, 110, 110];
    const keys = ['0', '1', '2', '3', '4', '5'];
    assert.deepEqual(restoreNavigationWindow(widths, 250, 4, null, keys), [4, 5]);
    assert.deepEqual(restoreNavigationWindow(widths, 250, 1, null, keys), [0, 1]);
});

test('stale or oversized handoffs do not override canonical menu ordering', () => {
    const widths = [130, 90, 130, 90, 130];
    const keys = ['a', 'b', 'c', 'd', 'e'];
    const saved = { keys, visible: [2, 3], width: 220 };

    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, saved, keys), [2, 3]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, saved, ['a', 'b', 'changed', 'd', 'e']), [3, 4]);
    assert.deepEqual(restoreNavigationWindow(widths, 170, 3, saved, keys), [2]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 4, saved, keys), [2, 3]);
    assert.deepEqual(restoreNavigationWindow(widths, 220, 3, {
        keys, visible: [2, 2], width: 220,
    }, keys), [3, 4]);

    // A menu fitting in its entirety never needs a stored subset.
    assert.deepEqual(restoreNavigationWindow([60, 50], 200, 1,
        { keys: ['a', 'b'], visible: [1], width: 200 }, ['a', 'b']), [0, 1]);
});


test('cyclic wraparound window survives a selected-item page load unchanged', () => {
    const keys = ['one', 'two', 'three', 'four', 'five', 'six'];
    const widths = [75, 75, 75, 75, 75, 75];
    const saved = { keys, width: 225, visible: [4, 5, 0] };
    assert.deepEqual(restoreNavigationWindow(widths, 225, 0, saved, keys), [4, 5, 0]);
    assert.deepEqual(restoreNavigationWindow(widths, 225, 5, saved, keys), [4, 5, 0]);
});


test('selection never moves an intact top-level window, even if its parent is hidden', () => {
    const widths = Array(8).fill(80);
    const keys = widths.map((_, index) => String(index));
    const windows = [
        [2, 3, 4],
        [0, 1, 2],
        [5, 6, 7],
        [6, 7, 0],
    ];

    for (const visible of windows) {
        for (const selected of [0, 1, 3, 5, 7]) {
            assert.deepEqual(restoreNavigationWindow(
                widths, 240, selected, { keys, visible, width: 240 }, keys,
            ), visible);
        }
    }
});

test('a long hidden parent does not displace visible neighbors on child selection', () => {
    const widths = [40, 40, 40, 180, 40, 40, 40, 40];
    const keys = widths.map((_, index) => String(index));
    const visible = [4, 5, 6, 7, 0, 1];

    assert.deepEqual(restoreNavigationWindow(
        widths, 260, 3, { keys, visible, width: 260 }, keys,
    ), visible);

    // Only explicit arrow activation is allowed to shift the stack.
    assert.deepEqual(
        advanceNavigationWindow(widths, visible, 260, -1),
        [3, 4, 5],
    );
});


test('scrollbar appearing or disappearing cannot make selection move a fitting window', () => {
    const widths = [70, 90, 65, 75, 90];
    const keys = widths.map((_, index) => String(index));
    const saved = { keys, visible: [1, 2, 3], width: 245 };

    // Neither of these selections is inside the saved window.
    assert.deepEqual(restoreNavigationWindow(widths, 240, 0, saved, keys), [1, 2, 3]);
    assert.deepEqual(restoreNavigationWindow(widths, 260, 4, saved, keys), [1, 2, 3]);

    // Actual insufficient width may evict an item; retain the left edge.
    assert.deepEqual(restoreNavigationWindow(widths, 165, 4, saved, keys), [1, 2]);
});
