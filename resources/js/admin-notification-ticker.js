export const MAX_PENDING_NOTIFICATIONS = 20;

const queue = [];
let current = null;
let phase = 'idle';
let phaseTimer = null;
let listenerRegistered = false;
let resizeListenerRegistered = false;
let layoutObserver = null;
let notificationSequence = 0;

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

function cssDuration(name, fallback) {
    const raw = window.getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    if (raw.endsWith('ms')) {
        const value = Number.parseFloat(raw);

        return Number.isFinite(value) ? value : fallback;
    }

    if (raw.endsWith('s')) {
        const value = Number.parseFloat(raw);

        return Number.isFinite(value) ? value * 1000 : fallback;
    }

    return fallback;
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

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function durationFor(notification) {
    const configured = Number(notification?.duration);

    if (Number.isFinite(configured) && configured > 0) {
        return Math.max(3200, Math.min(configured, 9000));
    }

    return notification?.status === 'danger'
        ? cssDuration('--admin-feedback-danger-hold', 7000)
        : notification?.status === 'warning'
            ? cssDuration('--admin-feedback-warning-hold', 6200)
            : cssDuration('--admin-feedback-default-hold', 5200);
}

function normalize(detail) {
    const source = detail?.notification ?? detail ?? {};
    const status = ['success', 'warning', 'danger', 'info'].includes(source.status)
        ? source.status
        : 'info';

    notificationSequence += 1;

    return {
        id: String(source.id ?? `admin-feedback-${notificationSequence}`),
        title: String(source.title ?? 'Notification').trim(),
        body: source.body == null ? '' : String(source.body).trim(),
        status,
        duration: durationFor(source),
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

    const localLeft = Math.max(0, left - topbarRect.left);
    const availableWidth = Math.max(0, right - left);

    root.style.setProperty('--admin-notification-ticker-left', `${localLeft}px`);
    root.style.setProperty('--admin-notification-ticker-width', `${availableWidth}px`);
}

function scheduleGeometrySync() {
    window.requestAnimationFrame(syncTickerGeometry);
}

function bindLayoutObserver() {
    layoutObserver?.disconnect();
    layoutObserver = null;

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

    layoutObserver = new ResizeObserver(scheduleGeometrySync);
    observed.forEach((element) => layoutObserver.observe(element));
    scheduleGeometrySync();
}

function render(notification) {
    const root = ticker();
    if (!root) return false;

    const title = root.querySelector('[data-admin-notification-title]');
    const body = root.querySelector('[data-admin-notification-body]');

    if (title) title.textContent = notification.title;
    if (body) {
        body.textContent = notification.body;
        body.hidden = notification.body === '';
    }

    root.dataset.status = notification.status;

    return true;
}

function clearPhaseTimer() {
    if (phaseTimer === null) return;

    window.clearTimeout(phaseTimer);
    phaseTimer = null;
}

function animationDuration(kind) {
    if (prefersReducedMotion()) return 0;

    return kind === 'enter'
        ? cssDuration('--admin-feedback-enter-duration', 320)
        : cssDuration('--admin-feedback-exit-duration', 240);
}

function fold() {
    const root = ticker();

    root?.classList.remove('is-active', 'is-entering', 'is-leaving');
    if (root) delete root.dataset.status;
}

function enterCurrent() {
    const root = ticker();
    if (!root || !current || !render(current)) return;

    clearPhaseTimer();
    phase = 'entering';

    root.classList.remove('is-leaving');
    root.classList.add('is-active', 'is-entering');

    phaseTimer = window.setTimeout(() => {
        const activeRoot = ticker();
        activeRoot?.classList.remove('is-entering');

        phase = 'visible';
        phaseTimer = window.setTimeout(finishCurrent, current?.duration ?? 5200);
    }, animationDuration('enter'));
}

function finishCurrent() {
    if (!current || phase === 'leaving') return;

    clearPhaseTimer();
    phase = 'leaving';

    const root = ticker();
    root?.classList.remove('is-entering');
    root?.classList.add('is-active', 'is-leaving');

    phaseTimer = window.setTimeout(() => {
        fold();
        current = null;
        phase = 'gap';

        phaseTimer = window.setTimeout(() => {
            phase = 'idle';
            showNext();
        }, prefersReducedMotion() ? 0 : 140);
    }, animationDuration('exit'));
}

function showNext() {
    if (current || phase !== 'idle' || queue.length === 0) return;

    current = queue.shift() ?? null;
    if (!current) return;

    if (!ticker()) {
        queue.unshift(current);
        current = null;
        return;
    }

    enterCurrent();
}

export function appendPendingNotification(pending, notification, max = MAX_PENDING_NOTIFICATIONS) {
    if (pending.length >= max) {
        return false;
    }

    pending.push(notification);

    return true;
}

function enqueue(detail) {
    const notification = normalize(detail);

    if (current?.id === notification.id || queue.some((queued) => queued.id === notification.id)) {
        return;
    }

    if (!appendPendingNotification(queue, notification)) {
        return;
    }

    showNext();
}

function enqueueInitialFeedback(root) {
    const source = root.querySelector('[data-admin-notification-initial]');
    if (!source || source.dataset.consumed === 'true') return;

    source.dataset.consumed = 'true';

    try {
        const messages = JSON.parse(source.textContent || '[]');
        if (Array.isArray(messages)) {
            messages.forEach(enqueue);
        }
    } catch {
        // Malformed queued feedback must not break the admin header.
    }
}

function restoreCurrentPresentation(root) {
    if (!current) {
        fold();
        showNext();
        return;
    }

    render(current);
    root.classList.add('is-active');

    if (phase === 'entering') {
        root.classList.add('is-entering');
    } else if (phase === 'leaving') {
        root.classList.add('is-leaving');
    }
}

export function initializeAdminNotificationTicker() {
    if (!listenerRegistered) {
        window.addEventListener('admin-notification-ticker', (event) => enqueue(event.detail));
        listenerRegistered = true;
    }

    if (!resizeListenerRegistered) {
        window.addEventListener('resize', scheduleGeometrySync, { passive: true });
        resizeListenerRegistered = true;
    }

    const root = ticker();
    if (!root) return;

    syncTickerGeometry();
    bindLayoutObserver();

    if (root.dataset.tickerInitialized !== 'true') {
        root.dataset.tickerInitialized = 'true';
        root.querySelector('[data-admin-notification-dismiss]')?.addEventListener('click', finishCurrent);
        enqueueInitialFeedback(root);
    }

    restoreCurrentPresentation(root);
}
