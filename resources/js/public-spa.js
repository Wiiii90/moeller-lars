// Livewire 4 owns SPA document visits. This module only declares which real
// server-rendered public links participate and synchronizes public shell tokens.
const OUTSIDE_PUBLIC_SITE = /^\/(?:admin|api|pulse|livewire|logout|media|storage|vendor)(?:\/|$)/;

export function isPublicSpaDestination(href, currentHref, target = '', isDownload = false) {
    if (isDownload || (target !== '' && target !== '_self')) return false;

    let destination;
    let current;
    try {
        current = new URL(currentHref);
        destination = new URL(href, current);
    } catch {
        return false;
    }

    if (!['http:', 'https:'].includes(destination.protocol)
        || destination.origin !== current.origin
        || OUTSIDE_PUBLIC_SITE.test(destination.pathname)) return false;

    // Same-document anchors should retain native browser scrolling.
    return !(destination.pathname === current.pathname
        && destination.search === current.search && destination.hash);
}

export function markPublicSpaLinks(root = document.body) {
    for (const link of root.querySelectorAll('a[href]')) {
        if (link.hasAttribute('download') || link.hasAttribute('data-public-full-load')
            || link.hasAttribute('data-navigation-parent-link')
            || link.relList?.contains('external')
            || !isPublicSpaDestination(link.getAttribute('href'), window.location.href, link.target)) {
            continue;
        }
        link.setAttribute('wire:navigate', '');
    }
}

export function applyPublicLayoutFromMeta() {
    const config = document.querySelector('meta[data-public-layout]');
    if (!config) return;

    // CSSOM property assignment is permitted by the strict inline-style CSP.
    // The server's author-approved values remain the only source of truth.
    const declarations = {
        '--public-page': config.dataset.publicLayoutBackground || '#777',
        '--public-shell-max': config.dataset.publicLayoutShell,
        '--public-art-max': config.dataset.publicLayoutArt,
        '--public-content-padding': config.dataset.publicLayoutPadding,
    };
    for (const [name, value] of Object.entries(declarations)) {
        if (value) document.documentElement.style.setProperty(name, value);
    }
}
