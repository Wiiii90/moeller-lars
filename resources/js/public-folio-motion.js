// The public paper itself is the only motion surface. Every corner is an
// existing ::before/::after with connected borders. One coordinate axis moves
// at a time; horizontal slides also adjust return width so the joint remains
// anchored to its original white-sheet edge.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4rem', '3.8rem', '1.15rem'],
    ['1.53rem', '4rem', '4.22rem', '1.15rem'],
    ['1.53rem', '4.30rem', '4.22rem', '1.43rem'],
]);
// Menu strokes are connected to the TOP border for their entire life.
// Only their lower joint rises/falls, never the upper endpoint.
// Nonoverlapping cycles: LEFT always ends above RIGHT, regardless of
// each side's phase. Neither lower joint touches the menu bottom.
export const MENU_VARIANTS = Object.freeze([
    ['0rem', '32%', '0rem', '1px'],
    ['0rem', '60%', '0rem', '17%'],
    ['0rem', '42%', '0rem', '6%'],
    ['0rem', '73%', '0rem', '26%'],
]);

// Original horizontal geometry belongs to the accepted shell CSS. These
// values mirror its fixed edges and make the outside/inside motion bounded.
const CONTENT_EDGES = [
    { side: 'left', offset: '-.58rem', width: '.58rem' },
    { side: 'right', offset: '-.48rem', width: '.48rem' },
];
const CONTENT_DURATIONS = [25100, 29700];
const MENU_DURATIONS = [34200, 38800];
const BUILD_DURATION = 1240;
const HEIGHT_DURATION = 780;

// Precisely one outer paper on each surface. The menu's internal rule is
// an inset stroke, not another backing sheet.
const SHADOW_START = {
    content: (x, y) => `${x}px ${y}px 0 var(--public-folio-soft)`,
    menu: (x, y) => [
        'inset 0 -1px 0 var(--public-index-rule)',
        `${x}px ${y}px 0 ${y}px var(--public-folio-soft)`,
    ].join(', '),
};

let snapshot = null;
const activeHeightAnimations = new WeakMap();
const activePaperPulses = new WeakMap();

function visiblePseudo(element, pseudo) {
    if (!element) return null;
    const style = getComputedStyle(element, pseudo);
    return style.display === 'none' || style.content === 'none' ? null : style;
}

function cornerGeometry(element, side, pseudo) {
    const style = visiblePseudo(element, pseudo);
    if (!style) return null;
    const value = {
        top: style.top, bottom: style.bottom,
        width: style.width, [side]: style[side],
    };
    return Object.values(value).some(v => !v || v === 'auto') ? null : value;
}

function pairGeometry(element) {
    if (!element) return null;
    const left = cornerGeometry(element, 'left', '::before');
    const right = cornerGeometry(element, 'right', '::after');
    return left && right ? [left, right] : null;
}

function pageBody(root) {
    return root.querySelector('#content > .public-content-reveal');
}

// Snapshots are captured before Livewire swaps the body. The old article
// height includes any already-running height reveal, so rapid browsing does
// not restart from an unrelated page size.
export function capturePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const body = pageBody(root);
    snapshot = {
        content: pairGeometry(content),
        contentShadow: content ? getComputedStyle(content).boxShadow : null,
        bodyHeight: body ? body.getBoundingClientRect().height : null,
    };
    return Boolean(snapshot.content || body);
}

// One calm outward/shortening sweep and ONE return per cycle. Repeated
// micro-corrections and rapid turns near the loop seam looked like shaking.
// Start from the actual displayed state; close on that EXACT state so a
// repeated WAAPI cycle never jumps. Each leg changes only one axis.
export function buildCornerFrames(start, nextTop, nextBottom, edge) {
    const states = [{ ...start, offset: 0 }];
    const step = (changes, offset) => states.push({
        ...states[states.length - 1], ...changes, offset,
    });
    step({}, .12); // stationary
    step({ [edge.side]: `calc(${edge.offset} - 2px)`,
        width: `calc(${edge.width} + 2px)` }, .26);
    step({}, .36);
    step({ top: nextTop }, .49);
    step({ bottom: nextBottom }, .62);
    step({}, .70);
    step({ top: start.top }, .81);
    step({ bottom: start.bottom }, .91);
    step({ [edge.side]: start[edge.side], width: start.width }, .98);
    step({}, 1);
    return states.map(frame => ({
        ...frame, easing: 'cubic-bezier(.42, 0, .58, 1)',
    }));
}

