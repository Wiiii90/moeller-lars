// Public navigation remains real server-rendered Blade routes. Delegate
// links that lack an initial wire:navigate attribute to Livewire's own API,
// rather than adding Alpine directives after Alpine has initialized them.
const OUTSIDE_PUBLIC_SITE = /^\/(?:admin|api|pulse|livewire|logout|media|storage|vendor|site-assets)(?:\/|$)/;
const OUTSIDE_PUBLIC_PREVIEW = /^\/preview\/(?:media|admin|logout)(?:\/|$)/;

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
        || OUTSIDE_PUBLIC_SITE.test(destination.pathname)
        || OUTSIDE_PUBLIC_PREVIEW.test(destination.pathname)
        || /\.(?:pdf|xml|txt|jpe?g|png|webp|mp4|webm|svg|zip)$/i.test(destination.pathname)) return false;

    // Preserve native intra-page anchor scrolling.
    return !(destination.pathname === current.pathname
        && destination.search === current.search && destination.hash);
}

export function initializePublicSpaLinks(root = document) {
    root.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey
            || event.metaKey || event.altKey || event.shiftKey) return;

        const link = event.target.closest?.('a[href]');
        if (!(link instanceof HTMLAnchorElement)
            || link.hasAttribute('wire:navigate')
            || link.hasAttribute('data-navigation-parent-link')
            || link.hasAttribute('data-public-full-load')
            || link.hasAttribute('download')
            || link.relList.contains('external')
            || !isPublicSpaDestination(link.getAttribute('href'), window.location.href, link.target)) return;

        const navigate = window.Livewire?.navigate;
        if (typeof navigate !== 'function') return; // Native link fallback
        event.preventDefault();
        navigate(link.href);
    });
}

export function applyPublicLayoutFromMeta() {
    const config = document.querySelector('meta[data-public-layout]');
    if (!config) return;
    // Exactly one current frame variant after Livewire navigation. The
    // rendering owner remains server-side; this merely replays meta state.
    const frameStyles = ['layered', 'continuous', 'contours', 'backing', 'plain'];
    const frameStyle = frameStyles.includes(config.dataset.publicLayoutFrameStyle)
        ? config.dataset.publicLayoutFrameStyle : 'layered';
    for (const candidate of frameStyles) {
        document.body.classList.toggle('public-site--frame-' + candidate, candidate === frameStyle);
    }
    // Update the nonce-authorized rule itself. Element.style becomes a style
    // attribute in Livewire's HTML snapshots and CSP rejects it on restore.
    const sheet = [...document.querySelectorAll('style[data-public-layout-style]')].at(-1)?.sheet;
    const rule = [...(sheet?.cssRules ?? [])].find((entry) => entry.selectorText === ':root');
    if (!rule) return;

    const declarations = {
        '--public-page': config.dataset.publicLayoutBackground || '#777',
        '--public-shell-max': config.dataset.publicLayoutShell,
        '--public-art-max': config.dataset.publicLayoutArt,
        '--public-content-padding': config.dataset.publicLayoutPadding,
        '--public-title-size': config.dataset.publicLayoutTitleSize,
        '--public-title-min-size': config.dataset.publicLayoutTitleMinSize,
        '--public-folio-left-top': config.dataset.folioLeftTop,
        '--public-folio-left-bottom': config.dataset.folioLeftBottom,
        '--public-folio-right-top': config.dataset.folioRightTop,
        '--public-folio-right-bottom': config.dataset.folioRightBottom,
        '--public-menu-left-bottom': config.dataset.folioMenuLeftBottom,
        '--public-menu-right-bottom': config.dataset.folioMenuRightBottom,
    };
    for (const [name, value] of Object.entries(declarations)) {
        if (value) rule.style.setProperty(name, value);
    }
    if (config.dataset.publicLayoutDividerColor) {
        rule.style.setProperty('--public-separator-color', config.dataset.publicLayoutDividerColor);
    } else {
        rule.style.removeProperty('--public-separator-color');
    }
    // SPA document swaps must replay the currently rendered divider paint.
    if (config.dataset.publicLayoutDividerGradient) {
        rule.style.setProperty('--public-separator-gradient', config.dataset.publicLayoutDividerGradient);
        rule.style.setProperty('--public-separator-paint', config.dataset.publicLayoutDividerGradient);
    } else {
        rule.style.removeProperty('--public-separator-gradient');
        rule.style.removeProperty('--public-separator-paint');
    }
    if (config.dataset.publicLayoutTitleColor) {
        rule.style.setProperty('--public-title-color', config.dataset.publicLayoutTitleColor);
    } else {
        rule.style.removeProperty('--public-title-color');
    }
}
