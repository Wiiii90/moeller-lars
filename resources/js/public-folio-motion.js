// The public paper itself is the only motion surface. Every corner is an
// existing ::before/::after with connected borders. One coordinate axis moves
// at a time; horizontal slides also adjust return width so the joint remains
// anchored to its original white-sheet edge.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4rem', '3.8rem', '1.15rem'],
    ['1.53rem', '4rem', '4.22rem', '1.15rem'],
    ['1.53rem', '4.30rem', '4.22rem', '1.43rem'],
]);
export const MENU_VARIANTS = Object.freeze([
    ['0rem', '0rem', '0rem', '0rem'],
    ['.23rem', '0rem', '.21rem', '0rem'],
    ['.23rem', '.14rem', '.21rem', '.14rem'],
]);

// Original horizontal geometry belongs to the accepted shell CSS. These
// values mirror its fixed edges and make the outside/inside motion bounded.
const CONTENT_EDGES = [
    { side: 'left', offset: '-.7rem', width: '.55rem' },
    { side: 'right', offset: '-.55rem', width: '.55rem' },
];
const MENU_EDGES = [
    { side: 'left', offset: '-.31rem', width: '.55rem' },
    { side: 'right', offset: '-.32rem', width: '.56rem' },
];
const CONTENT_DURATIONS = [19800, 23600];
const MENU_DURATIONS = [21900, 25800];
const BUILD_DURATION = 1480;
const HEIGHT_DURATION = 980;

const SHADOW_START = {
    content: (middleX, middleY, rearX, rearY) => [
        '-2px 3px 0 rgba(28, 35, 32, .09)',
        `${middleX}px ${middleY}px 0 var(--public-folio-soft)`,
        `${rearX}px ${rearY}px 0 var(--public-folio-depth)`,
    ].join(', '),
    menu: (middleX, middleY, rearX, rearY) => [
        'inset 0 -1px 0 var(--public-index-rule)',
        `${middleX}px ${middleY}px 0 1px var(--public-folio-depth)`,
        `${rearX}px ${rearY}px 0 1px var(--public-folio-soft)`,
    ].join(', '),
};
let snapshot = null;

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
    const menu = root.querySelector('.site-navigation');
    const body = pageBody(root);
    snapshot = {
        content: pairGeometry(content),
        menu: pairGeometry(menu),
        contentShadow: content ? getComputedStyle(content).boxShadow : null,
        menuShadow: menu ? getComputedStyle(menu).boxShadow : null,
        bodyHeight: body ? body.getBoundingClientRect().height : null,
    };
    return Boolean(snapshot.content || snapshot.menu);
}

// Each successive frame changes ONLY one axis: top, bottom or a horizontal
// (offset+width) pair. Connected return strokes cannot detach or become
// diagonal. The final frame is exactly the captured initial configuration.
export function buildCornerFrames(start, base, nextTop, nextBottom, edge) {
    const frames = [{ ...start }];
    const step = patch => frames.push({ ...frames[frames.length - 1], ...patch });
    // Pauses are ordinary repeated WAAPI frames, NOT timers: individual
    // strokes may rest while others continue. One direction per segment.
    const rest = () => step({});
    step({ top: base.top });
    step({ bottom: base.bottom });
    rest(); rest();
    step({
        [edge.side]: `calc(${edge.offset} - 3.5px)`,
        width: `calc(${edge.width} + 3.5px)`,
    });
    rest();
    step({ top: nextTop });
    rest();
    step({ bottom: nextBottom });
    rest();
    step({
        [edge.side]: `calc(${edge.offset} + 1.8px)`,
        width: `calc(${edge.width} - 1.8px)`,
    });
    rest(); rest();
    step({ top: base.top });
    step({ bottom: base.bottom });
    step({ [edge.side]: start[edge.side], width: start.width });
    step({ top: start.top });
    step({ bottom: start.bottom });
    rest(); rest();
    return frames.map((frame, index) => ({
        ...frame,
        offset: index / (frames.length - 1),
        easing: 'ease-in-out',
    }));
}

