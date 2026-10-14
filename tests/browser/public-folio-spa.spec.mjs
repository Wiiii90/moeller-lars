import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const source = readFileSync(
    new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const heroSource = readFileSync(
    new URL('../../resources/js/home-hero.js', import.meta.url), 'utf8',
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
    expect(result.outside.every(n => n < 0 && n > -10)).toBe(true);
    expect(result.animations.filter(a => a.pseudo)).toHaveLength(4);
    expect(result.animations.filter(a => a.target !== 'other' && !a.pseudo)).toHaveLength(2);
    expect(result.animations.every(a => a.iterations === Infinity)).toBe(true);
    const paperProjections = await page.evaluate(() => {
        const parse = (node) => {
            const layers=[];
            const string=getComputedStyle(node).boxShadow;
            let level=0,begin=0;
            for(let i=0;i<string.length;i++){
                if(string[i]==='(')level++;
                else if(string[i]===')')level--;
                else if(string[i]===','&&level===0){
                    layers.push(string.slice(begin,i)); begin=i+1;
                }
            }
            layers.push(string.slice(begin));
            return layers.slice(1).map(layer=>{
                const values=[...layer.matchAll(/(-?\\d+(?:\\.\\d+)?)px/g)]
                    .slice(0,4).map(match=>Number(match[1]));
                const [x,y]=values;
                const spread=values.length>=3?values.at(-1):0;
                return { left:x-spread, bottom:y+spread, top:y-spread };
            });
        };
        return {
            menu:parse(document.querySelector('.site-navigation')),
            content:parse(document.querySelector('#content')),
        };
    });
    for(let i=0;i<2;i++){
        const menu=paperProjections.menu[i];
        const content=paperProjections.content[i];
        expect(menu.top).toBeLessThanOrEqual(.05);
        expect(menu.left).toBeCloseTo(content.left,0);
        expect(menu.bottom).toBeCloseTo(content.bottom,0);
    }
    const menu = await page.evaluate(() => {
        const element = document.querySelector('.site-navigation');
        return document.getAnimations()
            .filter(a => a.effect.target === element && a.effect.pseudoElement)
            .map(a => a.effect.getKeyframes().map(frame => ({
                top: frame.top, bottom: frame.bottom,
                left: frame.left, right: frame.right, width: frame.width,
            })));
    });
    expect(menu).toHaveLength(2);
    for (const frames of menu) {
        expect(frames.every(frame => parseFloat(frame.top) === 0)).toBe(true);
        expect(frames.every(frame => parseFloat(frame.bottom) >= 0)).toBe(true);
        expect(new Set(frames.map(frame => frame.width)).size).toBe(1);
        expect(new Set(frames.map(frame => frame.left ?? frame.right)).size).toBe(1);
    }
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
        // A SPA swap cannot force the menu into a new assembly impulse.
        expect(result.menuPulseDurations).toEqual([34200, 38800, 36400]);
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

test('Home Hero arrows animate the same paper/content, without touching the menu', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const heroHtml = '<header class="site-header"><div class="site-navigation"><nav>Home</nav></div></header>'
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
    expect(result.paper).toEqual([1480, 1480, 1480]);
    expect(result.height).toEqual([980]);
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
                out: a.effect.getKeyframes().map(f => f.left ?? f.right),
            }));
    });
    expect(afterFastClick).toHaveLength(2);
    const left = afterFastClick.find(a => a.pseudo === '::before');
    const right = afterFastClick.find(a => a.pseudo === '::after');
    expect(left.last.top).toBeTruthy();
    expect(right.last.bottom).toBeTruthy();
    expect(left.last.left).toBe('-.58rem');
    expect(right.last.right).toBe('-.48rem');
    expect(left.out).toContain('calc(-.58rem - 3.1px)');
    expect(right.out).toContain('calc(-.48rem - 3.1px)');
    expect(left.out.every(v => !v?.includes('calc(calc('))).toBe(true);
    expect(right.out.every(v => !v?.includes('calc(calc('))).toBe(true);
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
