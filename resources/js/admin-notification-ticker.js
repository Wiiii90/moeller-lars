const queue = [];
let current = null;
let timeoutId = null;
let listenerRegistered = false;

function ticker() {
    return document.querySelector('[data-admin-notification-ticker]');
}

function durationFor(notification) {
    const configured = Number(notification?.duration);

    if (Number.isFinite(configured) && configured > 0) {
        return Math.max(2400, Math.min(configured, 8000));
    }

    return notification?.status === 'danger'
        ? 6200
        : notification?.status === 'warning'
            ? 5000
            : 3600;
}

function normalize(detail) {
    const source = detail?.notification ?? detail ?? {};
    const status = ['success', 'warning', 'danger', 'info'].includes(source.status)
        ? source.status
        : 'info';

    return {
        id: String(source.id ?? Date.now() + '-' + queue.length),
        title: String(source.title ?? 'Notification').trim(),
        body: source.body == null ? '' : String(source.body).trim(),
        status,
        duration: durationFor(source),
    };
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
    root.classList.add('is-active');

    return true;
}

function fold() {
    const root = ticker();
    root?.classList.remove('is-active');
    if (root) delete root.dataset.status;
}

function finishCurrent() {
    if (timeoutId !== null) {
        window.clearTimeout(timeoutId);
        timeoutId = null;
    }

    fold();
    current = null;
    window.setTimeout(showNext, 180);
}

function showNext() {
    if (current || queue.length === 0) return;

    current = queue.shift();
    if (!render(current)) {
        queue.unshift(current);
        current = null;
        window.requestAnimationFrame(showNext);
        return;
    }

    timeoutId = window.setTimeout(finishCurrent, current.duration);
}

function enqueue(detail) {
    const notification = normalize(detail);

    if (current?.id === notification.id || queue.some((queued) => queued.id === notification.id)) {
        return;
    }

    queue.push(notification);
    showNext();
}

export function initializeAdminNotificationTicker() {
    if (!listenerRegistered) {
        window.addEventListener('admin-notification-ticker', (event) => enqueue(event.detail));
        listenerRegistered = true;
    }

    const root = ticker();
    if (!root || root.dataset.tickerInitialized === 'true') {
        showNext();
        return;
    }

    root.dataset.tickerInitialized = 'true';
    root.querySelector('[data-admin-notification-dismiss]')?.addEventListener('click', finishCurrent);
    showNext();
}
