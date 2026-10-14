import test from 'node:test';
import assert from 'node:assert/strict';
import { revealLinkedLegalSection } from '../../resources/js/public-legal.js';

test('opens only a requested legal disclosure', () => {
    const element = { tagName: 'DETAILS', open: false };
    const root = { getElementById: id => id === 'legal-imprint' ? element : null };

    revealLinkedLegalSection(root, { hash: '#legal-imprint' });
    assert.equal(element.open, true);

    element.open = false;
    revealLinkedLegalSection(root, { hash: '#not-a-legal-fragment' });
    assert.equal(element.open, false);

    revealLinkedLegalSection(root, { hash: '#legal-copyright-ai' });
    assert.equal(element.open, false);
    revealLinkedLegalSection({ getElementById: () => null }, { hash: '#legal-imprint' });
});
