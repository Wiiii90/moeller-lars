// One centrally owned, viewport-independent contour language.
// Variants are curated geometry, not page-type styling or random pixel noise.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.1rem', '4rem', '3.8rem', '1.15rem', '.35rem', '-1.1rem'],
    ['.8rem', '4.65rem', '4.25rem', '1.35rem', '.15rem', '-1.35rem'],
    ['1.65rem', '3.35rem', '3.25rem', '.75rem', '.6rem', '-.9rem'],
    ['.65rem', '5rem', '4.75rem', '1rem', '.3rem', '-1.65rem'],
    ['1.3rem', '3.8rem', '2.9rem', '1.55rem', '.55rem', '-1.25rem'],
    ['1.9rem', '4.25rem', '3.6rem', '.65rem', '.12rem', '-1.55rem'],
    ['.95rem', '5.25rem', '4.1rem', '1.8rem', '.7rem', '-.75rem'],
    ['1.5rem', '3.1rem', '5.1rem', '1.25rem', '.2rem', '-1.2rem'],
    ['.7rem', '4.3rem', '2.75rem', '.9rem', '.45rem', '-1.75rem'],
    ['1.25rem', '5.55rem', '3.3rem', '1.5rem', '.28rem', '-1rem'],
]);
const FOLIO_PROPERTIES = [
    '--public-folio-left-top',
    '--public-folio-left-bottom',
    '--public-folio-right-top',
    '--public-folio-right-bottom',
    '--public-folio-menu-left-top',
    '--public-folio-menu-right-top',
];

// Uniform draw from all variants except the one already reached.
// The next navigation is free to interrupt the CSS transition at any instant.
export function chooseNextFolioVariant(current, random = Math.random) {
    const from = Number.isInteger(current) && current >= 0 && current < FOLIO_VARIANTS.length
        ? current : 0;
    const value = Math.max(0, Math.min(1 - Number.EPSILON, random()));
    const offset = Math.floor(value * (FOLIO_VARIANTS.length - 1));
    return offset >= from ? offset + 1 : offset;
}

let currentVariant = null;
export function advancePublicFolio() {
    if (currentVariant === null) {
        // First response already uses CSS @property initial geometry.
        currentVariant = 0;
        return;
    }
    currentVariant = chooseNextFolioVariant(currentVariant);
    const dimensions = FOLIO_VARIANTS[currentVariant];
    for (let i = 0; i < FOLIO_PROPERTIES.length; i += 1) {
        document.documentElement.style.setProperty(FOLIO_PROPERTIES[i], dimensions[i]);
    }
}
