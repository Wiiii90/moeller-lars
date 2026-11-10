import test from 'node:test';
import assert from 'node:assert/strict';
import { headerBesideGeometry, headerNeedsStack, headerShellGeometry } from '../../resources/js/public-header.js';

test('Beside has a real exterior alignment rail, including expanded Artwork paper', () => {
    const wide = headerShellGeometry(1440, 800);
    const beside = headerBesideGeometry(1440, wide.menuWidth, wide.menuWidth, wide.gutter);
    assert.deepEqual(beside, { offset: 296, railWidth: 296 });
    assert.equal(headerNeedsStack('outside', beside.railWidth, 145), false);
    assert.equal(headerNeedsStack('outside', beside.railWidth, 280), true);

    const expanded = headerBesideGeometry(1920, 800, 1400, 24);
    assert.deepEqual(expanded, { offset: 536, railWidth: 236 });
    // The title starts at the viewport gutter but cannot overlap a larger
    // Artwork Menu paper, even though its navigation still occupies 800px.
    assert.equal(headerNeedsStack('outside', expanded.railWidth, 145), false);
    assert.equal(headerNeedsStack('outside', expanded.railWidth, 220), true);

    const constrained = headerShellGeometry(1000, 800);
    const tight = headerBesideGeometry(1000, constrained.menuWidth, constrained.menuWidth, constrained.gutter);
    assert.deepEqual(tight, { offset: 80, railWidth: 80 });
    assert.equal(headerNeedsStack('outside', tight.railWidth, 145), true);

    // A wide configured Menu can exhaust the exterior rail entirely.
    const wideMenu = headerShellGeometry(1440, 1920);
    const absent = headerBesideGeometry(1440, wideMenu.menuWidth, wideMenu.menuWidth, wideMenu.gutter);
    assert.equal(absent.railWidth, 0);
    assert.equal(headerNeedsStack('outside', absent.railWidth, 25), true);

    const spacious = headerShellGeometry(2000, 800);
    const spaciousBeside = headerBesideGeometry(2000, spacious.menuWidth, spacious.menuWidth, spacious.gutter);
    assert.equal(headerNeedsStack('outside', spaciousBeside.railWidth, 540), false);
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