// One anchored stroke per side. There are deliberate early reversals:
 // extend -> partially retract -> extend again -> rest. The left lower
// endpoint is ALWAYS >=32% high, the right never >26% and never <1px.
// Separate cycle periods cannot invert the visual left/right hierarchy.
export function buildMenuCornerFrames(start, index) {
    const column = index === 0 ? 1 : 3;
    const fixed = { ...start, top: '0px' };
    const steps = [
        { bottom: start.bottom, offset: 0 },
        { bottom: start.bottom, offset: .08 },
        { bottom: MENU_VARIANTS[1][column], offset: .29 },
        { bottom: MENU_VARIANTS[1][column], offset: .37 },
        { bottom: MENU_VARIANTS[2][column], offset: .54 },
        { bottom: MENU_VARIANTS[2][column], offset: .61 },
        { bottom: MENU_VARIANTS[3][column], offset: .80 },
        { bottom: MENU_VARIANTS[3][column], offset: .86 },
        { bottom: start.bottom, offset: .97 },
        { bottom: start.bottom, offset: 1 },
    ];
    return steps.map(({ bottom, offset }) => ({
        ...fixed, bottom, offset, easing: 'cubic-bezier(.42, 0, .58, 1)',
    }));
}

// Parse comma-separated shadow layers without splitting inside rgba().
function shadowLayers(value) {
    const parts = [];
    let depth = 0, begin = 0;
    for (let i = 0; i < value.length; i += 1) {
        if (value[i] === '(') depth += 1;
        else if (value[i] === ')') depth -= 1;
        else if (value[i] === ',' && depth === 0) {
            parts.push(value.slice(begin, i).trim());
            begin = i + 1;
        }
    }
    parts.push(value.slice(begin).trim());
    return parts;
}

function renderedShadowCoords(value, type) {
    const layers = shadowLayers(value);
    if (layers.length !== (type === 'menu' ? 2 : 1)) return null;
    const xy = [...layers.at(-1).matchAll(/(-?\d+(?:\.\d+)?)px/g)]
        .slice(0, 2).map(m => Number(m[1]));
    return xy.length === 2 && xy.every(Number.isFinite) ? xy : null;
}

// The single existing exterior sheet on each surface.
export const FOLIO_PLANE_BASE = Object.freeze({
    content: [-5, 7],
    menu: [-1.5, 3.5],
});

// The backing sheet moves one x or y axis at a time, rests and returns.
// No hidden second sheet, independent shadow copy or interpolated dark fill.
export function buildShadowFrames(start, type) {
    const shadow = SHADOW_START[type];
    const base = FOLIO_PLANE_BASE[type];
    const rendered = renderedShadowCoords(start, type) ?? base;
    const xy = [...rendered];
    const frames = [{ boxShadow: start, weight: 0 }];
    const move = (i, value) => {
        if (xy[i] === value) return;
        const distance = Math.abs(xy[i] - value);
        xy[i] = value;
        frames.push({ boxShadow: shadow(...xy),
            weight: Math.max(4, 3 * distance) });
    };
    const rest = weight => frames.push({ boxShadow: shadow(...xy), weight });
    base.forEach((value, i) => move(i, value));
    rest(9);
    move(0, base[0] - (type === 'menu' ? 1 : 2));
    rest(4);
    move(1, base[1] + (type === 'menu' ? 1.3 : 2));
    rest(10);
    move(0, base[0]); move(1, base[1]);
    rest(10);
    move(0, base[0] + (type === 'menu' ? .4 : .8));
    move(1, base[1] - (type === 'menu' ? .2 : .5));
    rest(7);
    move(1, base[1]); move(0, base[0]);
    rest(9);
    rendered.forEach((value, i) => move(i, value));
    frames.push({ boxShadow: start, weight: 4 });

    const total = frames.reduce((sum, frame) => sum + frame.weight, 0);
    let elapsed = 0;
    return frames.map((frame, index) => {
        elapsed += frame.weight;
        return { boxShadow: frame.boxShadow,
            offset: index === frames.length - 1 ? 1 : elapsed / total,
            easing: 'cubic-bezier(.42, 0, .58, 1)' };
    });
}

