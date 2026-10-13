import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, chooseNextFolioVariant, capturePublicFolio, advancePublicFolio,
} from '../../resources/js/public-folio-motion.js';

function withPseudoBrowser(run, { reducedMotion = false, initial = null } = {}) {
    const previous = {
        window: globalThis.window,
        document: globalThis.document,
        getComputedStyle: globalThis.getComputedStyle,
        KeyframeEffect: globalThis.KeyframeEffect,
        Animation: globalThis.Animation,
    };
    const effects = [];
    const animations = [];
    const content = {
        style: { values: new Map(), setProperty(name, value) { this.values.set(name, value); } },
        computed: {
            '::before': { display: 'block', content: '""', top: initial?.[0] ?? '18px', bottom: initial?.[1] ?? '64px' },
            '::after': { display: 'block', content: '""', top: initial?.[2] ?? '61px', bottom: initial?.[3] ?? '19px' },
        },
    };
    const root = { querySelector: (selector) => selector === '#content' ? content : null };
    globalThis.window = { matchMedia: () => ({ matches: reducedMotion }) };
    globalThis.document = { timeline: {} };
    globalThis.getComputedStyle = (element, pseudo) => element.computed[pseudo];
    globalThis.KeyframeEffect = class {
        constructor(element, frames, options) { Object.assign(this, { element, frames, options }); effects.push(this); }
    };
    globalThis.Animation = class {
        constructor(effect, timeline) { Object.assign(this, { effect, timeline }); animations.push(this); }
        play() { this.played = true; }
    };
    try { return run({ root, content, effects, animations }); }
    finally { for (const [key, value] of Object.entries(previous)) globalThis[key] = value; }
}

test('all ten destinations keep exactly the original two connected edges', () => {
    assert.equal(FOLIO_VARIANTS.length, 10);
    assert.deepEqual(FOLIO_VARIANTS[0], ['1.10rem', '4.00rem', '3.80rem', '1.15rem']);
    for (const variant of FOLIO_VARIANTS) {
        assert.equal(variant.length, 4);
        assert.ok(variant.every((value) => /^\d*\.?\d+rem$/.test(value) && parseFloat(value) > 0));
        variant.forEach((value, index) => {
            assert.ok(Math.abs(parseFloat(value) - parseFloat(FOLIO_VARIANTS[0][index])) <= .4,
                'original edge endpoints may only move subtly');
        });
    }
});

test('random selection always differs from prior variant', () => {
    for (let prior = 0; prior < 10; prior += 1) {
        for (const random of [0, .1, .25, .5, .8, .99, 1]) {
            const next = chooseNextFolioVariant(prior, () => random);
            assert.ok(next >= 0 && next < 10 && next !== prior);
        }
    }
});

test('SPA visits animate the existing CSS pseudos, never create new nodes', () => {
    withPseudoBrowser(({ root, content, effects, animations }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects.length, 2);
        assert.equal(animations.length, 2);
        assert.ok(animations.every((a) => a.played));
        assert.deepEqual(effects.map((e) => e.options.pseudoElement), ['::before', '::after']);
        assert.equal(effects[0].element, content);
        assert.equal(effects[1].element, content);
        assert.deepEqual(effects[0].frames[0], { top: '1.10rem', bottom: '4.00rem' });
        assert.deepEqual(effects[1].frames[0], { top: '3.80rem', bottom: '1.15rem' });
        assert.equal(effects[0].options.duration, 850);
        assert.deepEqual([...content.style.values.keys()], [
            '--public-folio-left-top', '--public-folio-left-bottom',
            '--public-folio-right-top', '--public-folio-right-bottom',
        ]);
    });
});

test('interrupted navigation starts from the preceding pseudoelements actual intermediate values', () => {
    const captured = ['21px', '69px', '57px', '16px'];
    withPseudoBrowser(({ root }) => {
        assert.equal(capturePublicFolio(root), true);
    }, { initial: captured });

    withPseudoBrowser(({ root, effects }) => {
        assert.equal(advancePublicFolio(root), true);
        assert.deepEqual(effects[0].frames[0], { top: '21px', bottom: '69px' });
        assert.deepEqual(effects[1].frames[0], { top: '57px', bottom: '16px' });
    });
});

test('reduced-motion leaves the original pseudos untouched', () => {
    withPseudoBrowser(({ root, content, effects }) => {
        assert.equal(advancePublicFolio(root), false);
        assert.equal(effects.length, 0);
        assert.equal(content.style.values.size, 0);
    }, { reducedMotion: true });
});

test('hidden mobile contours and missing sheets do not trigger effects', () => {
    withPseudoBrowser(({ root, content, effects }) => {
        content.computed['::before'].display = 'none';
        assert.equal(capturePublicFolio(root), false);
        assert.equal(advancePublicFolio(root), false);
        assert.equal(effects.length, 0);
    });
    withPseudoBrowser(({ effects }) => {
        assert.equal(capturePublicFolio({ querySelector: () => null }), false);
        assert.equal(advancePublicFolio({ querySelector: () => null }), false);
        assert.equal(effects.length, 0);
    });
});

test('unsupported pseudo-element WAAPI retains the existing connected CSS drawing', () => {
    withPseudoBrowser(({ root, content, effects }) => {
        globalThis.KeyframeEffect = undefined;
        assert.equal(advancePublicFolio(root), true);
        assert.equal(effects.length, 0);
        assert.equal(content.style.values.size, 4);
    });
});
