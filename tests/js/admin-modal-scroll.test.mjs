import assert from 'node:assert/strict';
import test from 'node:test';

import { hasDocumentVerticalOverflow } from '../../resources/js/admin-modal-scroll.js';

test('detects whether the document already needs vertical scrolling', () => {
    assert.equal(hasDocumentVerticalOverflow(2400, 900), true);
    assert.equal(hasDocumentVerticalOverflow(900, 900), false);
    assert.equal(hasDocumentVerticalOverflow(800, 900), false);
});
