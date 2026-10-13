function trackingConfiguration(root = document) {
    return root.querySelector?.('[data-matomo-tracking]') ?? null;
}

function trackingQueue() {
    if (!trackingConfiguration(document)) return null;
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
    const onClick = (event) => {
        const target = event.target.closest?.('[data-matomo-event-action]');
        if (!target) return;

        trackMatomoEvent(
            target.dataset.matomoEventCategory || 'Interaction',
            target.dataset.matomoEventAction,
            target.dataset.matomoEventName || null,
            null,
            root,
        );
    };
    root.addEventListener?.('click', onClick);

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
    if (viewTargets.length === 0 || typeof IntersectionObserver !== 'function') {
        return () => root.removeEventListener?.('click', onClick);
    }

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting || entry.intersectionRatio < 0.25) continue;
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
    }, { threshold: [0.25] });

    viewTargets.forEach((target) => observer.observe(target));
    return () => {
        root.removeEventListener?.('click', onClick);
        observer.disconnect();
    };
}

let trackingScriptUrl = null;
let disposeEvents = null;

export function initializeMatomoTracking(root = document) {
    disposeEvents?.();
    disposeEvents = null;
    const configuration = trackingConfiguration(document);
    if (!configuration) return;

    const baseUrl = configuration.dataset.matomoBaseUrl;
    const siteId = configuration.dataset.matomoSiteId;
    if (!baseUrl || !siteId) {
        throw new Error('Matomo tracking configuration is incomplete.');
    }

    const normalizedBaseUrl = `${baseUrl.replace(/\/$/, '')}/`;
    const queue = window._paq = window._paq || [];
    if (trackingScriptUrl === null) {
        queue.push(['setTrackerUrl', `${normalizedBaseUrl}matomo.php`]);
        queue.push(['setSiteId', siteId]);
        queue.push(['enableHeartBeatTimer', 15]);
        queue.push(['enableLinkTracking']);
        trackingScriptUrl = `${normalizedBaseUrl}matomo.js`;
        const script = document.createElement('script');
        script.async = true;
        script.src = trackingScriptUrl;
        document.head.appendChild(script);
    }
    queue.push(['setCustomUrl', window.location.href]);
    queue.push(['setDocumentTitle', document.title]);
    queue.push(['trackPageView']);
    disposeEvents = initializeDeclarativeEvents(root);
    return () => {
        disposeEvents?.();
        disposeEvents = null;
    };
}
