const RUNTIME_KEY = '__adminHeaderFeedbackRuntime';

function runtime() {
    window[RUNTIME_KEY] ??= {
        current: null,
        additionalCount: 0,
        listenerRegistered: false,
        boundRoot: null,
    };

    return window[RUNTIME_KEY];
}

function root() {
    return document.querySelector('[data-admin-header-feedback]');
}

function normalize(detail) {
    const source = detail?.notification ?? detail ?? {};
    const status = ['success', 'warning', 'danger', 'info'].includes(source.status)
        ? source.status
        : 'info';

    return {
        title: String(source.title ?? 'Notification').trim(),
        body: source.body == null ? '' : String(source.body).trim(),
        status,
    };
}

function render() {
    const state = runtime();
    const element = root();
    const title = element?.querySelector('[data-admin-header-feedback-title]');
    const body = element?.querySelector('[data-admin-header-feedback-body]');
    const counter = element?.querySelector('[data-admin-header-feedback-counter]');

    if (
        !element
        || !(title instanceof HTMLElement)
        || !(body instanceof HTMLElement)
        || !(counter instanceof HTMLElement)
    ) {
        return;
    }

    if (state.current === null) {
        title.textContent = '';
        body.textContent = '';
        body.hidden = true;
        counter.textContent = '';
        counter.hidden = true;
        element.dataset.status = 'info';

        return;
    }

    title.textContent = state.current.title;
    body.textContent = state.current.body;
    body.hidden = state.current.body === '';
    counter.textContent = state.additionalCount > 0 ? `+${state.additionalCount}` : '';
    counter.hidden = state.additionalCount === 0;
    element.dataset.status = state.current.status;
}

function receive(detail) {
    const state = runtime();

    if (state.current !== null) {
        state.additionalCount += 1;
    }

    state.current = normalize(detail);
    render();
}

function receiveInitialFeedback(element) {
    const source = element.querySelector('[data-admin-header-feedback-initial]');
    if (!source || source.dataset.consumed === 'true') return;

    source.dataset.consumed = 'true';

    try {
        const messages = JSON.parse(source.textContent || '[]');

        if (Array.isArray(messages)) {
            messages.forEach(receive);
        }
    } catch {
        // Malformed queued feedback must not break the admin header.
    }
}

export function initializeAdminHeaderFeedback() {
    const state = runtime();

    if (!state.listenerRegistered) {
        window.addEventListener('admin-header-feedback', (event) => receive(event.detail));
        state.listenerRegistered = true;
    }

    const element = root();
    if (!element) return;

    if (state.boundRoot !== element) {
        state.boundRoot = element;
        receiveInitialFeedback(element);
    }

    render();
}
