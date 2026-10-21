import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack, headerShellGeometry } from '../../resources/js/public-header.js';

test('Beside shares configured Content width with the navigation', () => {
    assert.equal(headerNeedsStack('outside', 800, 145), false);
    assert.equal(headerNeedsStack('outside', 640, 320), false);
    assert.equal(headerNeedsStack('outside', 560, 320), true);
    assert.equal(headerNeedsStack('outside', 420, 145), false);
    assert.equal(headerNeedsStack('outside', 390, 145), true);
    assert.equal(headerNeedsStack('outside', 800, 540), true);
});

test('Inside title fits its real title column and preserves 15rem for navigation', () => {
    assert.equal(headerNeedsStack('inside', 640, 130), false);
    assert.equal(headerNeedsStack('inside', 360, 130), true);
    assert.equal(headerNeedsStack('inside', 420, 70), true);
    // Formerly returned false: a 320px title overflowed its 240px column.
    assert.equal(headerNeedsStack('inside', 640, 320), true);
    assert.equal(headerNeedsStack('inside', 470, 100), true);
    assert.equal(headerNeedsStack('inside', 540, 100), false);
    assert.equal(headerNeedsStack('inside', 560, 320), true);
    assert.equal(headerNeedsStack('below', 360, 130), false);
    assert.equal(headerNeedsStack('above', 360, 130), false);
});

test('responsive shell geometry comes from configured width and fixed viewport only', () => {
    const desktop = headerShellGeometry(1440, 800);
    const narrow = headerShellGeometry(520, 800);
    assert.deepEqual(desktop, { gutter: 24, menuWidth: 800 });
    assert.ok(Math.abs(narrow.gutter - 10.4) < 0.001);
    assert.ok(Math.abs(narrow.menuWidth - 499.2) < 0.001);
    assert.equal(headerShellGeometry(1440, 640).menuWidth, 640);
    // Nothing from the current title-stack class or rail geometry is an input.
    assert.deepEqual(headerShellGeometry(1440, 800), desktop);
});
