// The originally approved Atelier corner is variant 1 and remains the
// no-motion fallback. Both edges stay OUTSIDE the paper at every step:
// their horizontal returns can never exceed the initial .55rem depth.
// Only small top/bottom offsets and shorter returns are interpolated.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4.00rem', '3.80rem', '1.15rem', '.55rem', '.55rem'],
    ['1.30rem', '3.82rem', '3.95rem', '1.28rem', '.47rem', '.51rem'],
    ['.92rem', '4.21rem', '3.61rem', '1.04rem', '.53rem', '.42rem'],
    ['1.22rem', '4.12rem', '4.08rem', '.99rem', '.44rem', '.49rem'],
    ['.98rem', '3.78rem', '3.68rem', '1.32rem', '.51rem', '.55rem'],
    ['1.38rem', '4.23rem', '3.93rem', '1.10rem', '.40rem', '.46rem'],
    ['1.06rem', '3.89rem', '4.12rem', '1.24rem', '.55rem', '.43rem'],
    ['1.26rem', '4.16rem', '3.55rem', '.94rem', '.46rem', '.52rem'],
    ['.89rem', '4.08rem', '3.88rem', '1.37rem', '.50rem', '.45rem'],
    ['1.17rem', '3.74rem', '4.02rem', '1.06rem', '.42rem', '.54rem'],
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

// Capture the rendered intermediate state of a previous transition BEFORE
// Livewire swaps the body. No stored timers, router, or persistent DOM.
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

    for (const [side, element] of [['left', current.left], ['right', current.right]]) {
        const first = frame(start, side);
        const last = frame(target, side);
        Object.assign(element.style, last);

        if (typeof element.animate === 'function') {
            try {
                element.animate([first, last], {
                    duration: 850,
                    easing: 'cubic-bezier(.22, .62, .32, 1)',
                });
            } catch {
                // Unsupported WAAPI keeps complete, static corner strokes.
            }
        }
    }
    current.folio.setAttribute('data-folio-active', '');
    return true;
}