// Build transitions are always measured against the destination's STATIC
// paper geometry, not the last active animation. A rapid second click can
// begin at the current in-flight pose, but must never extend it cumulatively.
// The contour first shortens vertically, then opens as the white sheet
// grows, finally settling into the authored CSS at the exact final frame.
// Horizontal movement updates its return width in lockstep (no loose line).
export function buildCornerImpulse(start, base, edge, growing, height = 720) {
    const frames = [{ ...start, offset: 0, easing: 'cubic-bezier(.36, .08, .64, .92)' }];
    const step = (changes, offset) => frames.push({
        ...frames[frames.length - 1], ...changes, offset,
        easing: 'cubic-bezier(.36, .08, .64, .92)',
    });
    // The old 5px reduction was imperceptible on a full-height painting.
    // This contracts BOTH ends by up to 86px on taller pages, but retains
    // at least 40px of vertical stroke on even the shortest content.
    const available = Math.max(0, height - parseFloat(base.top) - parseFloat(base.bottom));
    const room = Math.max(0, available - 40);
    const topCut = Math.min(growing ? 40 : 32, room * .46);
    const bottomCut = Math.min(growing ? 46 : 38, room * .54);
    const amplitude = 3.1;
    step({ [edge.side]: `calc(${edge.offset} - ${amplitude}px)`,
        width: `calc(${edge.width} + ${amplitude}px)` }, .13);
    step({ top: `calc(${base.top} + ${topCut.toFixed(2)}px)` }, .29);
    step({ bottom: `calc(${base.bottom} + ${bottomCut.toFixed(2)}px)` }, .46);
    step({ top: `calc(${base.top} - 4px)` }, .68);
    step({ bottom: `calc(${base.bottom} - 4px)` }, .79);
    step({ [edge.side]: edge.offset, width: edge.width }, .90);
    step({ top: base.top }, .96);
    step({ bottom: base.bottom }, 1);
    return frames;
}

function nativeCorner(element, pseudo, frames, duration, loop = false) {
    if (typeof KeyframeEffect !== 'function' || typeof Animation !== 'function') return null;
    try {
        const effect = new KeyframeEffect(element, frames, {
            pseudoElement: pseudo, duration,
            iterations: loop ? Infinity : 1,
        });
        const animation = new Animation(effect, document.timeline);
        animation.play();
        return animation;
    } catch {
        return null;
    }
}

// The part of a long page below the viewport isn't visible and must NOT
// be dragged through thousands of pixels during a shorter-page navigation.
// Bound both ends of the reveal to what can currently be seen (+48px).
// After the animation, auto height reveals all content without a jump in
// the viewport. No scroll manipulation, DOM cloning, or JS timers.
export function visibleHeightTransition(previousHeight, nextHeight, viewportHeight, bodyTop = 0) {
    if (!Number.isFinite(previousHeight) || previousHeight <= 0
        || !Number.isFinite(nextHeight) || nextHeight <= 0) return null;
    if (!Number.isFinite(viewportHeight) || viewportHeight <= 0) {
        return { from: previousHeight, to: nextHeight };
    }
    const top = Number.isFinite(bodyTop) ? Math.max(0, bodyTop) : 0;
    const cap = Math.min(viewportHeight + 48,
        Math.max(96, viewportHeight - top + 48));
    return { from: Math.min(previousHeight, cap), to: Math.min(nextHeight, cap) };
}

