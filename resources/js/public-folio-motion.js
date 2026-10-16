// The public paper has one motion owner. Six resting corner connection points
// are server-rendered per-route, not re-randomized after hydration.
const CONTENT_EDGES = [
    { side: 'left', offset: '-7px', width: '7px' },
    { side: 'right', offset: '-6px', width: '6px' },
];
const CONTENT_DURATIONS = [98000, 113000];
const MENU_DURATIONS = [117000, 133000];
const BUILD_DURATION = 620;
const HEIGHT_DURATION = 480;
const contentShadow = (x, y) => `${x}px ${y}px 0 var(--public-folio-soft)`;

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

// Ambient contours are stationary for most of a very long cycle.
export function buildCornerFrames(start) {
    const pose = (top, bottom, offset) => ({
        ...start, top, bottom, offset, easing: 'cubic-bezier(.42, 0, .58, 1)',
    });
    return [
        pose(start.top, start.bottom, 0),
        pose(start.top, start.bottom, .25),
        pose(`calc(${start.top} + 1.5px)`, start.bottom, .31),
        pose(start.top, start.bottom, .37),
        pose(start.top, start.bottom, .60),
        pose(start.top, `calc(${start.bottom} + 2px)`, .66),
        pose(start.top, start.bottom, .72),
        pose(start.top, start.bottom, .95),
        pose(start.top, start.bottom, 1),
    ];
}

// Menu endpoints stay joined at the top. Left/right ranges cannot overlap.
export function buildMenuCornerFrames(start, index) {
    const lift = index === 0 ? 1.5 : 1;
    const pose = (bottom, offset) => ({
        ...start, top: '0px', bottom, offset,
        easing: 'cubic-bezier(.42, 0, .58, 1)',
    });
    return [
        pose(start.bottom, 0),
        pose(start.bottom, .28),
        pose(`calc(${start.bottom} + ${lift}px)`, .34),
        pose(start.bottom, .40),
        pose(start.bottom, .64),
        pose(`calc(${start.bottom} + ${lift / 2}px)`, .70),
        pose(start.bottom, .76),
        pose(start.bottom, 1),
    ];
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

function renderedShadowCoords(value) {
    const layers = shadowLayers(value);
    if (layers.length !== 1) return null;
    const xy = [...layers.at(-1).matchAll(/(-?\d+(?:\.\d+)?)px/g)]
        .slice(0, 2).map(m => Number(m[1]));
    return xy.length === 2 && xy.every(Number.isFinite) ? xy : null;
}

// The single existing exterior sheet on each surface.
export const FOLIO_PLANE_BASE = Object.freeze({
    content: [-3, 4],
    menu: [-3, 4],
});

// The backing barely moves, and only during two small windows per cycle.
export function buildShadowFrames(start) {
    const [x, y] = renderedShadowCoords(start) ?? FOLIO_PLANE_BASE.content;
    const shadow = (dx, dy, offset) => ({
        boxShadow: contentShadow(dx, dy), offset,
        easing: 'cubic-bezier(.42, 0, .58, 1)',
    });
    return [
        { boxShadow: start, offset: 0 },
        shadow(x, y, .26),
        shadow(x - .4, y, .32),
        shadow(x, y, .38),
        shadow(x, y, .62),
        shadow(x, y + .5, .68),
        shadow(x, y, .74),
        shadow(x, y, .96),
        { boxShadow: start, offset: 1 },
    ];
}

export function buildMenuBackingFrames() {
    const [x, y] = FOLIO_PLANE_BASE.menu;
    return [
        [0, x, y], [.28, x, y],
        [.34, x - .35, y], [.40, x, y],
        [.64, x, y], [.70, x, y + .45],
        [.76, x, y], [1, x, y],
    ].map(([offset, left, down]) => ({
        '--public-menu-sheet-x': `${left}px`,
        '--public-menu-sheet-y': `${down}px`,
        offset, easing: 'cubic-bezier(.42, 0, .58, 1)',
    }));
}

// The old large folding motion is replaced by a small, fast page change.
// Coordinates always settle on the newly authored page pose.
export function buildCornerImpulse(start, base, edge, growing, height = 720) {
    const frames = [{ ...start, offset: 0, easing: 'cubic-bezier(.38, .08, .62, .92)' }];
    const step = (changes, offset) => frames.push({
        ...frames[frames.length - 1], ...changes, offset,
        easing: 'cubic-bezier(.38, .08, .62, .92)',
    });
    const available = Math.max(0, height - parseFloat(base.top) - parseFloat(base.bottom));
    const room = Math.max(0, available - 40);
    const topCut = Math.min(growing ? 6 : 5, room * .46);
    const bottomCut = Math.min(growing ? 8 : 6, room * .54);
    step({ [edge.side]: `calc(${edge.offset} - .7px)`,
        width: `calc(${edge.width} + .7px)` }, .16);
    step({ top: `calc(${base.top} + ${topCut.toFixed(2)}px)` }, .33);
    step({ bottom: `calc(${base.bottom} + ${bottomCut.toFixed(2)}px)` }, .50);
    step({ top: base.top }, .72);
    step({ bottom: base.bottom }, .86);
    step({ [edge.side]: edge.offset, width: edge.width }, .97);
    step({}, 1);
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
        }
        return null;
    }
    // Clipping belongs to the animation effect, never a serialized style
    // attribute. Canceling/replacing the effect restores the underlying CSS.
    prior?.animation.cancel();
    try {
        const animation = body.animate([
            { height: `${visible.from}px`, overflow: 'clip' },
            { height: `${visible.to}px`, overflow: 'clip' },
        ], {
            duration: HEIGHT_DURATION,
            easing: 'cubic-bezier(.18, .74, .32, 1)',
        });
        activeHeightAnimations.set(body, { animation });
        const restore = () => {
            if (activeHeightAnimations.get(body)?.animation !== animation) return;
            activeHeightAnimations.delete(body);
        };
        animation.finished.then(restore, restore);
        return animation;
    } catch {
        activeHeightAnimations.delete(body);
        return null;
    }
}

