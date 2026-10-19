import test from 'node:test';
import assert from 'node:assert/strict';
import { headerNeedsStack } from '../../resources/js/public-header.js';

test('outside artist name uses actual menu margin rather than viewport breakpoint', () => {
    assert.equal(headerNeedsStack('outside', 640, 145, 320), false);
    assert.equal(headerNeedsStack('outside', 800, 145, 112), true);
    assert.equal(headerNeedsStack('outside', 640, 145, 160), true);
    assert.equal(headerNeedsStack('outside', 640, 145, 170), true);
    assert.equal(headerNeedsStack('outside', 640, 145, 210), false);
});

test('inside artist name preserves minimum real navigation rail', () => {
    assert.equal(headerNeedsStack('inside', 640, 130, 0), false);
    assert.equal(headerNeedsStack('inside', 360, 130, 0), true);
    assert.equal(headerNeedsStack('inside', 420, 70, 0), true);
    assert.equal(headerNeedsStack('inside', 640, 320, 0), false);
    // Right alignment needs a real 15rem title rail; smaller navs stack.
    assert.equal(headerNeedsStack('inside', 470, 100, 0), true);
    assert.equal(headerNeedsStack('inside', 540, 100, 0), false);
    assert.equal(headerNeedsStack('inside', 560, 320, 0), true);
    assert.equal(headerNeedsStack('below', 360, 130, 0), false);
    assert.equal(headerNeedsStack('above', 360, 130, 0), false);
});