// All pages share the same inner content wrapper. Its animated height
// controls the parent paper's natural height, but ONLY the wrapper clips:
// the original external folio lines/shadows never get cut off.
export function animatePageHeight(previousHeight, root = document) {
    if (!Number.isFinite(previousHeight) || previousHeight <= 0) return null;
    const body = pageBody(root);
    if (!body || typeof body.animate !== 'function') return null;
    const bounds = body.getBoundingClientRect();
    const natural = bounds.height;
    const viewportHeight = typeof window === 'undefined' ? null : window.innerHeight;
    const visible = visibleHeightTransition(previousHeight, natural,
        viewportHeight, bounds.top);
    const prior = activeHeightAnimations.get(body);
    if (!visible || Math.abs(visible.from - visible.to) < 7) {
        // A second Hero click may need no visible resize at all. It must
        // still dispose a canceled predecessor's clipping/height owner.
        if (prior) {
            activeHeightAnimations.delete(body);
            prior.animation.cancel();
            body.style.overflow = prior.originalOverflow;
        }
        return null;
    }
    const original = prior?.originalOverflow ?? body.style.overflow;
    // A second Hero click may interrupt the first height reveal. Only the
    // CURRENT animation is allowed to restore overflow after completion.
    prior?.animation.cancel();
    body.style.overflow = 'clip';
    try {
        const animation = body.animate([
            { height: `${visible.from}px` },
            { height: `${visible.to}px` },
        ], {
            duration: HEIGHT_DURATION,
            easing: 'cubic-bezier(.18, .74, .32, 1)',
        });
        activeHeightAnimations.set(body, { animation, originalOverflow: original });
        const restore = () => {
            if (activeHeightAnimations.get(body)?.animation !== animation) return;
            activeHeightAnimations.delete(body);
            body.style.overflow = original;
        };
        animation.finished.then(restore, restore);
        return animation;
    } catch {
        activeHeightAnimations.delete(body);
        body.style.overflow = original;
        return null;
    }
}

// One exterior backing sheet follows the white page's construction;
 // original captured and authored CSS poses remain the cycle endpoints.
// On navigation, push the SAME single sheet outward or inward. Geometry
// is based on canonical coordinates, preventing cumulative extension on
// rapid Hero clicks. The page-height reveal keeps its own existing pace.
export function buildPageImpulse(start, end, type, growing) {
    const base = FOLIO_PLANE_BASE[type];
    const xy = [...(renderedShadowCoords(start, type) ?? base)];
    const shadow = SHADOW_START[type];
    const frames = [start];
    const move = (index, value) => {
        if (xy[index] === value) return;
        xy[index] = value;
        frames.push(shadow(...xy));
    };
    const scale = type === 'menu' ? .5 : 1;
    if (growing !== false) {
        move(0, base[0] - 3.2 * scale);
        move(1, base[1] + 2.8 * scale);
    } else {
        move(0, base[0] + 1.2 * scale);
        move(1, base[1] - .8 * scale);
    }
    const destination = renderedShadowCoords(end, type) ?? base;
    move(0, destination[0]);
    move(1, destination[1]);
    frames.push(end);
    return frames.map((boxShadow, index) => ({
        boxShadow, offset: index / (frames.length - 1),
        easing: 'cubic-bezier(.35, 0, .25, 1)',
    }));
}

function startAmbient(element, states, type) {
    if (!element || !states) return;
    const durations = type === 'content' ? CONTENT_DURATIONS : MENU_DURATIONS;
    for (let index = 0; index < 2; index += 1) {
        let frames;
        if (type === 'menu') {
            frames = buildMenuCornerFrames(states[index], index);
        } else {
            const columns = index === 0 ? [0, 1] : [2, 3];
            frames = buildCornerFrames(states[index],
                FOLIO_VARIANTS[1][columns[0]],
                FOLIO_VARIANTS[2][columns[1]], CONTENT_EDGES[index]);
        }
        nativeCorner(element, index === 0 ? '::before' : '::after',
            frames, durations[index], true);
    }
    const shadow = getComputedStyle(element).boxShadow;
    if (shadow && typeof element.animate === 'function') {
        try {
            element.animate(buildShadowFrames(shadow, type), {
                duration: type === 'content' ? 30200 : 36400,
                iterations: Infinity,
            });
        } catch { /* The canonical CSS layers remain visible. */ }
    }
}

