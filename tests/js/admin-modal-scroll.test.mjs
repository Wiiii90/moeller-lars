import assert from 'node:assert/strict';
import test from 'node:test';

import { isDocumentScrollKey } from '../../resources/js/admin-modal-scroll.js';

test('recognizes document-scrolling keyboard keys', () => {
    for (const key of ['ArrowDown', 'ArrowUp', 'End', 'Home', 'PageDown', 'PageUp', ' ']) {
        assert.equal(isDocumentScrollKey(key), true);
    }

    for (const key of ['Enter', 'Escape', 'Tab', 'a']) {
        assert.equal(isDocumentScrollKey(key), false);
    }
});
