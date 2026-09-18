import test from 'node:test';
import assert from 'node:assert/strict';

import {
    GENERATED_SECRET_ALPHABET,
    GENERATED_SECRET_LENGTH,
    generateSecureSecret,
} from '../../resources/js/admin-password-tools.js';

test('generates a bounded password-manager friendly secret', () => {
    const value = generateSecureSecret();

    assert.equal(value.length, GENERATED_SECRET_LENGTH);

    for (const character of value) {
        assert.ok(GENERATED_SECRET_ALPHABET.includes(character));
    }
});

test('rejects random bytes outside the unbiased alphabet range', () => {
    let calls = 0;
    const cryptoProvider = {
        getRandomValues(buffer) {
            calls += 1;
            buffer.fill(calls === 1 ? 255 : 0);

            return buffer;
        },
    };

    const value = generateSecureSecret(4, cryptoProvider);

    assert.equal(value, GENERATED_SECRET_ALPHABET[0].repeat(4));
    assert.equal(calls, 2);
});