// Parse only the two existing rear planes, respecting commas inside rgb()
// functions. SPA swaps may interrupt a plane halfway across an axis;
// restoring each coordinate separately prevents a diagonal reset.
function renderedShadowCoords(value) {
    const layers = [];
    let depth = 0, begin = 0;
    for (let i = 0; i < value.length; i += 1) {
        if (value[i] === '(') depth += 1;
        else if (value[i] === ')') depth -= 1;
        else if (value[i] === ',' && depth === 0) {
            layers.push(value.slice(begin, i));
            begin = i + 1;
        }
    }
    layers.push(value.slice(begin));
    if (layers.length !== 3) return null;
    const positions = layers.slice(1).flatMap(layer =>
        [...layer.matchAll(/(-?\d+(?:\.\d+)?)px/g)].slice(0, 2).map(m => Number(m[1])));
    return positions.length === 4 && positions.every(Number.isFinite) ? positions : null;
}

// These match the three CSS layers exactly. The second and third paper
// planes must remain separate along LEFT and BOTTOM for every animation frame.
export const FOLIO_PLANE_BASE = Object.freeze({
    content: [-5, 7, -8, 10],
    menu: [-1, 1, -3, 2],
});

function shadowMotionFrames(start, type, direction, end = start) {
    const shadow = SHADOW_START[type];
    const base = FOLIO_PLANE_BASE[type];
    const positions = [...(renderedShadowCoords(start) ?? base)];
    const frames = [start];
    const current = () => shadow(...positions);
    const move = (index, target) => {
        if (positions[index] === target) return;
        positions[index] = target;
        frames.push(current());
    };
    const rest = () => frames.push(current());

    // Resume interrupted geometry axis by axis; never snap both coordinates
    // to a default position in one frame.
    base.forEach((value, index) => move(index, value));
    rest(); rest();
    const outward = direction !== 'inward';
    // The rear layer leads outward to preserve separation as the front
    // catches up. This sequence never swallows an already visible sheet.
    move(2, base[2] + (type === 'menu' ? -1.8 : -3.5));
    move(0, base[0] + (type === 'menu' ? -1 : -2));
    rest();
    move(3, base[3] + (type === 'menu' ? 1.4 : 3));
    move(1, base[1] + (type === 'menu' ? .75 : 1.7));
    rest(); rest();
    // Retract each sheet coordinate independently before the next phase.
    move(0, base[0]); move(1, base[1]);
    move(2, base[2]); move(3, base[3]);
    rest();
    // For the constant ambient cycle both excursions are available, with
    // quiet intervals between them; no involuntary jitter or diagonal steps.
    if (direction === 'cycle') {
        move(0, base[0] + (type === 'menu' ? .4 : 1.0));
        move(2, base[2] + (type === 'menu' ? .1 : 1.2));
        rest();
        move(1, base[1] - (type === 'menu' ? .2 : .6));
        move(3, base[3] - (type === 'menu' ? .1 : .3));
        rest(); rest();
        move(2, base[2]); move(3, base[3]);
        move(0, base[0]); move(1, base[1]);
        rest();
        (renderedShadowCoords(start) ?? base).forEach((value, index) => move(index, value));
    }
    frames.push(end);
    return frames.map((boxShadow, index) => ({
        boxShadow, offset: index / (frames.length - 1),
        easing: 'cubic-bezier(.33, 0, .67, 1)',
    }));
}

// A shadow layer's color/shape never changes, only one x/y coordinate per
// segment. Even the farthest inward positions keep a >=2px exposed margin.
export function buildShadowFrames(start, type) {
    return shadowMotionFrames(start, type, 'cycle');
}

function playShadowBreathing(element, shadow, type) {
    if (!element || !shadow || typeof element.animate !== 'function') return;
    try {
        element.animate(buildShadowFrames(shadow, type), {
            duration: type === 'content' ? 34800 : 36400,
            iterations: Infinity,
        });
    } catch {
        // Unsupported animation keeps the preexisting CSS paper layers.
    }
}

