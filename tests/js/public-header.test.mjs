import test from 'node:test';
import assert from 'node:assert/strict';
import { headerBesideGeometry, headerGutter, headerNeedsStack } from '../../resources/js/public-header.js';

test('Beside follows actual Menu and paper edges, including fitted Artwork', () => {
    const beside = headerBesideGeometry(320, 320, 24);
    assert.deepEqual(beside, { offset: 296, railWidth: 296 });
    assert.equal(headerNeedsStack('outside', beside.railWidth, 145), false);
    assert.equal(headerNeedsStack('outside', beside.railWidth, 280), true);

    // Navigation is centered at x=560, but the wider Artwork paper starts
    // at x=260. Alignment must end at paper x=260, not Menu x=560.
    const expanded = headerBesideGeometry(560, 260, 24);
    assert.deepEqual(expanded, { offset: 536, railWidth: 236 });
    assert.equal(headerNeedsStack('outside', expanded.railWidth, 145), false);
    assert.equal(headerNeedsStack('outside', expanded.railWidth, 220), true);

    const tight = headerBesideGeometry(100, 100, 20);
    assert.deepEqual(tight, { offset: 80, railWidth: 80 });
    assert.equal(headerNeedsStack('outside', tight.railWidth, 145), true);

    // A shell filling the viewport leaves only the configured gutter.
    const absent = headerBesideGeometry(24, 24, 24);
    assert.deepEqual(absent, { offset: 0, railWidth: 0 });
    assert.equal(headerNeedsStack('outside', absent.railWidth, 25), true);

    const spacious = headerBesideGeometry(600, 600, 24);
    assert.equal(headerNeedsStack('outside', spacious.railWidth, 540), false);
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

test('responsive gutter mirrors the root CSS clamp; Menu width is read from CSS', () => {
    assert.equal(headerGutter(1440), 24);
    assert.ok(Math.abs(headerGutter(520) - 10.4) < 0.001);
    assert.equal(headerGutter(1000), 20);
    assert.equal(headerGutter(2000), 24);
    // Menu/paper rectangle inputs are independent of title alignment.
    assert.deepEqual(headerBesideGeometry(560, 560, 24), { offset: 536, railWidth: 536 });
});
