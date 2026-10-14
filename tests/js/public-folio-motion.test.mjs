import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, MENU_VARIANTS, buildCornerFrames, buildShadowFrames,
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
                animations.push({ name, frames, options });
                return { finished: { then(done) { this.complete = done; } } };
            },
        };
        computed.set(node, node.computed);
        return node;
    };
    const painting = content ? fake('content',
        ['18px', '64px', '-11.2px', '8.8px'],
        ['61px', '19px', '-8.8px', '8.8px'],
        'rgba(28, 35, 32, 0.09) -2px 3px 0px 0px, rgba(255, 255, 255, 0.31) -5px 7px 0px 0px, rgba(28, 35, 32, 0.12) -6px 8px 0px 0px') : null;
    const navigation = menu ? fake('menu',
        ['.48px', '-4px', '-8px', '12.16px'],
        ['.32px', '-4.8px', '-8.32px', '12.16px'],
        'rgba(65, 70, 65, 0.22) 0px -1px 0px 0px inset, rgba(28, 35, 32, 0.12) -2px 0px 0px 0px, rgba(255, 255, 255, 0.31) -3px 0px 0px 0px') : null;
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
    assert.deepEqual(MENU_VARIANTS[0], ['.03rem', '-.25rem', '.02rem', '-.30rem']);
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
    const outside = frames.find(v => v.left === 'calc(-.7rem - 1.5px)');
    assert.equal(outside.width, 'calc(.55rem + 1.5px)');
    const inside = frames.find(v => v.left === 'calc(-.7rem + 1px)');
    assert.equal(inside.width, 'calc(.55rem - 1px)');
});

test('both existing paper planes move one axis at a time and end at the start', () => {
    for (const type of ['content', 'menu']) {
        const frames = buildShadowFrames('the originally rendered shadow', type);
        assert.equal(frames[0].boxShadow, frames.at(-1).boxShadow);
        assert.equal(frames.at(-1).offset, 1);
        for (let i = 2; i < frames.length - 1; i += 1) {
            const prev = frames[i - 1].boxShadow, next = frames[i].boxShadow;
            const xy = x => [...x.matchAll(/(-?\d+)px (-?\d+)px 0 var\(/g)]
                .flatMap(m => [Number(m[1]), Number(m[2])]);
            const before = xy(prev), after = xy(next);
            assert.equal(before.length, 4);
            assert.equal(after.length, 4);
            assert.equal(before.filter((x, j) => x !== after[j]).length, 1,
                'only one existing paper-plane coordinate may change per step');
        }
    }
});

test('captured mid-flight paper shadows return to base one axis at a time', () => {
    const start = 'rgba(28, 35, 32, .09) -2px 3px 0px 0px, rgba(255, 255, 255, .31) -6.5px 8px 0px 0px, rgba(28, 35, 32, .12) -7px 9.5px 0px 0px';
    const frames = buildShadowFrames(start, 'content');
    assert.equal(frames[0].boxShadow, start);
    assert.equal(frames.at(-1).boxShadow, start);
    const coordinates = string => {
        const matches = [...string.matchAll(/(-?\d+(?:\.\d+)?)px\s+(-?\d+(?:\.\d+)?)px\s+0(?:px)?/g)];
        return matches.slice(-2).flatMap(m => [Number(m[1]), Number(m[2])]);
    };
    for (let i = 1; i < frames.length - 1; i += 1) {
        const previous = coordinates(frames[i - 1].boxShadow);
        const next = coordinates(frames[i].boxShadow);
        assert.equal(previous.length, 4);
        assert.equal(next.length, 4);
        assert.ok(previous.filter((v, j) => v !== next[j]).length <= 1,
            'mid-visit shadow motion must never change x and y together');
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
        assert.ok(reveal);
        assert.deepEqual(reveal.frames, [{ height: '740px' }, { height: '420px' }]);
        assert.equal(article.style.overflow, 'clip');
        reveal.complete();
        assert.equal(article.style.overflow, '');
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
