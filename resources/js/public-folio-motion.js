// The accepted original remains the first variant and the CSS fallback.
// The six typed lengths always describe complete, connected paper corners.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4.00rem', '3.80rem', '1.15rem', '.55rem', '.55rem'],
    ['1.75rem', '3.55rem', '3.20rem', '1.55rem', '.78rem', '.7rem'],
    ['.68rem', '4.75rem', '4.48rem', '.82rem', '.68rem', '.9rem'],
    ['1.42rem', '3.12rem', '3.42rem', '1.65rem', '.95rem', '.68rem'],
    ['.76rem', '4.38rem', '4.26rem', '1.37rem', '.8rem', '.74rem'],
    ['1.88rem', '3.85rem', '3.02rem', '.78rem', '.66rem', '.94rem'],
    ['.93rem', '4.85rem', '4.02rem', '1.82rem', '.9rem', '.67rem'],
    ['1.58rem', '3.38rem', '4.64rem', '.98rem', '.7rem', '.85rem'],
    ['.62rem', '4.12rem', '3.62rem', '1.49rem', '1rem', '.72rem'],
    ['1.3rem', '4.52rem', '4.28rem', '.72rem', '.73rem', '1rem'],
]);
export const FOLIO_PROPERTIES = Object.freeze([
    '--public-folio-left-top', '--public-folio-left-bottom',
    '--public-folio-right-top', '--public-folio-right-bottom',
    '--public-folio-left-width', '--public-folio-right-width',
]);
let variant = 0;
let snapshot = null;

export function chooseNextFolioVariant(current, random = Math.random) {
    const from = Number.isInteger(current) && current >= 0 && current < FOLIO_VARIANTS.length
        ? current : 0;
    const draw = Math.max(0, Math.min(1 - Number.EPSILON, Number(random()) || 0));
    const pick = Math.floor(draw * (FOLIO_VARIANTS.length - 1));
    return pick < from ? pick : pick + 1;
}

// Body replacement destroys CSS transitions, so capture the actual animated
// computed geometry before Livewire swaps it. This includes interrupted motion.
export function capturePublicFolio(root = document) {
    snapshot = null;
    const element = root.querySelector('.public-folio-decoration[data-folio-active]');
    if (!element) return false;
    const computed = getComputedStyle(element);
    const values = FOLIO_PROPERTIES.map((name) => computed.getPropertyValue(name).trim());
    if (values.some((value) => !value)) return false;
    snapshot = values;
    return true;
}

export function advancePublicFolio(root = document) {
    const element = root.querySelector('.public-folio-decoration');
    if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        snapshot = null;
        return false;
    }
    const start = snapshot ?? FOLIO_VARIANTS[variant];
    snapshot = null;
    variant = chooseNextFolioVariant(variant);
    const target = FOLIO_VARIANTS[variant];
    const from = {};
    const to = {};

    for (let i = 0; i < FOLIO_PROPERTIES.length; i += 1) {
        const property = FOLIO_PROPERTIES[i];
        from[property] = start[i];
        to[property] = target[i];
        element.style.setProperty(property, target[i]);
    }
    element.setAttribute('data-folio-active', '');
    element.dataset.folioVariant = String(variant + 1);

    // WAAPI explicitly interpolates on the NEW node; no persistent wrapper,
    // forced reflow, timers, polling, or transition on a detached element.
    if (typeof element.animate === 'function') {
        try {
            element.animate([from, to], {
                duration: 920,
                easing: 'cubic-bezier(.2, .65, .25, 1)',
            });
        } catch {
            // Keep the complete static target on unsupported browsers.
        }
    }
    return true;
}