// Content-only page build. The rear sheet never moves more than 0.8px.
export function buildPageImpulse(start, end, growing) {
    const [x, y] = FOLIO_PLANE_BASE.content;
    const initial = renderedShadowCoords(start) ?? [x, y];
    const destination = renderedShadowCoords(end) ?? [x, y];
    const frames = [{ boxShadow: start, offset: 0 }];
    const step = (dx, dy, offset) => frames.push({
        boxShadow: contentShadow(dx, dy), offset,
        easing: 'cubic-bezier(.38, .08, .62, .92)',
    });
    step(x, initial[1], .18);
    step(x, y, .30);
    step(x + (growing === false ? .35 : -.65), y, .48);
    step(x, y, .68);
    step(x, y + (growing === false ? -.35 : .8), .82);
    step(destination[0], y, .93);
    frames.push({ boxShadow: end, offset: 1 });
    return frames;
}

function startAmbient(element, states, type) {
    if (!element || !states) return;
    const durations = type === 'content' ? CONTENT_DURATIONS : MENU_DURATIONS;
    for (let index = 0; index < 2; index += 1) {
        let frames;
        if (type === 'menu') {
            frames = buildMenuCornerFrames(states[index], index);
        } else {
            frames = buildCornerFrames(states[index]);
        }
        nativeCorner(element, index === 0 ? '::before' : '::after',
            frames, durations[index], true);
    }
    if (typeof element.animate !== 'function') return;
    try {
        if (type === 'menu') {
            element.animate(buildMenuBackingFrames(), { duration: 145000, iterations: Infinity });
        } else {
            element.animate(buildShadowFrames(getComputedStyle(element).boxShadow), {
                duration: 129000, iterations: Infinity,
            });
        }
    } catch { /* The authored paper remains visible without WAAPI. */ }
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
                buildPageImpulse(oldShadow, getComputedStyle(element).boxShadow, growing),
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
