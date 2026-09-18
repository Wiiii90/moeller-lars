import assert from 'node:assert/strict';
import test from 'node:test';

import { needsStableScrollbarGutter } from '../../resources/js/admin-modal-scroll.js';

test('reserves a modal gutter only for a classic pre-existing scrollbar', () => {
    assert.equal(needsStableScrollbarGutter(1280, 1263), true);
    assert.equal(needsStableScrollbarGutter(1280, 1280), false);
    assert.equal(needsStableScrollbarGutter(1280, 1290), false);
});
