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

// Debug animation is ONLY an explicit opt-in on loopback hosts. It persists
// within the tab while navigating, but never disables accessibility in prod.
const DEBUG_KEY = 'public-folio-motion-debug';
const DEBUG_HOSTS = new Set(['127.0.0.1', 'localhost', '::1', '[::1]']);
const DEBUG_SEQUENCE = Object.freeze([1, 7, 3, 8, 4, 9, 2, 6, 5, 0]);
const DEBUG_VARIANTS = Object.freeze([
    FOLIO_VARIANTS[0],
    ['.35rem', '6.2rem', '1.25rem', '3.0rem', '2.6rem', '2.2rem'],
    ['2.8rem', '1.8rem', '5.1rem', '.45rem', '3.0rem', '1.05rem'],
    ['.55rem', '5.65rem', '4.9rem', '.85rem', '1.1rem', '3.0rem'],
    ['2.65rem', '2.15rem', '1.3rem', '3.25rem', '2.7rem', '1.3rem'],
    ['.4rem', '6.35rem', '5.15rem', '.5rem', '1.1rem', '2.65rem'],
    ['2.9rem', '1.55rem', '1.5rem', '3.15rem', '3.05rem', '1.15rem'],
    ['.5rem', '5.9rem', '4.65rem', '.6rem', '1.2rem', '2.9rem'],
    ['2.5rem', '2rem', '1.15rem', '3rem', '2.9rem', '1.1rem'],
    ['.4rem', '6.1rem', '4.8rem', '.7rem', '1.1rem', '3.1rem'],
]);
let variant = 0;
let snapshot = null;
let debugStep = 0;

// ?folio-motion=debug enables a conspicuous preview only on localhost.
// ?folio-motion=off clears it. Ordinary pages respect reduced motion.
export function isPublicFolioDebugEnabled(browser = window) {
    if (!DEBUG_HOSTS.has(browser.location?.hostname)) return false;
    const mode = new URL(browser.location.href).searchParams.get('folio-motion');
    try {
        if (mode === 'debug') browser.sessionStorage?.setItem(DEBUG_KEY, '1');
        if (mode === 'off') browser.sessionStorage?.removeItem(DEBUG_KEY);
        return mode !== 'off'
            && (mode === 'debug' || browser.sessionStorage?.getItem(DEBUG_KEY) === '1');
    } catch {
        return mode === 'debug';
    }
}

export function syncPublicFolioDebug(root = document) {
    const enabled = isPublicFolioDebugEnabled();
    if (enabled) root.body?.setAttribute('data-public-folio-debug', '');
    else root.body?.removeAttribute('data-public-folio-debug');
    return enabled;
}

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
    const debug = isPublicFolioDebugEnabled();
    if (!current || (!debug && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
        snapshot = null;
        return false;
    }

    // First visit begins at the exact approved original. During fast
    // navigation a captured *computed* intermediate geometry wins.
    const start = snapshot ?? FOLIO_VARIANTS[variant];
    snapshot = null;
    if (debug) {
        variant = DEBUG_SEQUENCE[debugStep % DEBUG_SEQUENCE.length];
        debugStep += 1;
    } else {
        variant = chooseNextFolioVariant(variant);
    }
    const target = debug ? DEBUG_VARIANTS[variant] : FOLIO_VARIANTS[variant];

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
                    duration: debug ? 1800 : 920,
                    easing: 'cubic-bezier(.2, .65, .25, 1)',
                });
            } catch {
                // Native static geometry remains available.
            }
        }
    }
    current.folio.setAttribute('data-folio-active', '');
    current.folio.dataset.folioVariant = String(variant + 1);
    current.folio.dataset.folioMotion = debug ? 'debug' : 'normal';
    return true;
}
