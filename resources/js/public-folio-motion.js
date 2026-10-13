// One folio language: animate the EXISTING paper outlines and shadows.
// No new DOM, no timers or polling; the browser's native animation timeline
// gently cycles the original ::before and ::after on Content AND navigation.
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
export const MENU_VARIANTS = Object.freeze([
    ['-.76rem', '-.38rem', '-1.1rem', '-.44rem'],
    ['-.88rem', '-.47rem', '-1.23rem', '-.51rem'],
    ['-.66rem', '-.31rem', '-.98rem', '-.35rem'],
    ['-.83rem', '-.43rem', '-1.17rem', '-.56rem'],
    ['-.76rem', '-.38rem', '-1.1rem', '-.44rem'],
]);
export const FOLIO_SHADOW_DEPTHS = Object.freeze([
    [7, 8], [9, 11], [6, 9], [8, 10], [5, 7],
    [10, 12], [6, 8], [9, 10], [7, 11], [5, 9],
]);
const CONTENT_SEQUENCE = Object.freeze([0, 3, 8, 5, 1, 0]);
const SHADOW_SEQUENCE = Object.freeze([0, 3, 7, 5, 1, 0]);
let snapshot = null;

function visiblePseudo(element, pseudo) {
    if (!element) return null;
    const style = getComputedStyle(element, pseudo);
    return style.display === 'none' || style.content === 'none'
        ? null : style;
}

function geometry(element) {
    const before = visiblePseudo(element, '::before');
    const after = visiblePseudo(element, '::after');
    if (!before || !after) return null;
    const values = [before.top, before.bottom, after.top, after.bottom];
    return values.some(value => !value || value === 'auto') ? null : values;
}

function shadowFor(index) {
    const [middleY, rearY] = FOLIO_SHADOW_DEPTHS[index];
    return [
        '-2px 3px 0 rgba(28, 35, 32, .09)',
        `-5px ${middleY}px 0 var(--public-folio-soft)`,
        `-6px ${rearY}px 0 var(--public-folio-depth)`,
    ].join(', ');
}

// Capture *computed* intermediate values before Livewire replaces its body.
export function capturePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const menu = root.querySelector('.site-navigation');
    snapshot = {
        content: geometry(content),
        menu: geometry(menu),
        shadow: content ? getComputedStyle(content).boxShadow : null,
    };
    return Boolean(snapshot.content || snapshot.menu);
}

function playPseudo(element, pseudo, frames, duration) {
    if (typeof KeyframeEffect !== 'function' || typeof Animation !== 'function') return;
    try {
        const effect = new KeyframeEffect(element, frames, {
            pseudoElement: pseudo,
            duration,
            iterations: Infinity,
        });
        new Animation(effect, document.timeline).play();
    } catch {
        // The exact accepted CSS contours remain visible without native WAAPI.
    }
}

function cycleStates(start, states, order, first, second) {
    // Matching first/last keyframes avoid any jump at a loop boundary.
    const values = [start, ...order.slice(1, -1).map(index => states[index]), start];
    return values.map((value, index) => ({
        top: value[first],
        bottom: value[second],
        offset: index / (values.length - 1),
        easing: 'ease-in-out',
    }));
}

function animateCorners(element, start, states, order, leftDuration, rightDuration) {
    if (!start || !element) return;
    playPseudo(element, '::before',
        cycleStates(start, states, order, 0, 1), leftDuration);
    playPseudo(element, '::after',
        cycleStates(start, states, order, 2, 3), rightDuration);
}

// Called on initial page load AND each completed Livewire visit.
// The original first shape is visible immediately; movement then continues
// slowly on its own, without navigating and without interval callbacks.
export function advancePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const menu = root.querySelector('.site-navigation');
    const contentStart = snapshot?.content ?? geometry(content);
    const menuStart = snapshot?.menu ?? geometry(menu);
    const shadowStart = snapshot?.shadow ?? (content && getComputedStyle(content).boxShadow);
    snapshot = null;

    animateCorners(content, contentStart, FOLIO_VARIANTS, CONTENT_SEQUENCE, 16000, 19400);
    animateCorners(menu, menuStart, MENU_VARIANTS, [0, 1, 3, 2, 0], 17400, 20800);

    if (content && contentStart && shadowStart && typeof content.animate === 'function') {
        try {
            const shadows = [
                shadowStart,
                ...SHADOW_SEQUENCE.slice(1, -1).map(shadowFor),
                shadowStart,
            ];
            content.animate(shadows.map((boxShadow, index) => ({
                boxShadow,
                offset: index / (shadows.length - 1),
                easing: 'ease-in-out',
            })), { duration: 18200, iterations: Infinity });
        } catch {
            // The original three real sheet shadows remain unchanged.
        }
    }
    return Boolean(contentStart || menuStart);
}
