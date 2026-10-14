import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, MENU_VARIANTS, FOLIO_PLANE_BASE,
    buildCornerFrames, buildShadowFrames, buildArtworkRevealFrames,
    capturePublicFolio, advancePublicFolio, animateArtworkHeight,
} from '../../resources/js/public-folio-motion.js';

function scene({ content = true, menu = true, hidden = false, detailHeight = null } = {}) {
    const effects = [], animations = [];
    const computed = new Map();
    const fake = (name, before, after, shadow) => {
        const node = {
            name,
            computed: {
                '::before': { display: hidden ? 'none' : 'block', content: '""',
                    top: before[0], bottom: before[1], left: before[2], width: before[3] },
                '::after': { display: hidden ? 'none' : 'block', content: '""',
                    top: after[0], bottom: after[1], right: after[2], width: after[3] },
                '': { boxShadow: shadow },
            },
            animate(frames, options) {
                const animation = { name, frames, options,
                    finished: { then(done) { animation.complete = done; } } };
                animations.push(animation);
                return animation;
            },
        };
        computed.set(node, node.computed);
        return node;
    };
    const painting = content ? fake('content',
        ['18px', '64px', '-11.2px', '8.8px'],
        ['61px', '19px', '-8.8px', '8.8px'],
        'rgba(28, 35, 32, 0.09) -2px 3px 0px 0px, rgba(255, 255, 255, 0.31) -5px 7px 0px 0px, rgba(28, 35, 32, 0.12) -8px 10px 0px 0px') : null;
    const navigation = menu ? fake('menu',
        ['.48px', '.8px', '-8px', '12.16px'],
        ['.32px', '.8px', '-8.32px', '12.16px'],
        'rgba(65, 70, 65, 0.22) 0px -1px 0px 0px inset, rgba(28, 35, 32, 0.12) -2px 2px 0px 2px, rgba(255, 255, 255, 0.31) -5px 4px 0px 2px') : null;
    let article = null;
    if (detailHeight !== null) {
        article = {
            style: { overflow: '' },
            getBoundingClientRect() { return { height: detailHeight }; },
            animate(frames, options) {
                const animation = {
                    name: 'article', frames, options,
                    finished: { then(done) { animation.complete = done; } },
                };
                animations.push(animation);
                return animation;
            },
        };
    }
    const root = { querySelector: selector => ({
        '#content': painting,
        '.site-navigation': navigation,
        '#content > .artwork-detail': article,
    })[selector] ?? null };
    return { root, painting, navigation, article, effects, animations, computed };
}
function withBrowser(run, options) {
    const prior = {};
    for (const key of ['document', 'getComputedStyle', 'KeyframeEffect', 'Animation']) prior[key] = globalThis[key];
    const fixture = scene(options);
    globalThis.document = { timeline: {} };
    globalThis.getComputedStyle = (el, pseudo) => fixture.computed.get(el)[pseudo || ''];
    globalThis.KeyframeEffect = class {
        constructor(element, frames, options) { Object.assign(this, { element, frames, options }); fixture.effects.push(this); }
    };
    globalThis.Animation = class {
        constructor(effect, timeline) { Object.assign(this, { effect, timeline }); }
        play() { this.played = true; }
    };
    try { return run(fixture); } finally {
        for (const [key, value] of Object.entries(prior)) globalThis[key] = value;
    }
}

test('accepted first folio and menu remain the exact static geometries', () => {
    assert.deepEqual(FOLIO_VARIANTS[0], ['1.10rem', '4rem', '3.8rem', '1.15rem']);
    assert.deepEqual(MENU_VARIANTS[0], ['.03rem', '.05rem', '.02rem', '.05rem']);
    assert.ok(MENU_VARIANTS.every(v => v.every(x => parseFloat(x) >= 0)));
    assert.deepEqual(FOLIO_PLANE_BASE.content, [-5, 7, -8, 10]);
    assert.deepEqual(FOLIO_PLANE_BASE.menu, [-2, 2, -5, 4]);
    assert.ok(MENU_VARIANTS.every(v => parseFloat(v[0]) >= 0 && parseFloat(v[2]) >= 0));
});

