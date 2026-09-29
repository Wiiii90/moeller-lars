const RUNTIME_KEY = '__adminNotificationTickerRuntime';

function runtime() {
    window[RUNTIME_KEY] ??= {
        current: null,
        additionalCount: 0,
        listenerRegistered: false,
        resizeListenerRegistered: false,
        layoutObserver: null,
        boundRoot: null,
        sequence: 0,
    };

    return window[RUNTIME_KEY];
}

function ticker() {
    return document.querySelector('[data-admin-notification-ticker]');
}

function visibleElement(selector) {
    return [...document.querySelectorAll(selector)].find((element) => {
        if (!(element instanceof HTMLElement)) return false;

        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);

        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && rect.width > 0
            && rect.height > 0;
    }) ?? null;
}

function cssLength(name, fallback) {
    const raw = window.getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    if (raw.endsWith('rem')) {
        const value = Number.parseFloat(raw);
        const rootSize = Number.parseFloat(window.getComputedStyle(document.documentElement).fontSize);

        return Number.isFinite(value) && Number.isFinite(rootSize)
            ? value * rootSize
            : fallback;
    }

    if (raw.endsWith('px')) {
        const value = Number.parseFloat(raw);

        return Number.isFinite(value) ? value : fallback;
    }

    return fallback;
}

function normalize(detail) {
    const state = runtime();
    const source = detail?.notification ?? detail ?? {};
    const status = ['success', 'warning', 'danger', 'info'].includes(source.status)
        ? source.status
        : 'info';

    state.sequence += 1;

    return {
        id: String(source.id ?? `admin-feedback-${state.sequence}`),
        title: String(source.title ?? 'Notification').trim(),
        body: source.body == null ? '' : String(source.body).trim(),
        status,
    };
}

function contentBounds() {
    const workspace = visibleElement('.admin-workspace');
    if (workspace) {
        return workspace.getBoundingClientRect();
    }

    const main = visibleElement('.fi-main');
    if (!main) return null;

    const rect = main.getBoundingClientRect();
    const style = window.getComputedStyle(main);
    const paddingLeft = Number.parseFloat(style.paddingLeft) || 0;
    const paddingRight = Number.parseFloat(style.paddingRight) || 0;

    return {
        left: rect.left + paddingLeft,
        right: rect.right - paddingRight,
        width: Math.max(0, rect.width - paddingLeft - paddingRight),
    };
}

function syncTickerGeometry() {
    const root = ticker();
    const topbar = root?.closest('.fi-topbar');

    if (!root || !(topbar instanceof HTMLElement)) return;

    const content = contentBounds();
    if (!content) return;

    const topbarRect = topbar.getBoundingClientRect();
    const edgeGap = cssLength('--admin-header-feedback-edge-gap', 12);
    let left = content.left;
    let right = content.right;

    const burger = visibleElement('.fi-topbar-open-sidebar-btn, .fi-topbar-close-sidebar-btn');
    const userMenu = visibleElement('.fi-user-menu-trigger');

    if (burger) {
        left = Math.max(left, burger.getBoundingClientRect().right + edgeGap);
    }

    if (userMenu) {
        right = Math.min(right, userMenu.getBoundingClientRect().left - edgeGap);
    }

    root.style.setProperty(
        '--admin-notification-ticker-left',
        `${Math.max(0, left - topbarRect.left)}px`,
    );
    root.style.setProperty(
        '--admin-notification-ticker-width',
        `${Math.max(0, right - left)}px`,
    );
}

function scheduleGeometrySync() {
    window.requestAnimationFrame(syncTickerGeometry);
}

function bindLayoutObserver() {
    const state = runtime();

    state.layoutObserver?.disconnect();
    state.layoutObserver = null;

    if (typeof ResizeObserver === 'undefined') {
        scheduleGeometrySync();
        return;
    }

    const observed = [
        ticker()?.closest('.fi-topbar'),
        visibleElement('.admin-workspace'),
        visibleElement('.fi-main'),
        visibleElement('.fi-user-menu-trigger'),
        visibleElement('.fi-topbar-open-sidebar-btn, .fi-topbar-close-sidebar-btn'),
    ].filter((element) => element instanceof HTMLElement);

    if (observed.length === 0) return;

    state.layoutObserver = new ResizeObserver(scheduleGeometrySync);
    observed.forEach((element) => state.layoutObserver.observe(element));
    scheduleGeometrySync();
}

function render() {
    const state = runtime();
    const root = ticker();
    const title = root?.querySelector('[data-admin-notification-title]');
    const body = root?.querySelector('[data-admin-notification-body]');
    const counter = root?.querySelector('[data-admin-notification-counter]');

    if (
        !root
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
        root.dataset.status = 'info';

        return;
    }

    title.textContent = state.current.title;
    body.textContent = state.current.body;
    body.hidden = state.current.body === '';
    counter.textContent = state.additionalCount > 0 ? `+${state.additionalCount}` : '';
    counter.hidden = state.additionalCount === 0;
    root.dataset.status = state.current.status;
}

export function nextStaticFeedbackState(current, additionalCount, notification) {
    return {
        current: notification,
        additionalCount: current === null ? additionalCount : additionalCount + 1,
    };
}

function receive(detail) {
    const state = runtime();
    const notification = normalize(detail);
    const next = nextStaticFeedbackState(state.current, state.additionalCount, notification);

    state.current = next.current;
    state.additionalCount = next.additionalCount;
    render();
}

function receiveInitialFeedback(root) {
    const source = root.querySelector('[data-admin-notification-initial]');
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

function bindRoot(root) {
    const state = runtime();

    if (state.boundRoot === root) return;

    state.boundRoot = root;
    receiveInitialFeedback(root);
    render();
}

export function initializeAdminNotificationTicker() {
    const state = runtime();

    if (!state.listenerRegistered) {
        window.addEventListener('admin-notification-ticker', (event) => receive(event.detail));
        state.listenerRegistered = true;
    }

    if (!state.resizeListenerRegistered) {
        window.addEventListener('resize', scheduleGeometrySync, { passive: true });
        state.resizeListenerRegistered = true;
    }

    const root = ticker();
    if (!root) return;

    syncTickerGeometry();
    bindLayoutObserver();
    bindRoot(root);
}
