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
    ['.03rem', '.05rem', '.02rem', '.05rem'],
    ['.10rem', '.05rem', '.09rem', '.05rem'],
    ['.10rem', '.085rem', '.09rem', '.085rem'],
]);

// Original horizontal geometry belongs to the accepted shell CSS. These
// values mirror its fixed edges and make the outside/inside motion bounded.
const CONTENT_EDGES = [
    { side: 'left', offset: '-.7rem', width: '.55rem' },
    { side: 'right', offset: '-.55rem', width: '.55rem' },
];
const MENU_EDGES = [
    { side: 'left', offset: '-.5rem', width: '.76rem' },
    { side: 'right', offset: '-.52rem', width: '.76rem' },
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
        `${middleX}px ${middleY}px 0 2px var(--public-folio-depth)`,
        `${rearX}px ${rearY}px 0 2px var(--public-folio-soft)`,
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
    step({ top: base.top });
    step({ bottom: base.bottom });
    step({
        [edge.side]: `calc(${edge.offset} - 1.5px)`,
        width: `calc(${edge.width} + 1.5px)`,
    });
    step({ top: nextTop });
    step({ bottom: nextBottom });
    step({
        [edge.side]: `calc(${edge.offset} + 1px)`,
        width: `calc(${edge.width} - 1px)`,
    });
    step({ top: base.top });
    step({ bottom: base.bottom });
    step({ [edge.side]: start[edge.side], width: start.width });
    step({ top: start.top });
    step({ bottom: start.bottom });
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

// The two existing rear paper planes travel left/down or right/up by 1–2px.
// EVERY transition segment changes one x or y coordinate, even if a SPA
// navigation interrupted the preceding plane animation mid-movement.
export function buildShadowFrames(start, type) {
    const shadow = SHADOW_START[type];
    const initial = type === 'content' ? [-5, 7, -8, 10] : [-2, 2, -5, 4];
    const rendered = renderedShadowCoords(start) ?? initial;
    const coords = [...rendered];
    const frames = [start];
    const move = (index, value) => {
        if (coords[index] === value) return;
        coords[index] = value;
        frames.push(shadow(...coords));
    };
    // Realign a captured intermediate state one axis at a time.
    initial.forEach((value, index) => move(index, value));
    if (frames.length === 1) frames.push(shadow(...coords));
    move(0, initial[0] - 1); // middle layer outward, x
    move(2, initial[2] - 1); // rear layer outward, x
    move(1, initial[1] + 1); // middle layer down, y
    move(3, initial[3] + 2); // rear layer down, y
    move(0, initial[0]);     // middle layer inward, x
    move(2, initial[2]);     // rear layer inward, x
    move(1, initial[1]);     // middle layer up, y
    move(3, initial[3]);     // rear layer up, y
    rendered.forEach((value, index) => move(index, value));
    frames.push(start);      // same computed starting state at loop boundary
    return frames.map((boxShadow, index) => ({
        boxShadow, offset: index / (frames.length - 1), easing: 'ease-in-out',
    }));
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
    if (shadow && typeof element.animate === 'function') {
        try {
            element.animate(buildShadowFrames(shadow, type), {
                duration: type === 'content' ? 31200 : 32400,
                iterations: Infinity,
            });
        } catch {
            // Static native paper layers remain visible.
        }
    }
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
    animatePaper(content, previous?.content ?? pairGeometry(content),
        CONTENT_EDGES, CONTENT_DURATIONS,
        previous?.contentShadow ?? (content && getComputedStyle(content).boxShadow), 'content');
    animatePaper(menu, previous?.menu ?? pairGeometry(menu),
        MENU_EDGES, MENU_DURATIONS,
        previous?.menuShadow ?? (menu && getComputedStyle(menu).boxShadow), 'menu');
    if (previous?.articleHeight != null) animateArtworkHeight(previous.articleHeight, root);
    return Boolean(content || menu);
}
