import test from 'node:test';
import assert from 'node:assert/strict';
import { revealLinkedLegalSection } from '../../resources/js/public-legal.js';

test('opens only a requested legal disclosure', () => {
    const element = { tagName: 'DETAILS', open: false };
    const root = { getElementById: id => id === 'rechtliches-impressum' ? element : null };

    revealLinkedLegalSection(root, { hash: '#rechtliches-impressum' });
    assert.equal(element.open, true);

    element.open = false;
    revealLinkedLegalSection(root, { hash: '#not-a-legal-fragment' });
    assert.equal(element.open, false);

    revealLinkedLegalSection(root, { hash: '#rechtliches-urheberrecht' });
    assert.equal(element.open, false);
    revealLinkedLegalSection({ getElementById: () => null }, { hash: '#rechtliches-impressum' });
});