function playArtworkBuild(element, shadow, growing, root) {
    if (!element || !shadow || typeof element.animate !== 'function') return false;
    try {
        const finalShadow = getComputedStyle(element).boxShadow;
        const animation = element.animate(
            buildArtworkRevealFrames(shadow, finalShadow, growing),
            { duration: 700, easing: 'cubic-bezier(.22, .62, .32, 1)' },
        );
        animation.finished.then(() => {
            // A later SPA visit may already have replaced this paper.
            if (root.querySelector('#content') === element) {
                playShadowBreathing(element, getComputedStyle(element).boxShadow, 'content');
            }
        }, () => {});
        return true;
    } catch {
        return false;
    }
}

// A directional sequence: steady approach, a visible paper-driven thrust,
// a short follow-through, then a controlled return. In each keyframe only
// ONE axis changes; lateral movement adjusts width with the offset so the
// original corner remains connected to the same white-sheet edge.
export function buildCornerImpulse(start, edge, growing, type = 'content') {
    const frames = [{ ...start }];
    const step = patch => frames.push({ ...frames[frames.length - 1], ...patch });
    const amplitude = type === 'menu' ? 1.7 : 5;
    const vertical = type === 'menu' ? 2.0 : 6.5;
    const delta = growing ? 1 : -1;
    step({ [edge.side]: `calc(${start[edge.side]} - ${amplitude}px)`,
        width: `calc(${start.width} + ${amplitude}px)` });
    // Menu outside strokes must end inside their white bottom border.
    // A content corner may extend further down during the page build.
    const bottomDelta = type === 'menu' ? vertical : -vertical * delta;
    step({ bottom: `calc(${start.bottom} + ${bottomDelta}px)` });
    step({ top: `calc(${start.top} + ${vertical * .68}px)` });
    step({ [edge.side]: start[edge.side], width: start.width });
    step({ bottom: start.bottom });
    step({ top: start.top });
    return frames.map((frame, index) => ({
        ...frame,
        offset: index / (frames.length - 1),
        easing: 'cubic-bezier(.35, 0, .25, 1)',
    }));
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

// All pages share the same inner content wrapper. Its animated height
// controls the parent paper's natural height, but ONLY the wrapper clips:
// the original external folio lines/shadows never get cut off.
export function animatePageHeight(previousHeight, root = document) {
    if (!Number.isFinite(previousHeight) || previousHeight <= 0) return null;
    const body = pageBody(root);
    if (!body || typeof body.animate !== 'function') return null;
    const natural = body.getBoundingClientRect().height;
    if (!Number.isFinite(natural) || Math.abs(natural - previousHeight) < 7) return null;
    const original = body.style.overflow;
    body.style.overflow = 'clip';
    try {
        const animation = body.animate([
            { height: `${previousHeight}px` },
            { height: `${natural}px` },
        ], {
            duration: HEIGHT_DURATION,
            easing: 'cubic-bezier(.18, .74, .32, 1)',
        });
        animation.finished.then(() => { body.style.overflow = original; },
            () => { body.style.overflow = original; });
        return animation;
    } catch {
        body.style.overflow = original;
        return null;
    }
}

// Two genuine rear paper planes advance in measured separate directions;
// exposed separation from the white front and from one another is preserved.
// The first and final frames are actual sampled CSS values.
export function buildPageImpulse(start, end, type, growing) {
    const base = FOLIO_PLANE_BASE[type];
    const coords = [...(renderedShadowCoords(start) ?? base)];
    const shadow = SHADOW_START[type];
    const values = [start];
    const set = (i, v) => {
        if (coords[i] === v) return;
        coords[i] = v;
        values.push(shadow(...coords));
    };
    // In a growing page the rear plane leads outward. When shrinking the
    // middle plane retracts first so the rear one never gets swallowed.
    const scale = type === 'menu' ? .48 : 1;
    const outward = growing !== false;
    const middleX = base[0] + (outward ? -2.4 : 1.4) * scale;
    const rearX = base[2] + (outward ? -4.8 : 1.0) * scale;
    const middleY = base[1] + (outward ? 1.8 : -.9) * scale;
    const rearY = base[3] + (outward ? 4 : -.7) * scale;
    if (outward) {
        set(2, rearX); set(3, rearY); set(0, middleX); set(1, middleY);
    } else {
        set(0, middleX); set(1, middleY); set(2, rearX); set(3, rearY);
    }
    // Each plane retracts in one-axis movements before returning to the
    // destination's authored styling; never snap two axes simultaneously.
    const target = renderedShadowCoords(end) ?? base;
    if (outward) {
        set(0, target[0]); set(1, target[1]); set(2, target[2]); set(3, target[3]);
    } else {
        set(2, target[2]); set(3, target[3]); set(0, target[0]); set(1, target[1]);
    }
    values.push(end);
    return values.map((boxShadow, index) => ({
        boxShadow,
        offset: index / (values.length - 1),
        easing: 'cubic-bezier(.35, 0, .25, 1)',
    }));
}

function startAmbient(element, states, type) {
    if (!element || !states) return;
    const base = type === 'content' ? FOLIO_VARIANTS : MENU_VARIANTS;
    const edges = type === 'content' ? CONTENT_EDGES : MENU_EDGES;
    const durations = type === 'content' ? CONTENT_DURATIONS : MENU_DURATIONS;
    for (let index = 0; index < 2; index += 1) {
        const columns = index === 0 ? [0, 1] : [2, 3];
        const first = { top: base[0][columns[0]], bottom: base[0][columns[1]] };
        nativeCorner(element, index === 0 ? '::before' : '::after',
            buildCornerFrames(states[index], first,
                base[1][columns[0]], base[2][columns[1]], edges[index]),
            durations[index], true);
    }
    const shadow = getComputedStyle(element).boxShadow;
    if (shadow && typeof element.animate === 'function') {
        try {
            element.animate(buildShadowFrames(shadow, type), {
                duration: type === 'content' ? 24700 : 28100,
                iterations: Infinity,
            });
        } catch { /* The canonical CSS layers remain visible. */ }
    }
}

function pulsePaper(element, corners, oldShadow, type, growing, root) {
    if (!element || !corners) return false;
    const edges = type === 'content' ? CONTENT_EDGES : MENU_EDGES;
    const animations = [];
    for (let i = 0; i < 2; i += 1) {
        const animation = nativeCorner(element, i === 0 ? '::before' : '::after',
            buildCornerImpulse(corners[i], edges[i], growing, type),
            type === 'content' ? BUILD_DURATION : 980);
        if (animation) animations.push(animation);
    }
    if (oldShadow && typeof element.animate === 'function') {
        try {
            const shadowAnimation = element.animate(
                buildPageImpulse(oldShadow, getComputedStyle(element).boxShadow, type, growing),
                { duration: type === 'content' ? BUILD_DURATION : 980 },
            );
            animations.push(shadowAnimation);
        } catch { /* Preserve the real CSS layers. */ }
    }
    if (animations.length === 0) return false;
    // Do not start the ambient effect on the same properties until this
    // one-shot build has finished; ignore detached nodes after fast visits.
    Promise.allSettled(animations.map(animation => animation.finished)).then(() => {
        const selector = type === 'content' ? '#content' : '.site-navigation';
        if (root.querySelector(selector) === element) {
            startAmbient(element, pairGeometry(element), type);
        }
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
    const menuCorners = previous?.menu ?? pairGeometry(menu);
    const contentShadow = previous?.contentShadow ?? (content && getComputedStyle(content).boxShadow);
    const menuShadow = previous?.menuShadow ?? (menu && getComputedStyle(menu).boxShadow);
    if (!previous) {
        startAmbient(content, corners, 'content');
        startAmbient(menu, menuCorners, 'menu');
        return Boolean(content || menu);
    }

    const body = pageBody(root);
    const nextHeight = body?.getBoundingClientRect().height;
    const growing = !Number.isFinite(nextHeight) || !Number.isFinite(previous.bodyHeight)
        || nextHeight >= previous.bodyHeight;
    animatePageHeight(previous.bodyHeight, root);
    if (!pulsePaper(content, corners, contentShadow, 'content', growing, root)) {
        startAmbient(content, corners, 'content');
    }
    if (!pulsePaper(menu, menuCorners, menuShadow, 'menu', growing, root)) {
        startAmbient(menu, menuCorners, 'menu');
    }
    return Boolean(content || menu);
}
