export const MAX_PENDING_NOTIFICATIONS = 20;

const RUNTIME_KEY = '__adminNotificationTickerRuntime';

function runtime() {
    window[RUNTIME_KEY] ??= {
        queue: [],
        current: null,
        timer: null,
        listenerRegistered: false,
        resizeListenerRegistered: false,
        layoutObserver: null,
        boundRoot: null,
        sequence: 0,
        phase: 'idle',
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

function cssNumber(name, fallback) {
    const raw = window.getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
    const value = Number.parseFloat(raw);

    return Number.isFinite(value) ? value : fallback;
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

export function tickerTravelDuration(
    runwayWidth,
    trackWidth,
    pixelsPerSecond = 112,
    minimum = 4200,
    maximum = 9000,
) {
    const distance = Math.max(0, Number(runwayWidth) || 0) + Math.max(0, Number(trackWidth) || 0);
    const speed = Math.max(1, Number(pixelsPerSecond) || 1);
    const calculated = (distance / speed) * 1000;

    return Math.max(minimum, Math.min(calculated, maximum));
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

    const localLeft = Math.max(0, left - topbarRect.left);
    const availableWidth = Math.max(0, right - left);

    root.style.setProperty('--admin-notification-ticker-left', `${localLeft}px`);
    root.style.setProperty('--admin-notification-ticker-width', `${availableWidth}px`);
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

function updatePendingIndicator() {
    const state = runtime();
    const root = ticker();
    const indicator = root?.querySelector('[data-admin-notification-pending]');

    if (!(indicator instanceof HTMLElement)) return;

    const count = state.queue.length;
    indicator.hidden = count === 0;
    indicator.textContent = count === 0 ? '' : `+${count}`;
}

function clearTimer() {
    const state = runtime();

    if (state.timer === null) return;

    window.clearTimeout(state.timer);
    state.timer = null;
}

function render(notification) {
    const root = ticker();
    if (!root) return false;

    const title = root.querySelector('[data-admin-notification-title]');
    const body = root.querySelector('[data-admin-notification-body]');
    const track = root.querySelector('[data-admin-notification-track]');

    if (title) title.textContent = notification.title;
    if (body) {
        body.textContent = notification.body;
        body.hidden = notification.body === '';
    }
    if (track instanceof HTMLElement) {
        track.style.removeProperty('transform');
    }

    root.dataset.status = notification.status;
    root.classList.remove('is-active', 'is-leaving');

    return true;
}

function fold() {
    const root = ticker();

    root?.classList.remove('is-active', 'is-leaving', 'is-traveling');
    if (root) delete root.dataset.status;
}

function completeCurrent({ dismissed = false } = {}) {
    const state = runtime();

    if (!state.current || state.phase === 'leaving') return;

    clearTimer();

    state.phase = 'leaving';

    const root = ticker();
    root?.classList.add('is-leaving');

    const fadeDuration = prefersReducedMotion() ? 0 : 140;

    state.timer = window.setTimeout(() => {
        fold();
        state.current = null;
        updatePendingIndicator();
        state.phase = 'gap';

        const gap = dismissed
            ? 120
            : cssDuration('--admin-feedback-gap-duration', 280);

        state.timer = window.setTimeout(() => {
            state.phase = 'idle';
            state.timer = null;
            showNext();
        }, gap);
    }, fadeDuration);
}

function startTickerTravel(root) {
    const state = runtime();
    const runway = root.querySelector('[data-admin-notification-runway]');
    const track = root.querySelector('[data-admin-notification-track]');

    if (!(runway instanceof HTMLElement) || !(track instanceof HTMLElement)) {
        completeCurrent();

        return;
    }

    const runwayWidth = runway.clientWidth;
    const trackWidth = track.scrollWidth;

    if (runwayWidth <= 0 || trackWidth <= 0) {
        completeCurrent();

        return;
    }

    const reducedMotion = prefersReducedMotion();
    const speed = reducedMotion
        ? cssNumber('--admin-feedback-scroll-speed-reduced', 210)
        : cssNumber('--admin-feedback-scroll-speed', 280);
    const minimum = reducedMotion
        ? cssDuration('--admin-feedback-scroll-min-duration-reduced', 3800)
        : cssDuration('--admin-feedback-scroll-min-duration', 3000);
    const maximum = reducedMotion
        ? cssDuration('--admin-feedback-scroll-max-duration-reduced', 7200)
        : cssDuration('--admin-feedback-scroll-max-duration', 5600);
    const duration = tickerTravelDuration(runwayWidth, trackWidth, speed, minimum, maximum);
    const startX = runwayWidth;
    const endX = -trackWidth;

    track.style.setProperty('--admin-ticker-start-x', `${startX}px`);
    track.style.setProperty('--admin-ticker-end-x', `${endX}px`);
    track.style.setProperty('--admin-ticker-travel-duration', `${duration}ms`);
    track.style.transform = `translate3d(${startX}px, -50%, 0)`;

    root.classList.remove('is-traveling');
    void track.offsetWidth;
    root.classList.add('is-active', 'is-traveling');
    state.phase = 'traveling';

    const finish = () => {
        if (state.phase !== 'traveling') return;

        completeCurrent();
    };

    track.onanimationend = finish;
    state.timer = window.setTimeout(finish, duration + 120);
}

function presentCurrent() {
    const state = runtime();
    const root = ticker();

    if (!root || !state.current || !render(state.current)) return;

    clearTimer();

    window.requestAnimationFrame(() => {
        const activeRoot = ticker();

        if (!activeRoot || !state.current) return;

        startTickerTravel(activeRoot);
    });
}

function showNext() {
    const state = runtime();

    if (state.current || state.phase !== 'idle' || state.queue.length === 0) return;

    if (!ticker()) return;

    state.current = state.queue.shift() ?? null;
    updatePendingIndicator();
    if (!state.current) return;

    presentCurrent();
}

export function appendPendingNotification(pending, notification, max = MAX_PENDING_NOTIFICATIONS) {
    if (pending.length >= max) {
        return false;
    }

    pending.push(notification);

    return true;
}

function enqueue(detail) {
    const state = runtime();
    const notification = normalize(detail);

    if (
        state.current?.id === notification.id
        || state.queue.some((queued) => queued.id === notification.id)
    ) {
        return;
    }

    if (!appendPendingNotification(state.queue, notification)) {
        return;
    }

    updatePendingIndicator();
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

function bindRoot(root) {
    const state = runtime();

    if (state.boundRoot === root) return;

    state.boundRoot = root;
    updatePendingIndicator();
    root.querySelector('[data-admin-notification-dismiss]')
        ?.addEventListener('click', () => completeCurrent({ dismissed: true }));

    enqueueInitialFeedback(root);

    if (state.current) {
        presentCurrent();
    } else {
        fold();
        showNext();
    }
}

export function initializeAdminNotificationTicker() {
    const state = runtime();

    if (!state.listenerRegistered) {
        window.addEventListener('admin-notification-ticker', (event) => enqueue(event.detail));
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