test('each animated corner step moves only vertically or horizontally, never diagonally', () => {
    const a = { top: '18px', bottom: '64px', left: '-11.2px', width: '8.8px' };
    const frames = buildCornerFrames(a,
        { top: '1.1rem', bottom: '4rem' }, '1.25rem', '4.14rem',
        { side: 'left', offset: '-.7rem', width: '.55rem' });
    assert.deepEqual(frames[0], { ...a, offset: 0, easing: 'ease-in-out' });
    assert.equal(frames.at(-1).top, a.top);
    assert.equal(frames.at(-1).bottom, a.bottom);
    assert.equal(frames.at(-1).left, a.left);
    assert.equal(frames.at(-1).width, a.width);
    for (let i = 1; i < frames.length; i += 1) {
        const prev = frames[i - 1], next = frames[i];
        const v = prev.top !== next.top || prev.bottom !== next.bottom;
        const h = prev.left !== next.left || prev.width !== next.width;
        assert.equal(v && h, false, 'a keyframe must never move diagonally');
        assert.ok(next.offset > prev.offset);
    }
    assert.ok(frames.some((f, i) => i > 0 && f.top === frames[i - 1].top
        && f.bottom === frames[i - 1].bottom && f.left === frames[i - 1].left),
        'ambient motion contains genuine resting intervals');
    const outside = frames.find(v => v.left === 'calc(-.7rem - 1.5px)');
    assert.equal(outside.width, 'calc(.55rem + 1.5px)');
    const inside = frames.find(v => v.left === 'calc(-.7rem + 1px)');
    assert.equal(inside.width, 'calc(.55rem - 1px)');
});

function positions(value) {
    const chunks = [];
    let depth = 0, from = 0;
    for (let i = 0; i < value.length; i += 1) {
        if (value[i] === '(') depth++;
        else if (value[i] === ')') depth--;
        else if (value[i] === ',' && depth === 0) {
            chunks.push(value.slice(from, i)); from = i + 1;
        }
    }
    chunks.push(value.slice(from));
    assert.equal(chunks.length, 3);
    return chunks.slice(1).flatMap(layer => [...layer.matchAll(/(-?\d+(?:\.\d+)?)px/g)]
        .slice(0, 2).map(m => Number(m[1])));
}

function assertVisibleLayers(frames, type) {
    for (const frame of frames) {
        const [middleX, middleY, rearX, rearY] = positions(frame.boxShadow);
        assert.ok(rearX <= middleX - 2, 'rear sheet must show on the left');
        assert.ok(rearY >= middleY + 1.5, 'rear sheet must show below the middle sheet');
        if (type === 'menu') {
            assert.ok(middleX <= -1.5 && middleY >= 1.5,
                'front paper must remain outside white and visible at menu bottom');
            assert.ok(rearY + 2 >= 4, 'rear menu paper must remain exposed below');
        }
    }
}

test('paper planes keep visible outer margins and move only one axis per step', () => {
    for (const type of ['content', 'menu']) {
        const sample = type === 'content'
            ? 'rgba(28, 35, 32, 0.09) -2px 3px 0px 0px, rgba(255, 255, 255, 0.31) -5px 7px 0px 0px, rgba(28, 35, 32, 0.12) -8px 10px 0px 0px'
            : 'rgba(65, 70, 65, 0.22) 0px -1px 0px 0px inset, rgba(28, 35, 32, 0.12) -2px 2px 0px 2px, rgba(255, 255, 255, 0.31) -5px 4px 0px 2px';
        const frames = buildShadowFrames(sample, type);
        assert.equal(frames[0].boxShadow, sample);
        assert.equal(frames.at(-1).boxShadow, sample);
        assertVisibleLayers(frames, type);
        assert.ok(frames.some((v, i) => i > 0 && v.boxShadow === frames[i - 1].boxShadow),
            'a sheet must sometimes rest');
        for (let i = 1; i < frames.length; i += 1) {
            const before = positions(frames[i - 1].boxShadow);
            const after = positions(frames[i].boxShadow);
            assert.ok(before.filter((x, j) => x !== after[j]).length <= 1,
                'only one horizontal or vertical coordinate changes per step');
        }
    }
});

test('an interrupted shadow resumes axis by axis, never covering the other sheet', () => {
    const start = 'rgba(28, 35, 32, .09) -2px 3px 0px 0px, rgba(255, 255, 255, .31) -5.5px 7.5px 0px 0px, rgba(28, 35, 32, .12) -8.4px 10.7px 0px 0px';
    const frames = buildShadowFrames(start, 'content');
    assertVisibleLayers(frames, 'content');
    for (let i = 1; i < frames.length; i++) {
        assert.ok(positions(frames[i - 1].boxShadow).filter(
            (x, j) => x !== positions(frames[i].boxShadow)[j]).length <= 1);
    }
});

