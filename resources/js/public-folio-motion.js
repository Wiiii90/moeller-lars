// Hand-composed variations of the SAME external paper corner contours.
// Each array: top/bottom of left edge, top/bottom of right edge.
// The pair of return strokes is always painted together with its side.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4.00rem', '3.80rem', '1.15rem'],
    ['1.50rem', '3.65rem', '3.35rem', '1.45rem'],
    ['.92rem', '4.45rem', '4.15rem', '.95rem'],
    ['1.25rem', '3.55rem', '3.65rem', '1.32rem'],
    ['1.65rem', '4.10rem', '3.30rem', '.86rem'],
    ['1.02rem', '4.30rem', '4.45rem', '1.12rem'],
    ['1.38rem', '3.82rem', '3.98rem', '1.53rem'],
    ['.82rem', '4.18rem', '3.55rem', '1.22rem'],
    ['1.58rem', '3.42rem', '4.05rem', '.92rem'],
    ['1.18rem', '4.48rem', '3.24rem', '1.36rem'],
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
    const candidate = Math.min(FOLIO_VARIANTS.length - 2, Math.max(0,
        Math.floor(random() * (FOLIO_VARIANTS.length - 1)),
    ));
    return candidate < from ? candidate : candidate + 1;
}

let variant = 0;
export function advancePublicFolio(root = document) {
    const element = root.querySelector('.public-folio-decoration');
    if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    variant = chooseNextFolioVariant(variant);
    const values = FOLIO_VARIANTS[variant];
    // This is the persisted DOM node, not the new document's replacement.
    // The CSS engine retargets an interrupted typed-property transition from
    // its CURRENT rendered value: no animation timers or frame loops.
    PROPERTIES.forEach((name, index) => element.style.setProperty(name, values[index]));
}
