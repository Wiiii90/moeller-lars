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
    assert.match(source, /event\.pointerType === 'mouse'/);
    assert.doesNotMatch(source, /positionSubmenu/);
    assert.doesNotMatch(source, /--submenu-left/);
    assert.doesNotMatch(source, /data-navigation-submenu-toggle/);
    assert.doesNotMatch(source, /toggle\.addEventListener/);
});

test('the canonical link itself owns the full primary navigation cell', () => {
    const source = readFileSync(new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8');
    const css = readFileSync(new URL('../../resources/css/public-presentation.css', import.meta.url), 'utf8');

    assert.match(css, /\.site-navigation__primary > a\s*\{[^}]*width:\s*100%;/s);
    assert.doesNotMatch(source, /initializeFullCellTargets/);
    assert.doesNotMatch(source, /primary\.style\.cursor/);
    assert.doesNotMatch(source, /link\.click\(\)/);
});
