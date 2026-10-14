import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const source = readFileSync(
    new URL('../../../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const heroSource = readFileSync(
    new URL('../../../../resources/js/home-hero.js', import.meta.url), 'utf8',
);
const sheet = (label, height, kind = 'artwork-detail') =>
    '<header class="site-header"><div class="site-navigation"><div class="site-navigation__rail"><nav>' + label + '</nav></div></div></header>'
    + '<main id="content" class="site-content">'
    + '<div class="public-content-reveal"><section class="' + kind + '"'
    + ' style="height:' + height + 'px"><h2>' + label + '</h2></section></div></main>';

async function initialize(page, label = 'Painting', height = 520, kind = 'artwork-detail') {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">'
        + '</head><body class="public-site">' + sheet(label, height, kind) + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: source.replace(/^export /gm, '')
            + '\nwindow.paper = { capturePublicFolio, advancePublicFolio, visibleHeightTransition };',
    });
    await page.evaluate(() => window.paper.advancePublicFolio());
}

test('menu corners and single backing remain close to their surfaces', async ({ page }) => {
    await initialize(page);
    const actual = await page.evaluate(() => {
        const menu=document.querySelector('.site-navigation');
        const rail=menu.querySelector('.site-navigation__rail');
        const left=getComputedStyle(menu,'::before');
        const right=getComputedStyle(menu,'::after');
        const backing=getComputedStyle(rail,'::before');
        const effects=document.getAnimations().filter(a=>a.effect.target===menu);
        return {
            left:parseFloat(left.left),right:parseFloat(right.right),
            top:parseFloat(backing.top),backLeft:parseFloat(backing.left),
            backRight:parseFloat(backing.right),backBottom:parseFloat(backing.bottom),
            menuShadow:getComputedStyle(menu).boxShadow,
            cornerFrames:effects.filter(a=>a.effect.pseudoElement)
                .map(a=>({duration:a.effect.getTiming().duration,frames:a.effect.getKeyframes()})),
            backingDurations:effects.filter(a=>!a.effect.pseudoElement)
                .map(a=>a.effect.getTiming().duration),
        };
    });
    expect(actual.left).toBeCloseTo(-6,1);
    expect(actual.right).toBeCloseTo(-5.5,1);
    expect(actual.top).toBe(0);
    expect(actual.backLeft).toBeCloseTo(-3,1);
    expect(actual.backRight).toBeCloseTo(3,1);
    expect(actual.backBottom).toBeCloseTo(-4,1);
    expect(actual.menuShadow).toContain('inset');
    expect(actual.cornerFrames.map(f=>f.duration)).toEqual([117000,133000]);
    expect(actual.backingDurations).toEqual([145000]);
    for(const {frames} of actual.cornerFrames){
        expect(frames.every(f=>parseFloat(f.top)===0)).toBe(true);
        expect(frames.every(f=>f.left===frames[0].left&&f.right===frames[0].right)).toBe(true);
        expect(frames.at(-1).bottom).toBe(frames[0].bottom);
    }
});

