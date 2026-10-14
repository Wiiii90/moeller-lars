// The public paper itself is the only motion surface. Every corner is an
// existing ::before/::after with connected borders. One coordinate axis moves
// at a time; horizontal slides also adjust return width so the joint remains
// anchored to its original white-sheet edge.
export const FOLIO_VARIANTS = Object.freeze([
    ['1.10rem', '4rem', '3.8rem', '1.15rem'],
    ['1.25rem', '4rem', '3.93rem', '1.15rem'],
    ['1.25rem', '4.14rem', '3.93rem', '1.28rem'],
]);
export const MENU_VARIANTS = Object.freeze([
    ['0rem', '0rem', '0rem', '0rem'],
    ['.16rem', '0rem', '.13rem', '0rem'],
    ['.16rem', '.10rem', '.13rem', '.10rem'],
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
const CONTENT_DURATIONS = [28600, 31800];
const MENU_DURATIONS = [27000, 30900];

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

function artworkArticle(root) {
    return root.querySelector('#content > .artwork-detail');
}

// Snapshots are captured before Livewire swaps the body. The old article
// height includes any already-running height reveal, so rapid browsing does
// not restart from an unrelated page size.
export function capturePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const menu = root.querySelector('.site-navigation');
    const article = artworkArticle(root);
    snapshot = {
        content: pairGeometry(content),
        menu: pairGeometry(menu),
        contentShadow: content ? getComputedStyle(content).boxShadow : null,
        menuShadow: menu ? getComputedStyle(menu).boxShadow : null,
        articleHeight: article ? article.getBoundingClientRect().height : null,
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
        [edge.side]: `calc(${edge.offset} - 1.5px)`,
        width: `calc(${edge.width} + 1.5px)`,
    });
    rest();
    step({ top: nextTop });
    rest();
    step({ bottom: nextBottom });
    rest();
    step({
        [edge.side]: `calc(${edge.offset} + 1px)`,
        width: `calc(${edge.width} - 1px)`,
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

function playCorner(element, pseudo, frames, duration) {
    if (typeof KeyframeEffect !== 'function' || typeof Animation !== 'function') return;
    try {
        const effect = new KeyframeEffect(element, frames, {
            pseudoElement: pseudo, duration, iterations: Infinity,
        });
        new Animation(effect, document.timeline).play();
    } catch {
        // Unsupported browsers retain the same complete static border.
    }
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
    move(0, base[0] + (outward ? -.7 : .35));
    move(2, base[2] + (outward ? -.9 : .45));
    rest();
    move(1, base[1] + (outward ? .45 : -.25));
    move(3, base[3] + (outward ? .8 : -.35));
    rest(); rest();
    // Retract each sheet coordinate independently before the next phase.
    base.forEach((value, index) => move(index, value));
    rest();
    // For the constant ambient cycle both excursions are available, with
    // quiet intervals between them; no involuntary jitter or diagonal steps.
    if (direction === 'cycle') {
        move(0, base[0] + .35);
        move(2, base[2] + .45);
        rest();
        move(1, base[1] - .25);
        move(3, base[3] - .35);
        rest(); rest();
        base.forEach((value, index) => move(index, value));
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

// During a taller/shorter artwork swap the two rear paper planes briefly
// open or close together with the 700ms article-height reveal, then settle
// back on the authored background. No new surfaces or image transformations.
export function buildArtworkRevealFrames(start, end, growing) {
    return shadowMotionFrames(start, 'content', growing ? 'outward' : 'inward', end);
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

function animatePaper(element, states, edges, durations, shadow, type) {
    if (!element || !states) return;
    const base = type === 'content' ? FOLIO_VARIANTS : MENU_VARIANTS;
    for (let index = 0; index < 2; index += 1) {
        const columns = index === 0 ? [0, 1] : [2, 3];
        const baseFrame = { top: base[0][columns[0]], bottom: base[0][columns[1]] };
        playCorner(element, index === 0 ? '::before' : '::after', buildCornerFrames(
            states[index], baseFrame, base[1][columns[0]], base[2][columns[1]], edges[index],
        ), durations[index]);
    }
    playShadowBreathing(element, shadow, type);
}

// Height changes belong to the already rendered artwork article, not the
// page's outer pseudo-element frame. Clipping the ARTICLE (not #content)
// reveals the new image at its natural dimensions while the outer paper
// grows/shrinks with its height. No image transform or extra wrapper.
export function animateArtworkHeight(previousHeight, root = document) {
    if (!Number.isFinite(previousHeight) || previousHeight <= 0) return false;
    const article = artworkArticle(root);
    if (!article || typeof article.animate !== 'function') return false;
    const nextHeight = article.getBoundingClientRect().height;
    if (!Number.isFinite(nextHeight) || Math.abs(nextHeight - previousHeight) < 6) return false;

    const originalOverflow = article.style.overflow;
    article.style.overflow = 'clip';
    try {
        const animation = article.animate([
            { height: `${previousHeight}px` },
            { height: `${nextHeight}px` },
        ], {
            duration: 700,
            easing: 'cubic-bezier(.22, .62, .32, 1)',
        });
        const restore = () => { article.style.overflow = originalOverflow; };
        animation.finished.then(restore, restore);
        return true;
    } catch {
        article.style.overflow = originalOverflow;
        return false;
    }
}

// Initial load: native breathing runs without navigating. SPA visits restart
// from the exact rendered coordinates, with artwork height interpolated only
// if BOTH outgoing and incoming pages are individual artwork details.
export function advancePublicFolio(root = document) {
    const content = root.querySelector('#content');
    const menu = root.querySelector('.site-navigation');
    const previous = snapshot;
    snapshot = null;
    const contentShadow = previous?.contentShadow ?? (content && getComputedStyle(content).boxShadow);
    const article = artworkArticle(root);
    const newHeight = article?.getBoundingClientRect().height;
    const priorHeight = previous?.articleHeight;
    const artworkChange = Number.isFinite(priorHeight) && Number.isFinite(newHeight)
        && Math.abs(priorHeight - newHeight) >= 6;
    // Keep one owner of each shadow animation. During the artwork-height
    // build, the 700ms paper-plane reveal temporarily replaces ambient
    // breathing, which resumes only after that short transition completes.
    animatePaper(content, previous?.content ?? pairGeometry(content),
        CONTENT_EDGES, CONTENT_DURATIONS, artworkChange ? null : contentShadow, 'content');
    animatePaper(menu, previous?.menu ?? pairGeometry(menu),
        MENU_EDGES, MENU_DURATIONS,
        previous?.menuShadow ?? (menu && getComputedStyle(menu).boxShadow), 'menu');

    if (artworkChange && animateArtworkHeight(priorHeight, root)) {
        if (!playArtworkBuild(content, contentShadow, newHeight > priorHeight, root)) {
            playShadowBreathing(content, contentShadow, 'content');
        }
    } else if (artworkChange) {
        playShadowBreathing(content, contentShadow, 'content');
    }
    return Boolean(content || menu);
}
