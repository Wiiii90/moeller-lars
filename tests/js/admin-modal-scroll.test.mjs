import assert from 'node:assert/strict';
import test from 'node:test';

import { hasClassicDocumentScrollbar } from '../../resources/js/admin-modal-scroll.js';

test('detects only a classic pre-existing document scrollbar', () => {
    assert.equal(hasClassicDocumentScrollbar(1280, 1263), true);
    assert.equal(hasClassicDocumentScrollbar(1280, 1280), false);
    assert.equal(hasClassicDocumentScrollbar(1280, 1290), false);
});
