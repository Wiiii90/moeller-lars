export const MAX_PENDING_NOTIFICATIONS = 20;

const RUNTIME_KEY = '__adminNotificationTickerRuntime';

function runtime() {
    window[RUNTIME_KEY] ??= {
        queue: [],
        active: [],
        frame: null,
        lastFrameAt: null,
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

function cssNumber(name, fallback) {
    const raw = window.getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
    const value = Number.parseFloat(raw);

    return Number.isFinite(value) ? value : fallback;
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

function streamElements() {
    const root = ticker();
    const runway = root?.querySelector('[data-admin-notification-runway]');
    const stream = root?.querySelector('[data-admin-notification-stream]');

    if (
        !root
        || !(runway instanceof HTMLElement)
        || !(stream instanceof HTMLElement)
    ) {
        return null;
    }

    return { root, runway, stream };
}

function createMessageElement(notification, withSeparator) {
    const item = document.createElement('div');
    item.className = 'admin-notification-ticker__item';
    item.dataset.notificationId = notification.id;
    item.dataset.status = notification.status;

    if (withSeparator) {
        const separator = document.createElement('span');
        separator.className = 'admin-notification-ticker__separator';
        separator.setAttribute('aria-hidden', 'true');
        separator.textContent = '·';
        item.append(separator);
    }

    const title = document.createElement('strong');
    title.textContent = notification.title;
    item.append(title);

    if (notification.body !== '') {
        const body = document.createElement('span');
        body.className = 'admin-notification-ticker__item-body';
        body.textContent = notification.body;
        item.append(body);
    }

    return item;
}

function positionItem(item) {
    if (!(item.element instanceof HTMLElement)) return;

    item.element.style.transform = `translate3d(${item.x}px, -50%, 0)`;
}

function hydrateActiveItems(stream) {
    const state = runtime();

    stream.replaceChildren();

    state.active.forEach((item, index) => {
        const element = createMessageElement(item.notification, index > 0 || item.withSeparator);
        stream.append(element);
        item.element = element;
        item.width = element.getBoundingClientRect().width;
        positionItem(item);
    });
}

function spawnNext(runwayWidth) {
    const state = runtime();
    const elements = streamElements();

    if (!elements || state.queue.length === 0 || runwayWidth <= 0) {
        return false;
    }

    const notification = state.queue.shift();
    if (!notification) return false;

    const withSeparator = state.active.length > 0;
    const element = createMessageElement(notification, withSeparator);
    elements.stream.append(element);

    const item = {
        notification,
        element,
        x: runwayWidth,
        width: element.getBoundingClientRect().width,
        withSeparator,
    };

    state.active.push(item);
    positionItem(item);
    elements.root.classList.add('is-active');

    return true;
}

export function canReleaseFollowingNotification(itemRight, runwayWidth) {
    return Number(itemRight) <= Number(runwayWidth);
}

function releasePendingWhenReady(runwayWidth) {
    const state = runtime();

    if (state.queue.length === 0) return;

    if (state.active.length === 0) {
        spawnNext(runwayWidth);

        return;
    }

    const last = state.active[state.active.length - 1];

    if (canReleaseFollowingNotification(last.x + last.width, runwayWidth)) {
        spawnNext(runwayWidth);
    }
}

function removeExitedItems() {
    const state = runtime();
    const retained = [];

    state.active.forEach((item) => {
        if (item.x + item.width <= 0) {
            item.element?.remove();

            return;
        }

        retained.push(item);
    });

    state.active = retained;
}

function foldIfIdle() {
    const state = runtime();

    if (state.active.length > 0 || state.queue.length > 0) {
        return false;
    }

    const root = ticker();
    root?.classList.remove('is-active');
    state.lastFrameAt = null;

    return true;
}

function scrollSpeed() {
    return prefersReducedMotion()
        ? cssNumber('--admin-feedback-scroll-speed-reduced', 210)
        : cssNumber('--admin-feedback-scroll-speed', 280);
}

function tick(now) {
    const state = runtime();
    const elements = streamElements();

    if (!elements) {
        state.frame = window.requestAnimationFrame(tick);

        return;
    }

    const runwayWidth = elements.runway.clientWidth;

    if (runwayWidth <= 0) {
        state.frame = window.requestAnimationFrame(tick);

        return;
    }

    if (state.active.length === 0) {
        releasePendingWhenReady(runwayWidth);
    }

    if (state.lastFrameAt === null) {
        state.lastFrameAt = now;
    }

    const elapsed = Math.min(50, Math.max(0, now - state.lastFrameAt));
    state.lastFrameAt = now;
    const delta = scrollSpeed() * (elapsed / 1000);

    state.active.forEach((item) => {
        item.x -= delta;
        positionItem(item);
    });

    removeExitedItems();
    releasePendingWhenReady(runwayWidth);

    if (foldIfIdle()) {
        state.frame = null;

        return;
    }

    state.frame = window.requestAnimationFrame(tick);
}

function ensureTickerRunning() {
    const state = runtime();

    if (state.frame !== null) return;

    state.lastFrameAt = null;
    state.frame = window.requestAnimationFrame(tick);
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
        state.active.some((item) => item.notification.id === notification.id)
        || state.queue.some((queued) => queued.id === notification.id)
    ) {
        return;
    }

    if (!appendPendingNotification(state.queue, notification)) {
        return;
    }

    ticker()?.classList.add('is-active');
    ensureTickerRunning();
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

function dismissTicker() {
    const state = runtime();

    state.queue = [];
    state.active.forEach((item) => item.element?.remove());
    state.active = [];
    state.lastFrameAt = null;

    ticker()?.classList.remove('is-active');
}

function bindRoot(root) {
    const state = runtime();

    if (state.boundRoot === root) return;

    state.boundRoot = root;

    const stream = root.querySelector('[data-admin-notification-stream]');
    if (stream instanceof HTMLElement) {
        hydrateActiveItems(stream);
    }

    root.querySelector('[data-admin-notification-dismiss]')
        ?.addEventListener('click', dismissTicker);

    enqueueInitialFeedback(root);

    if (state.active.length > 0 || state.queue.length > 0) {
        root.classList.add('is-active');
        ensureTickerRunning();
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
