import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const source = readFileSync(
    new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const sheet = (label, height, kind = 'artwork-detail') =>
    '<header class="site-header"><div class="site-navigation"><nav>' + label + '</nav></div></header>'
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
            + '\nwindow.paper = { capturePublicFolio, advancePublicFolio };',
    });
    await page.evaluate(() => window.paper.advancePublicFolio());
}

test('only the existing menu strokes and rear paper planes show, without overshooting', async ({ page }) => {
    await initialize(page);
    const result = await page.evaluate(() => {
        const menu = document.querySelector('.site-navigation');
        const content = document.querySelector('#content');
        const left = getComputedStyle(menu, '::before');
        const right = getComputedStyle(menu, '::after');
        const effects = document.getAnimations();
        const menuShadow = getComputedStyle(menu).boxShadow;
        return {
            children: content.children.length,
            topBorders: [left.borderTopStyle, right.borderTopStyle],
            bottomBorders: [left.borderBottomStyle, right.borderBottomStyle],
            top: [parseFloat(left.top), parseFloat(right.top)],
            bottom: [parseFloat(left.bottom), parseFloat(right.bottom)],
            outside: [parseFloat(left.left), parseFloat(right.right)],
            menuShadow,
            animations: effects.map(a => ({
                target: a.effect.target === content ? 'content' :
                    a.effect.target === menu ? 'menu' : 'other',
                pseudo: a.effect.pseudoElement || null,
                iterations: a.effect.getTiming().iterations,
            })),
        };
    });
    expect(result.children).toBe(1);
    expect(result.topBorders).toEqual(['none', 'none']);
    expect(result.bottomBorders).toEqual(['solid', 'solid']);
    expect(result.top.every(n => n >= 0)).toBe(true);
    expect(result.bottom.every(n => n >= 0)).toBe(true);
    expect(result.outside.every(n => n < 0 && n > -7)).toBe(true);
    expect(result.animations.filter(a => a.pseudo)).toHaveLength(4);
    expect(result.animations.filter(a => a.target !== 'other' && !a.pseudo)).toHaveLength(2);
    expect(result.animations.every(a => a.iterations === Infinity)).toBe(true);
});

for (const scenario of [
    { from: ['Exhibitions', 330, 'exhibitions-page'], to: ['Painting', 810, 'artwork-detail'] },
    { from: ['Blog', 870, 'blog-page'], to: ['Home', 300, 'home-components'] },
    { from: ['CV', 450, 'custom-page'], to: ['Gallery', 450, 'artwork-list'] },
]) {
    test(`all-page paper build: ${scenario.from[0]} -> ${scenario.to[0]}`, async ({ page }) => {
        await initialize(page, ...scenario.from);
        const result = await page.evaluate((nextMarkup) => {
            const oldHeight = document.querySelector('.public-content-reveal').getBoundingClientRect().height;
            window.paper.capturePublicFolio();
            document.body.innerHTML = nextMarkup;
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
                naturalHeight: inner.querySelector('section').getBoundingClientRect().height,
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
        expect(result.pulseDurations).toEqual([1480, 1480, 1480]);
        expect(result.menuPulseDurations).toEqual([980, 980, 980]);
        if (scenario.from[1] !== scenario.to[1]) {
            expect(result.innerOverflow).toBe('clip');
            expect(result.heightDuration).toBe(980);
            expect(parseFloat(result.heightFrames[0].height)).toBeCloseTo(result.oldHeight, 0);
            expect(parseFloat(result.heightFrames[1].height)).toBeCloseTo(result.naturalHeight, 0);
        } else {
            expect(result.heightFrames).toBeUndefined();
        }
        await page.waitForTimeout(1550);
        await expect.poll(() => page.evaluate(() => document.getAnimations()
            .filter(a => a.effect.target === document.querySelector('#content')
                && a.effect.getTiming().iterations === Infinity).length)).toBe(3);
        await expect.poll(() => page.evaluate(() => document.getAnimations()
            .filter(a => a.effect.target === document.querySelector('.site-navigation')
                && a.effect.getTiming().iterations === Infinity).length)).toBe(3);
        if (scenario.from[1] !== scenario.to[1]) {
            await expect(page.locator('.public-content-reveal')).toHaveCSS('overflow', 'visible');
        }
    });
}

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
    const numbers = shadow => [...shadow.matchAll(/-?\\d+(?:\\.\\d+)?px/g)]
        .map(match => parseFloat(match[0]));
    expect(numbers(result.nextShadow)).toEqual(numbers(result.oldShadow));
    expect(parseFloat(result.nextHeight)).toBeCloseTo(result.oldBodyHeight, 0);
});

test('real Laravel/Livewire public navigation stays in the same document', async ({ page }) => {
    test.skip(!process.env.PUBLIC_SPA_SMOKE_URL, 'Requires a running local Laravel preview.');
    await page.goto(new URL('/paintings', process.env.PUBLIC_SPA_SMOKE_URL).href);
    await page.evaluate(() => { window.__sameDocument = true; });
    const next = await page.evaluate(() => [...document.querySelectorAll('.site-navigation a[wire\\:navigate]')]
        .find(link => new URL(link.href).pathname !== location.pathname)?.href);
    expect(next).toBeTruthy();
    await page.locator('.site-navigation a[wire\\:navigate]').evaluateAll((links, destination) =>
        links.find(link => link.href === destination)?.click(), next);
    await expect(page).toHaveURL(next);
    await expect.poll(() => page.evaluate(() => window.__sameDocument)).toBe(true);
    await expect(page.locator('#content > .public-content-reveal')).toHaveCount(1);
});