test('growth and shrink have distinct brief paper-build directions', () => {
    const sample = 'rgba(28, 35, 32, .09) -2px 3px 0px 0px, rgba(255, 255, 255, .31) -5px 7px 0px 0px, rgba(28, 35, 32, .12) -8px 10px 0px 0px';
    const growing = buildArtworkRevealFrames(sample, sample, true);
    const shrinking = buildArtworkRevealFrames(sample, sample, false);
    assertVisibleLayers(growing, 'content');
    assertVisibleLayers(shrinking, 'content');
    assert.ok(Math.min(...growing.map(f => positions(f.boxShadow)[0])) < -5);
    assert.ok(Math.max(...shrinking.map(f => positions(f.boxShadow)[0])) > -5);
    for (const frames of [growing, shrinking]) {
        assert.equal(frames[0].boxShadow, sample);
        assert.equal(frames.at(-1).boxShadow, sample);
    }
});

test('both paper surfaces breathe continuously from the initial page', () => {
    withBrowser(({ root, effects, animations, painting, navigation }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects.length, 4);
        assert.equal(effects.filter(e => e.element === painting).length, 2);
        assert.equal(effects.filter(e => e.element === navigation).length, 2);
        assert.ok(effects.every(e => e.options.iterations === Infinity));
        assert.deepEqual(effects.map(e => e.options.duration), [28600, 31800, 27000, 30900]);
        assert.equal(animations.length, 2);
        assert.ok(animations.every(a => a.options.iterations === Infinity));
        assert.deepEqual(animations.map(a => a.options.duration), [34800, 36400]);
    });
});

test('a DOM swap continues from in-flight geometry without creating other lines', () => {
    withBrowser(({ root }) => assert.equal(capturePublicFolio(root), true), { detailHeight: 740 });
    withBrowser(({ root, effects, animations }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects[0].frames[0].top, '18px');
        assert.equal(effects[2].frames[0].top, '.48px');
        assert.equal(animations.length, 2);
    });
});

test('a real detail-to-detail SPA swap grows and shrinks from the outgoing rendered article height', () => {
    withBrowser(({ root }) => {
        assert.equal(capturePublicFolio(root), true);
    }, { detailHeight: 740 });
    withBrowser(({ root, article, animations }) => {
        assert.equal(advancePublicFolio(root), true);
        const reveal = animations.find(a => a.name === 'article');
        const paperBuild = animations.find(a => a.name === 'content' && a.options.duration === 700);
        assert.ok(reveal);
        assert.ok(paperBuild);
        assert.equal(animations.filter(a => a.name === 'content').length, 1,
            'ambient shadow must wait until the build finishes');
        assert.deepEqual(reveal.frames, [{ height: '740px' }, { height: '420px' }]);
        assert.equal(article.style.overflow, 'clip');
        reveal.complete();
        paperBuild.complete();
        assert.equal(article.style.overflow, '');
        assert.equal(animations.filter(a => a.name === 'content').length, 2,
            'ambient breathing resumes after the build');
        assert.equal(animations.at(-1).options.iterations, Infinity);
    }, { detailHeight: 420 });

    withBrowser(({ root }) => assert.equal(capturePublicFolio(root), true), { detailHeight: 360 });
    withBrowser(({ root, animations }) => {
        advancePublicFolio(root);
        assert.deepEqual(animations.find(a => a.name === 'article').frames, [
            { height: '360px' }, { height: '880px' },
        ]);
    }, { detailHeight: 880 });
});

test('detail-to-detail page height reveals the next image without scaling or extra wrappers', () => {
    withBrowser(({ root, article, animations }) => {
        assert.equal(animateArtworkHeight(780, root), true);
        assert.equal(article.style.overflow, 'clip');
        assert.equal(animations.length, 1);
        assert.deepEqual(animations[0].frames, [{ height: '780px' }, { height: '480px' }]);
        assert.equal(animations[0].options.duration, 700);
        animations[0].complete();
        assert.equal(article.style.overflow, '');
    }, { detailHeight: 480 });
});

test('equal-height/non-detail navigation leaves the normal responsive layout untouched', () => {
    withBrowser(({ root }) => assert.equal(animateArtworkHeight(480, root), false));
    withBrowser(({ root }) => assert.equal(animateArtworkHeight(482, root), false), { detailHeight: 480 });
    withBrowser(({ root }) => assert.equal(animateArtworkHeight(null, root), false), { detailHeight: 480 });
});

test('mobile hidden paper or missing nodes have no effects', () => {
    withBrowser(({ root, effects, animations }) => {
        assert.equal(advancePublicFolio(root), false);
        assert.equal(effects.length, 0);
        assert.equal(animations.length, 0);
    }, { content: false, menu: false });
    withBrowser(({ root, effects, animations }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects.length, 0);
        assert.equal(animations.length, 0);
    }, { hidden: true });
});
