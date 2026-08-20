import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { PAN_OVERSCROLL } from '../../resources/js/artwork-viewer.js';

test('viewer pan has no artificial black overscroll allowance', () => {
    assert.equal(PAN_OVERSCROLL, 0);
});

test('public submenus use the shared expanding navigation region and item-step overflow', () => {
    const source = readFileSync(new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8');

    assert.match(source, /data-navigation-submenu-region/);
    assert.match(source, /regionInner\.append\(controls\.submenu\)/);
    assert.match(source, /shiftByOneItem/);
    assert.match(source, /items\[index\]\.offsetLeft/);
    assert.doesNotMatch(source, /positionSubmenu/);
    assert.doesNotMatch(source, /--submenu-left/);
});

test('the full primary navigation cell activates its canonical link without stealing submenu toggle clicks', () => {
    const source = readFileSync(new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8');

    assert.match(source, /function initializeFullCellTargets\(scroller\)/);
    assert.match(source, /primary\.style\.cursor = 'pointer'/);
    assert.match(source, /event\.target\.closest\('a, button'\)/);
    assert.match(source, /link\.click\(\)/);
    assert.match(source, /initializeFullCellTargets\(scroller\)/);
});
