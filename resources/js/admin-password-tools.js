export const GENERATED_SECRET_LENGTH = 28;

export const GENERATED_SECRET_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!#$%&()*+,-./:;<=>?@[]^_{|}~';

export function generateSecureSecret(length = GENERATED_SECRET_LENGTH, cryptoProvider = globalThis.crypto) {
    if (! Number.isInteger(length) || length < 1) {
        throw new TypeError('Generated value length must be a positive integer.');
    }

    if (! cryptoProvider?.getRandomValues) {
        throw new Error('A cryptographically secure random source is required.');
    }

    const alphabetLength = GENERATED_SECRET_ALPHABET.length;
    const acceptanceLimit = Math.floor(256 / alphabetLength) * alphabetLength;
    const buffer = new Uint8Array(Math.max(32, length * 2));
    let value = '';

    while (value.length < length) {
        cryptoProvider.getRandomValues(buffer);

        for (const byte of buffer) {
            if (byte >= acceptanceLimit) continue;

            value += GENERATED_SECRET_ALPHABET[byte % alphabetLength];

            if (value.length === length) break;
        }
    }

    return value;
}

function findScopedInput(scope, attribute, trigger) {
    const root = trigger?.closest?.('.fi-modal-window, form') ?? document;
    const inputs = root.querySelectorAll(`[${attribute}]`);

    for (const input of inputs) {
        if (input.getAttribute(attribute) === scope) return input;
    }

    return null;
}

function setInputValue(input, value) {
    const nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;

    if (nativeSetter) nativeSetter.call(input, value);
    else input.value = value;

    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

export function generateForScope(scope, trigger) {
    const primaryInput = findScopedInput(scope, 'data-admin-password-input', trigger);
    const confirmationInput = findScopedInput(scope, 'data-admin-password-confirmation', trigger);

    if (! primaryInput || ! confirmationInput) return;

    const generatedValue = generateSecureSecret();
    setInputValue(primaryInput, generatedValue);
    setInputValue(confirmationInput, generatedValue);
    primaryInput.focus();
}

export async function copyForScope(scope, trigger) {
    const primaryInput = findScopedInput(scope, 'data-admin-password-input', trigger);

    if (! primaryInput?.value) return;

    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(primaryInput.value);
        return;
    }

    primaryInput.focus();
    primaryInput.select();
}

if (typeof window !== 'undefined') {
    window.AdminPasswordTools = Object.freeze({
        generate: generateForScope,
        copy: copyForScope,
    });
}