for (const scenario of [
    { from: ['Exhibitions', 330, 'exhibitions-page'], to: ['Painting', 810, 'artwork-detail'] },
    { from: ['Blog', 870, 'blog-page'], to: ['Home', 300, 'home-components'] },
    { from: ['Gallery', 9000, 'artwork-list'], to: ['Home', 280, 'home-components'] },
    { from: ['Gallery', 9000, 'artwork-list'], to: ['CV', 350, 'custom-page'] },
    { from: ['Gallery', 9000, 'artwork-list'], to: ['Blog', 7000, 'blog-page'] },
    { from: ['CV', 450, 'custom-page'], to: ['Gallery', 450, 'artwork-list'] },
]) {
    test(`all-page paper build: ${scenario.from[0]} -> ${scenario.to[0]}`, async ({ page }) => {
        await initialize(page, ...scenario.from);
        const result = await page.evaluate((nextMarkup) => {
            const oldHeight = document.querySelector('.public-content-reveal').getBoundingClientRect().height;
            window.paper.capturePublicFolio();
            document.body.innerHTML = nextMarkup;
            const newBody = document.querySelector('.public-content-reveal');
            const frameBudget = window.paper.visibleHeightTransition(
                oldHeight, newBody.getBoundingClientRect().height,
                window.innerHeight, newBody.getBoundingClientRect().top);
            window.paper.advancePublicFolio();
            const content = document.querySelector('#content');
            const inner = content.querySelector('.public-content-reveal');
            const menu = document.querySelector('.site-navigation');
            const animations = document.getAnimations();
            const height = animations.find(a => a.effect.target === inner);
            const pulse = animations.filter(a => a.effect.target === content);
            const menuPulse = animations.filter(a => a.effect.target === menu);
            return {
                oldHeight,
                frameBudget,
                naturalHeight: inner.querySelector('section').getBoundingClientRect().height,
                viewportHeight: window.innerHeight,
                innerOverflow: getComputedStyle(inner).overflow,
                paperOverflow: getComputedStyle(content).overflow,
                heightFrames: height?.effect.getKeyframes(),
                heightDuration: height?.effect.getTiming().duration,
                pulseDurations: pulse.map(a => a.effect.getTiming().duration),
                menuPulseDurations: menuPulse.map(a => a.effect.getTiming().duration),
                childCount: content.children.length,
            };
        }, sheet(...scenario.to));
        expect(result.childCount).toBe(1);
        expect(result.paperOverflow).toBe('visible');
        expect(result.pulseDurations).toEqual([620, 620, 620]);
        // A SPA swap cannot force the menu into a new assembly impulse.
        expect(result.menuPulseDurations).toEqual([117000, 133000, 145000]);
        if (Math.abs(result.frameBudget.from - result.frameBudget.to) >= 7) {
            expect(result.innerOverflow).toBe('clip');
            expect(result.heightDuration).toBe(480);
            expect(parseFloat(result.heightFrames[0].height))
                .toBeCloseTo(result.frameBudget.from, 0);
            expect(parseFloat(result.heightFrames[1].height))
                .toBeCloseTo(result.frameBudget.to, 0);
            expect(result.frameBudget.from)
                .toBeLessThanOrEqual(result.viewportHeight + 48);
            expect(result.frameBudget.to)
                .toBeLessThanOrEqual(result.viewportHeight + 48);
        } else {
            expect(result.heightFrames).toBeUndefined();
        }
        await expect.poll(() => page.evaluate(() => document.getAnimations()
            .filter(a => a.effect.target === document.querySelector('#content')
                && a.effect.getTiming().iterations === Infinity).length)).toBe(3);
        await expect.poll(() => page.evaluate(() => document.getAnimations()
            .filter(a => a.effect.target === document.querySelector('.site-navigation')
                && a.effect.getTiming().iterations === Infinity).length)).toBe(3);
        if (Math.abs(result.frameBudget.from - result.frameBudget.to) >= 7) {
            await expect(page.locator('.public-content-reveal')).toHaveCSS('overflow', 'visible');
        }
    });
}

