import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack, headerShellGeometry } from '../../resources/js/public-header.js';

test('Beside stacks according to one stable title slot, not the active navigation rail', () => {
    assert.equal(headerNeedsStack('outside', 640, 145, 320), false);
    assert.equal(headerNeedsStack('outside', 800, 145, 112), true);
    assert.equal(headerNeedsStack('outside', 640, 145, 160), true);
    assert.equal(headerNeedsStack('outside', 640, 145, 210), false);
});

test('Inside title fits its real title column and preserves 15rem for navigation', () => {
    assert.equal(headerNeedsStack('inside', 640, 130, 0), false);
    assert.equal(headerNeedsStack('inside', 360, 130, 0), true);
    assert.equal(headerNeedsStack('inside', 420, 70, 0), true);
    // Formerly returned false: a 320px title overflowed its 240px column.
    assert.equal(headerNeedsStack('inside', 640, 320, 0), true);
    assert.equal(headerNeedsStack('inside', 470, 100, 0), true);
    assert.equal(headerNeedsStack('inside', 540, 100, 0), false);
    assert.equal(headerNeedsStack('inside', 560, 320, 0), true);
    assert.equal(headerNeedsStack('below', 360, 130, 0), false);
    assert.equal(headerNeedsStack('above', 360, 130, 0), false);
});

test('responsive shell geometry comes from configured width and fixed viewport only', () => {
    const desktop = headerShellGeometry(1440, 800);
    const narrow = headerShellGeometry(520, 800);
    assert.deepEqual(desktop, { gutter: 24, menuWidth: 800, outsideSpace: 320 });
    assert.ok(Math.abs(narrow.gutter - 10.4) < 0.001);
    assert.ok(Math.abs(narrow.menuWidth - 499.2) < 0.001);
    assert.ok(Math.abs(narrow.outsideSpace - 10.4) < 0.001);
    assert.equal(headerShellGeometry(1440, 640).menuWidth, 640);
    // Nothing from the current title-stack class or rail geometry is an input.
    assert.deepEqual(headerShellGeometry(1440, 800), desktop);
});
