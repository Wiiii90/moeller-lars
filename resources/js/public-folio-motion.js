// Animate the ORIGINAL #content::before/::after contours directly.
// No duplicated corners, overlay nodes or new lines are ever created.
// Variant 1 is the accepted static geometry; every variant retains the
// original lateral offsets, stroke widths, bends and paper edge colors.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4.00rem', '3.80rem', '1.15rem'],
    ['1.30rem', '3.82rem', '3.95rem', '1.28rem'],
    ['.92rem', '4.21rem', '3.61rem', '1.04rem'],
    ['1.22rem', '4.12rem', '4.08rem', '.99rem'],
    ['.98rem', '3.78rem', '3.68rem', '1.32rem'],
    ['1.38rem', '4.23rem', '3.93rem', '1.10rem'],
    ['1.06rem', '3.89rem', '4.12rem', '1.24rem'],
    ['1.26rem', '4.16rem', '3.55rem', '.94rem'],
    ['.89rem', '4.08rem', '3.88rem', '1.37rem'],
    ['1.17rem', '3.74rem', '4.02rem', '1.06rem'],
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

function visibleCorners(content) {
    if (!content) return null;
    const left = getComputedStyle(content, '::before');
    const right = getComputedStyle(content, '::after');
    if (left.display === 'none' || right.display === 'none'
        || left.content === 'none' || right.content === 'none') return null;
    return [left, right];
}

// Read the exact in-flight coordinates before Livewire replaces #content.
// Computed pseudo-element styles include the current WAAPI interpolation.
export function capturePublicFolio(root = document) {
    snapshot = null;
    const corners = visibleCorners(root.querySelector('#content'));
    if (!corners) return false;
    const values = [corners[0].top, corners[0].bottom, corners[1].top, corners[1].bottom];
    if (values.some((value) => !value || value === 'auto')) return false;
    snapshot = values;
    return true;
}

export function advancePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const corners = visibleCorners(content);
    if (!corners || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        snapshot = null;
        return false;
    }

    const from = snapshot ?? FOLIO_VARIANTS[variant];
    snapshot = null;
    variant = chooseNextFolioVariant(variant);
    const to = FOLIO_VARIANTS[variant];

    // Keep target geometry in the existing CSS pseudos after the effect ends.
    // Width, left/right position, color and edge shadows never change.
    content.style.setProperty('--public-folio-left-top', to[0]);
    content.style.setProperty('--public-folio-left-bottom', to[1]);
    content.style.setProperty('--public-folio-right-top', to[2]);
    content.style.setProperty('--public-folio-right-bottom', to[3]);

    if (typeof KeyframeEffect !== 'function' || typeof Animation !== 'function') return true;

    for (const [pseudoElement, indices] of [
        ['::before', [0, 1]],
        ['::after', [2, 3]],
    ]) {
        try {
            const effect = new KeyframeEffect(content, [
                { top: from[indices[0]], bottom: from[indices[1]] },
                { top: to[indices[0]], bottom: to[indices[1]] },
            ], {
                pseudoElement,
                duration: 850,
                easing: 'cubic-bezier(.22, .62, .32, 1)',
            });
            new Animation(effect, document.timeline).play();
        } catch {
            // In unsupported browsers the accepted connected pseudos remain.
        }
    }
    return true;
}
