import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack, headerShellGeometry } from '../../resources/js/public-header.js';

test('Beside uses the external title rail rather than the Menu width', () => {
    const wide = headerShellGeometry(1440, 800);
    const wideBeside = (1440 - wide.menuWidth) / 2 - wide.gutter;
    assert.equal(wideBeside, 296);
    assert.equal(headerNeedsStack('outside', wideBeside, 145), false);
    assert.equal(headerNeedsStack('outside', wideBeside, 280), true);

    const constrained = headerShellGeometry(1000, 800);
    const constrainedBeside = (1000 - constrained.menuWidth) / 2 - constrained.gutter;
    assert.equal(constrainedBeside, 80);
    assert.equal(headerNeedsStack('outside', constrainedBeside, 145), true);

    // A 1920px Menu fills a 1440px viewport's available width: no exterior
    // title rail exists, even though the viewport itself is desktop-sized.
    const wideMenu = headerShellGeometry(1440, 1920);
    const absentBeside = Math.max(0, (1440 - wideMenu.menuWidth) / 2 - wideMenu.gutter);
    assert.equal(absentBeside, 0);
    assert.equal(headerNeedsStack('outside', absentBeside, 25), true);

    const spacious = headerShellGeometry(2000, 800);
    const spaciousBeside = (2000 - spacious.menuWidth) / 2 - spacious.gutter;
    assert.equal(headerNeedsStack('outside', spaciousBeside, 540), false);
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
