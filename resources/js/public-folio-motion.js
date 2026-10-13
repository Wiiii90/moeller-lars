// Variations of the accepted ORIGINAL folio contour. Both corners retain
// all three joined borders at all times, including during transitions.
// Values: left top / bottom, right top / bottom, left / right return width.
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
    '--public-folio-left-top',
    '--public-folio-left-bottom',
    '--public-folio-right-top',
    '--public-folio-right-bottom',
    '--public-folio-left-width',
    '--public-folio-right-width',
]);

export function chooseNextFolioVariant(current, random = Math.random) {
    const from = Number.isInteger(current) && current >= 0 && current < FOLIO_VARIANTS.length
        ? current : 0;
    const draw = Math.max(0, Math.min(1 - Number.EPSILON, Number(random()) || 0));
    const pick = Math.floor(draw * (FOLIO_VARIANTS.length - 1));
    return pick < from ? pick : pick + 1;
}

let variant = 0;
export function advancePublicFolio(root = document) {
    const element = root.querySelector('.public-folio-decoration');
    if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;

    // Only the FIRST actual SPA visit activates the overlay. Force its original
    // computed geometry before retargeting, so the new endpoint interpolates.
    if (!element.hasAttribute('data-folio-active')) {
        element.setAttribute('data-folio-active', '');
        element.getBoundingClientRect();
    }

    variant = chooseNextFolioVariant(variant);
    const values = FOLIO_VARIANTS[variant];
    FOLIO_PROPERTIES.forEach((name, index) => element.style.setProperty(name, values[index]));
    element.dataset.folioVariant = String(variant + 1);
    return true;
}
