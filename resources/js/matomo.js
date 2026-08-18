function trackingConfiguration(root = document) {
    return root.querySelector?.('[data-matomo-tracking]') ?? null;
}

function trackingQueue(root = document) {
    if (!trackingConfiguration(root)) return null;
    return window._paq = window._paq || [];
}

export function trackMatomoEvent(category, action, name = null, value = null, root = document) {
    const queue = trackingQueue(root);
    if (!queue || !category || !action) return false;

    const payload = ['trackEvent', category, action];
    if (name !== null && name !== '') payload.push(name);
    if (value !== null && Number.isFinite(Number(value))) {
        if (payload.length === 3) payload.push('');
        payload.push(Number(value));
    }
    queue.push(payload);

    return true;
}

function initializeDeclarativeEvents(root = document) {
    root.addEventListener?.('click', (event) => {
        const target = event.target.closest?.('[data-matomo-event-action]');
        if (!target) return;

        trackMatomoEvent(
            target.dataset.matomoEventCategory || 'Interaction',
            target.dataset.matomoEventAction,
            target.dataset.matomoEventName || null,
            null,
            root,
        );
    });

    root.querySelectorAll?.('[data-matomo-event-on-load]').forEach((target) => {
        trackMatomoEvent(
            target.dataset.matomoEventCategory || 'Interaction',
            target.dataset.matomoEventOnLoad,
            target.dataset.matomoEventName || null,
            null,
            root,
        );
    });

    const viewTargets = Array.from(root.querySelectorAll?.('[data-matomo-event-view]') ?? []);
    if (viewTargets.length === 0 || typeof IntersectionObserver !== 'function') return;

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting || entry.intersectionRatio < 0.5) continue;
            const target = entry.target;
            trackMatomoEvent(
                target.dataset.matomoEventCategory || 'Interaction',
                target.dataset.matomoEventView,
                target.dataset.matomoEventName || null,
                null,
                root,
            );
            observer.unobserve(target);
        }
    }, { threshold: [0.5] });

    viewTargets.forEach((target) => observer.observe(target));
}

export function initializeMatomoTracking(root = document) {
    const configuration = trackingConfiguration(root);
    if (!configuration) return;

    const baseUrl = configuration.dataset.matomoBaseUrl;
    const siteId = configuration.dataset.matomoSiteId;
    if (!baseUrl || !siteId) {
        throw new Error('Matomo tracking configuration is incomplete.');
    }

    const normalizedBaseUrl = `${baseUrl.replace(/\/$/, '')}/`;
    const queue = window._paq = window._paq || [];
    queue.push(['setTrackerUrl', `${normalizedBaseUrl}matomo.php`]);
    queue.push(['setSiteId', siteId]);
    queue.push(['enableHeartBeatTimer', 15]);
    queue.push(['trackPageView']);
    queue.push(['enableLinkTracking']);

    initializeDeclarativeEvents(root);

    const script = document.createElement('script');
    script.async = true;
    script.src = `${normalizedBaseUrl}matomo.js`;
    document.head.appendChild(script);
}