test('Home Hero arrows animate the same paper/content, without touching the menu', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const heroHtml = '<header class="site-header"><div class="site-navigation"><div class="site-navigation__rail"><nav>Home</nav></div></div></header>'
        + '<main id="content" class="site-content"><div class="public-content-reveal">'
        + '<div data-home-hero>'
        + '<div data-home-hero-slide><article class="artwork-card" style="height:380px">'
        + '<button type="button" data-home-hero-direction="next">Next</button></article></div>'
        + '<div data-home-hero-slide hidden><article class="artwork-card" style="height:730px">'
        + '<button type="button" data-home-hero-direction="next">Next</button></article></div>'
        + '</div></div></main>';
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">'
        + '</head><body class="public-site">' + heroHtml + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: source.replace(/^export /gm, '')
            + '\nwindow.paper = { advancePublicFolio, animatePublicHeroChange };',
    });
    await page.addScriptTag({
        content: heroSource.replace(/^export /gm, '')
            + '\ninitializeHomeHero(document, change => window.paper.animatePublicHeroChange(change));',
    });
    await page.evaluate(() => window.paper.advancePublicFolio());

    const oldMenu = await page.evaluate(() => document.getAnimations()
        .filter(a => a.effect.target === document.querySelector('.site-navigation'))
        .map(a => ({ duration: a.effect.getTiming().duration,
            iterations: a.effect.getTiming().iterations })));
    await page.locator('[data-home-hero-slide]:not([hidden]) button[data-home-hero-direction="next"]').click();
    const result = await page.evaluate(() => {
        const main = document.querySelector('#content');
        const menu = document.querySelector('.site-navigation');
        const inner = document.querySelector('.public-content-reveal');
        const effects = document.getAnimations();
        const paper = effects.filter(a => a.effect.target === main);
        const nav = effects.filter(a => a.effect.target === menu);
        return {
            visible: [...document.querySelectorAll('[data-home-hero-slide]')]
                .map(slide => !slide.hidden),
            paper: paper.map(a => a.effect.getTiming().duration),
            menu: nav.map(a => ({ duration: a.effect.getTiming().duration,
                iterations: a.effect.getTiming().iterations })),
            height: effects.filter(a => a.effect.target === inner)
                .map(a => a.effect.getTiming().duration),
        };
    });
    expect(result.visible).toEqual([false, true]);
    expect(result.paper).toEqual([620, 620, 620]);
    expect(result.height).toEqual([480]);
    expect(result.menu).toEqual(oldMenu);

    // The next click starts from an in-flight corner, but the END keyframe
    // must be the destination's original CSS geometry. This specifically
    // catches the old accumulated excursion/snap-on-finish regression.
    const reference = await page.evaluate(() => {
        const sheet = document.querySelector('#content');
        return {
            before: getComputedStyle(sheet, '::before').top,
            after: getComputedStyle(sheet, '::after').bottom,
        };
    });
    await page.locator('[data-home-hero-slide]:not([hidden]) button[data-home-hero-direction="next"]').click();
    const afterFastClick = await page.evaluate(() => {
        const sheet = document.querySelector('#content');
        return document.getAnimations()
            .filter(a => a.effect.target === sheet && a.effect.pseudoElement)
            .map(a => ({
                pseudo: a.effect.pseudoElement,
                first: a.effect.getKeyframes()[0],
                last: a.effect.getKeyframes().at(-1),
                out: a.effect.getKeyframes().map(f =>
                    CSSNumericValue.parse(f.left ?? f.right).to('px').value),
            }));
    });
    expect(afterFastClick).toHaveLength(2);
    const left = afterFastClick.find(a => a.pseudo === '::before');
    const right = afterFastClick.find(a => a.pseudo === '::after');
    expect(left.last.top).toBeTruthy();
    expect(right.last.bottom).toBeTruthy();
    expect(left.last.left).toBe('-7px');
    expect(right.last.right).toBe('-6px');
    // Compare the actual excursion; CSSOM may simplify equivalent calc syntax.
    expect(Math.min(...left.out)).toBeCloseTo(parseFloat(left.last.left) - 0.7, 5);
    expect(Math.min(...right.out)).toBeCloseTo(parseFloat(right.last.right) - 0.7, 5);
    expect(reference.before).toBeTruthy();
    expect(reference.after).toBeTruthy();
    await expect.poll(() => page.evaluate(() =>
        document.getAnimations().filter(a => a.effect.target === document.querySelector('#content')
            && a.effect.getTiming().iterations === 1).length)).toBe(3);
    await expect.poll(() => page.evaluate(() =>
        document.getAnimations().filter(a => a.effect.target === document.querySelector('.site-navigation')
            && a.effect.getTiming().iterations === 1).length)).toBe(0);
});

test('a rapid second page switch continues the interrupted build from its actual position', async ({ page }) => {
    await initialize(page, 'Exhibitions', 340, 'exhibitions-page');
    const result = await page.evaluate(async ({ first, second }) => {
        window.paper.capturePublicFolio();
        document.body.innerHTML = first;
        window.paper.advancePublicFolio();

        // Align with a real early second navigation rather than waiting for
        // the paper to settle; two animation frames pass without any timer.
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const current = document.querySelector('#content');
        const oldTop = getComputedStyle(current, '::before').top;
        const oldShadow = getComputedStyle(current).boxShadow;
        const oldBodyHeight = document.querySelector('.public-content-reveal').getBoundingClientRect().height;

        window.paper.capturePublicFolio();
        document.body.innerHTML = second;
        window.paper.advancePublicFolio();

        const content = document.querySelector('#content');
        const wrapper = content.querySelector('.public-content-reveal');
        const effects = document.getAnimations();
        const left = effects.find(a => a.effect.target === content && a.effect.pseudoElement === '::before');
        const paper = effects.find(a => a.effect.target === content && !a.effect.pseudoElement);
        const height = effects.find(a => a.effect.target === wrapper);
        return {
            oldTop, oldShadow, oldBodyHeight,
            nextTop: left?.effect.getKeyframes()[0].top,
            nextShadow: paper?.effect.getKeyframes()[0].boxShadow,
            nextHeight: height?.effect.getKeyframes()[0].height,
            count: document.querySelectorAll('#content > .public-content-reveal').length,
        };
    }, {
        first: sheet('Painting', 640, 'artwork-detail'),
        second: sheet('Journal', 850, 'blog-page'),
    });
    expect(result.count).toBe(1);
    expect(result.nextTop).toBe(result.oldTop);
    const numbers = shadow => [...shadow.matchAll(/-?\d+(?:\.\d+)?px/g)]
        .map(match => parseFloat(match[0]));
    expect(numbers(result.oldShadow)).not.toHaveLength(0);
    expect(numbers(result.nextShadow)).toEqual(numbers(result.oldShadow));
    expect(parseFloat(result.nextHeight)).toBeCloseTo(result.oldBodyHeight, 0);
});
