import test from 'node:test';
import assert from 'node:assert/strict';
import { FOLIO_VARIANTS, MENU_VARIANTS, FOLIO_SHADOW_DEPTHS, capturePublicFolio, advancePublicFolio } from '../../resources/js/public-folio-motion.js';

function scene({ nav = true, content = true, hidden = false } = {}) {
    const animations = [];
    const effects = [];
    const computed = new Map();
    const element = (name, before, after) => {
        const node = {
            name,
            computed: {
                '::before': { display: hidden ? 'none' : 'block', content: '""',
                    top: before[0], bottom: before[1] },
                '::after': { display: hidden ? 'none' : 'block', content: '""',
                    top: after[0], bottom: after[1] },
                '': { boxShadow: name === 'content'
                    ? 'rgba(28, 35, 32, 0.09) -2px 3px 0px 0px, rgba(255, 255, 255, 0.31) -5px 7px 0px 0px, rgba(28, 35, 32, 0.12) -6px 8px 0px 0px'
                    : 'none' },
            },
            animate(frames, options) {
                animations.push({ name, frames, options });
                return { frames, options };
            },
        };
        computed.set(node, node.computed);
        return node;
    };
    const painting = content ? element('content', ['18px', '64px'], ['61px', '19px']) : null;
    const menu = nav ? element('menu', ['6px', '-6px'], ['8px', '-7px']) : null;
    const root = { querySelector: selector =>
        selector === '#content' ? painting : selector === '.site-navigation' ? menu : null };
    return { root, painting, menu, animations, effects, computed };
}

function browser(testFn, args) {
    const prev = {};
    for (const key of ['document', 'getComputedStyle', 'KeyframeEffect', 'Animation']) prev[key] = globalThis[key];
    const s = scene(args);
    globalThis.document = { timeline: {} };
    globalThis.getComputedStyle = (node, pseudo) => s.computed.get(node)[pseudo || ''];
    globalThis.KeyframeEffect = class {
        constructor(element, frames, options) {
            Object.assign(this, { element, frames, options });
            s.effects.push(this);
        }
    };
    globalThis.Animation = class {
        constructor(effect, timeline) { Object.assign(this, { effect, timeline }); }
        play() { this.played = true; }
    };
    try { return testFn(s); }
    finally { for (const [key, val] of Object.entries(prev)) globalThis[key] = val; }
}

test('original content and menu contours are first states and every state is bounded', () => {
    assert.equal(FOLIO_VARIANTS.length, 10);
    assert.deepEqual(FOLIO_VARIANTS[0], ['1.10rem', '4.00rem', '3.80rem', '1.15rem']);
    assert.deepEqual(MENU_VARIANTS[0], ['-.76rem', '-.38rem', '-1.1rem', '-.44rem']);
    assert.deepEqual(MENU_VARIANTS.at(-1), MENU_VARIANTS[0]);
    for (const v of FOLIO_VARIANTS) {
        assert.equal(v.length, 4);
        v.forEach((x, i) => {
            assert.match(x, /^\d*\.?\d+rem$/);
            assert.ok(Math.abs(parseFloat(x) - parseFloat(FOLIO_VARIANTS[0][i])) <= .4);
        });
    }
    for (const v of MENU_VARIANTS) {
        assert.equal(v.length, 4);
        assert.ok(v.every(x => /^-?\d*\.?\d+rem$/.test(x)));
        // All four offsets keep the top ends above the menu and preserve
        // one lower connection at each side throughout the animation.
        assert.ok(parseFloat(v[0]) < 0 && parseFloat(v[2]) < 0);
        assert.ok(parseFloat(v[1]) < 0 && parseFloat(v[3]) < 0);
    }
    assert.equal(FOLIO_SHADOW_DEPTHS.length, 10);
    assert.ok(FOLIO_SHADOW_DEPTHS.every(v => v.length === 2 && v.every(n => n >= 5 && n <= 12)));
});

test('initial document starts FOUR infinite pseudo-effects plus existing shadow breath', () => {
    browser(({ root, effects, animations, painting, menu }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects.length, 4);
        assert.equal(animations.length, 1);
        assert.equal(effects.filter(e => e.element === painting).length, 2);
        assert.equal(effects.filter(e => e.element === menu).length, 2);
        assert.deepEqual(effects.map(e => e.options.pseudoElement), ['::before', '::after', '::before', '::after']);
        assert.deepEqual(effects.map(e => e.options.duration), [16000, 19400, 17400, 20800]);
        for (const effect of effects) {
            assert.equal(effect.options.iterations, Infinity);
            assert.deepEqual(effect.frames[0].top, effect.frames.at(-1).top);
            assert.deepEqual(effect.frames[0].bottom, effect.frames.at(-1).bottom);
            assert.ok(effect.frames.some(frame => frame.top !== effect.frames[0].top));
        }
        assert.equal(animations[0].options.iterations, Infinity);
        assert.equal(animations[0].options.duration, 18200);
        assert.equal(animations[0].frames[0].boxShadow, animations[0].frames.at(-1).boxShadow);
        assert.equal(animations[0].frames[0].boxShadow.split(/,\s*(?=rgba\()/).length, 3);
    });
});

test('SPA replacement continues from current rendered paper AND menu geometry', () => {
    browser(({ root }) => assert.equal(capturePublicFolio(root), true));
    browser(({ root, effects, animations }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.deepEqual(effects[0].frames[0], {
            top: '18px', bottom: '64px', offset: 0, easing: 'ease-in-out',
        });
        assert.equal(effects[2].frames[0].top, '6px');
        assert.equal(effects[3].frames[0].bottom, '-7px');
        assert.equal(animations[0].frames[0].boxShadow.includes('rgba'), true);
    });
});

test('missing, hidden or compact mobile contours do not cause animation', () => {
    browser(({ root, effects, animations }) => {
        assert.equal(advancePublicFolio(root), false);
        assert.equal(effects.length, 0);
        assert.equal(animations.length, 0);
    }, { content: false, nav: false });
    browser(({ root, effects }) => {
        assert.equal(advancePublicFolio(root), false);
        assert.equal(effects.length, 0);
    }, { hidden: true });
});

test('unsupported native pseudo effects keep original static drawings', () => {
    browser(({ root, painting, animations }) => {
        globalThis.KeyframeEffect = undefined;
        assert.equal(advancePublicFolio(root), true);
        assert.equal(animations.length, 1);
        assert.equal(painting.computed['::before'].top, '18px');
    });
});
