// The first variant is the accepted original Atelier sheet, never rewritten.
// Later variants vary only the heights of its FOUR connected, complete corners.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4.00rem', '3.80rem', '1.15rem'],
    ['1.36rem', '3.78rem', '3.55rem', '1.33rem'],
    ['.92rem', '4.20rem', '4.03rem', '1.02rem'],
    ['1.26rem', '3.62rem', '3.91rem', '1.31rem'],
    ['1.52rem', '4.11rem', '3.60rem', '.96rem'],
    ['1.01rem', '3.71rem', '4.20rem', '1.39rem'],
    ['1.43rem', '4.26rem', '3.73rem', '1.06rem'],
    ['.84rem', '3.90rem', '3.97rem', '1.25rem'],
    ['1.27rem', '4.14rem', '3.46rem', '.89rem'],
    ['1.08rem', '3.56rem', '4.15rem', '1.47rem'],
]);
const PROPERTIES = [
    '--public-folio-left-top',
    '--public-folio-left-bottom',
    '--public-folio-right-top',
    '--public-folio-right-bottom',
];

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
    if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // The fallback #content pseudo-edges remain authoritative until the
    // first REAL Livewire navigation. Start from their IDENTICAL geometry.
    if (!element.hasAttribute('data-folio-active')) {
        element.setAttribute('data-folio-active', '');
        // Commit the base style before changing targets, without timers.
        element.getBoundingClientRect();
    }
    variant = chooseNextFolioVariant(variant);
    const values = FOLIO_VARIANTS[variant];
    PROPERTIES.forEach((name, index) => element.style.setProperty(name, values[index]));
}
