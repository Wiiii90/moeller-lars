export function initializeMatomoTracking() {
    const configuration = document.querySelector('[data-matomo-tracking]');
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
    queue.push(['trackPageView']);
    queue.push(['enableLinkTracking']);

    const script = document.createElement('script');
    script.async = true;
    script.src = `${normalizedBaseUrl}matomo.js`;
    document.head.appendChild(script);
}
