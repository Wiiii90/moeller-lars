// Ten curated configurations of the SAME two connected Atelier corners.
// Variant 1 is the accepted original, also rendered without JavaScript.
// Coordinates: left(top,bottom), right(top,bottom), left/right return width.
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

let variant = 0;
let snapshot = null;

export function chooseNextFolioVariant(current, random = Math.random) {
    const from = Number.isInteger(current) && current >= 0 && current < FOLIO_VARIANTS.length
        ? current : 0;
    const draw = Math.max(0, Math.min(1 - Number.EPSILON, Number(random()) || 0));
    const pick = Math.floor(draw * (FOLIO_VARIANTS.length - 1));
    return pick < from ? pick : pick + 1;
}

function edges(root) {
    const folio = root.querySelector('.public-folio-decoration');
    if (!folio) return null;
    const left = folio.querySelector('.public-folio-decoration__left');
    const right = folio.querySelector('.public-folio-decoration__right');
    return left && right ? { folio, left, right } : null;
}

function geometry(left, right) {
    const l = getComputedStyle(left);
    const r = getComputedStyle(right);
    return [l.top, l.bottom, r.top, r.bottom, l.width, r.width];
}

export function capturePublicFolio(root = document) {
    snapshot = null;
    const current = edges(root);
    if (!current?.folio.hasAttribute('data-folio-active')) return false;
    snapshot = geometry(current.left, current.right);
    return snapshot.every((value) => value !== '');
}

function frame(values, side) {
    return side === 'left'
        ? { top: values[0], bottom: values[1], width: values[4] }
        : { top: values[2], bottom: values[3], width: values[5] };
}

export function advancePublicFolio(root = document) {
    const current = edges(root);
    if (!current || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        snapshot = null;
        return false;
    }

    const start = snapshot ?? FOLIO_VARIANTS[variant];
    snapshot = null;
    variant = chooseNextFolioVariant(variant);
    const target = FOLIO_VARIANTS[variant];

    // Apply the exact destination first so unsupported WAAPI never distorts
    // the paper. The browser then interpolates ordinary layout properties
    // from the captured mid-animation state, on the NEW node.
    for (const [side, element] of [['left', current.left], ['right', current.right]]) {
        const first = frame(start, side);
        const last = frame(target, side);
        Object.assign(element.style, last);

        if (typeof element.animate === 'function') {
            try {
                element.animate([first, last], {
                    duration: 920,
                    easing: 'cubic-bezier(.2, .65, .25, 1)',
                });
            } catch {
                // Native static geometry remains available.
            }
        }
    }
    current.folio.setAttribute('data-folio-active', '');
    current.folio.dataset.folioVariant = String(variant + 1);
    return true;
}
