/**
 * One browser-session owner for presentation-only Admin tab preferences.
 * Views backed by Livewire #[Session] or navigable URL state do not use this.
 * Each consumer supplies its legitimate options; no layout or domain data
 * is persisted here.
 */
function restore(key, allowed, fallback, accept = () => true) {
    if (!key || !Array.isArray(allowed)) return fallback;

    try {
        const stored = window.sessionStorage.getItem(key);
        if (stored === null) return fallback;

        const option = allowed.find((value) => String(value) === stored);
        if (option !== undefined && accept(option)) return option;

        window.sessionStorage.removeItem(key);
    } catch {
        // Unavailable session storage is not a reason to break the editor.
    }

    return fallback;
}

function remember(key, value, allowed) {
    if (!key || !Array.isArray(allowed) || !allowed.includes(value)) return;

    try {
        window.sessionStorage.setItem(key, String(value));
    } catch {
        // The active UI state remains usable when browser storage is blocked.
    }
}

/**
 * Bind one Alpine state property to the existing tab preference.
 * The optional apply callback keeps consumers' normal selection side effects
 * (such as Gallery preview focus); other fields just receive the saved value.
 */
function connectAlpine(component, { property, key, allowed, accept, apply }) {
    const initial = component[property];
    const saved = restore(key, allowed, initial, accept);

    if (saved !== initial) {
        if (typeof apply === 'function') {
            apply(saved);
        } else {
            component[property] = saved;
        }
    }

    component.$watch(property, (value) => remember(key, value, allowed));
}

/** Keep existing Dashboard CSS radio tabs; share the same persistence owner. */
function connectRadio(root, key, name) {
    const radios = Array.from(root.querySelectorAll('input[type="radio"]'))
        .filter((input) => input.name === name);
    const allowed = radios.map((radio) => radio.value);
    const fallback = radios.find((radio) => radio.checked)?.value ?? allowed[0];
    const value = restore(key, allowed, fallback);

    for (const radio of radios) radio.checked = radio.value === value;

    root.addEventListener('change', (event) => {
        if (radios.includes(event.target)) {
            remember(key, event.target.value, allowed);
        }
    });
}

export const adminRememberedTabs = { connectAlpine, connectRadio };