function pulsePaper(element, corners, oldShadow, growing, root) {
    if (!element || !corners) return false;
    const token = {};
    activePaperPulses.set(element, token);
    const animations = [];
    // Fresh DOM on Livewire swaps and canceled DOM on Home Hero both expose
    // the true destination base here. Never derive the new excursion from
    // corners sampled mid-flight on a previous pulse.
    const canonical = pairGeometry(element);
    for (let i = 0; i < 2; i += 1) {
        const animation = nativeCorner(element, i === 0 ? '::before' : '::after',
            buildCornerImpulse(corners[i], canonical?.[i] ?? corners[i],
                CONTENT_EDGES[i], growing,
                element.getBoundingClientRect().height), BUILD_DURATION);
        if (animation) animations.push(animation);
    }
    if (oldShadow && typeof element.animate === 'function') {
        try {
            const shadowAnimation = element.animate(
                buildPageImpulse(oldShadow, getComputedStyle(element).boxShadow, 'content', growing),
                { duration: BUILD_DURATION },
            );
            animations.push(shadowAnimation);
        } catch { /* Preserve the real CSS layers. */ }
    }
    if (animations.length === 0) {
        activePaperPulses.delete(element);
        return false;
    }
    // Both a detached Livewire page AND an in-place Home Hero can supersede
    // a prior build. Only the latest token may resume ambient breathing.
    Promise.allSettled(animations.map(animation => animation.finished)).then(() => {
        if (activePaperPulses.get(element) !== token
            || root.querySelector('#content') !== element) return;
        activePaperPulses.delete(element);
        startAmbient(element, pairGeometry(element), 'content');
    });
    return true;
}

// Every real Public SPA swap triggers a coordinated paper-build gesture,
// independently of whether the destination is an Artwork, Journal, Home,
// Exhibitions or Custom page. An unchanged page height still receives a
// small horizontal/vertical paper impulse. On initial load, breathe calmly.
export function advancePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const menu = root.querySelector('.site-navigation');
    const previous = snapshot;
    snapshot = null;
    const corners = previous?.content ?? pairGeometry(content);
    const contentShadow = previous?.contentShadow ?? (content && getComputedStyle(content).boxShadow);
    if (!previous) {
        startAmbient(content, corners, 'content');
        startAmbient(menu, pairGeometry(menu), 'menu');
        return Boolean(content || menu);
    }

    const body = pageBody(root);
    const nextHeight = body?.getBoundingClientRect().height;
    const growing = !Number.isFinite(nextHeight) || !Number.isFinite(previous.bodyHeight)
        || nextHeight >= previous.bodyHeight;
    animatePageHeight(previous.bodyHeight, root);
    if (!pulsePaper(content, corners, contentShadow, growing, root)) {
        startAmbient(content, corners, 'content');
    }
    // The menu has no new content to assemble. On SPA swaps it continues
    // only its calm ambient breathing, without a navigation-triggered pulse.
    startAmbient(menu, pairGeometry(menu), 'menu');
    return Boolean(content || menu);
}


// Home Hero's arrows change hidden slides IN PLACE, not via Livewire.
// Reuse the SAME content-only page machinery around the actual slide toggle.
// Never pulse the menu and never synthesize a route/History transition.
export function animatePublicHeroChange(change, root = document) {
    const content = root.querySelector('#content');
    const body = pageBody(root);
    if (!content || !body) {
        change();
        return false;
    }
    const oldHeight = body.getBoundingClientRect().height;
    const corners = pairGeometry(content);
    const shadow = getComputedStyle(content).boxShadow;

    // Stop competing infinite effects on the current content surface.
    // Sample FIRST so the next impulse begins exactly at its visible pose.
    document.getAnimations().filter(animation =>
        animation.effect?.target === content
    ).forEach(animation => animation.cancel());
    body.getAnimations().forEach(animation => animation.cancel());

    change(); // Existing Hero ownership: update only hidden states.
    const newHeight = body.getBoundingClientRect().height;
    const growing = newHeight >= oldHeight;
    animatePageHeight(oldHeight, root);
    if (!pulsePaper(content, corners, shadow, growing, root)) {
        startAmbient(content, pairGeometry(content), 'content');
    }
    return true;
}
