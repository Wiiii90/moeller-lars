import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const motionSource = readFileSync(new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8');
const sheet = (name) => '<header class="site-header"><div class="site-navigation">'
    + '<nav>' + name + '</nav></div></header>'
    + '<main id="content" class="site-content"><h2>' + name + '</h2></main>';

test('content and menu animate their EXISTING strokes continuously, with no extra elements', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setContent('<!doctype html><html><head><meta name="viewport" '
        + 'content="width=device-width, initial-scale=1"></head><body class="public-site">'
        + sheet('Paintings') + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: motionSource.replace(/^export /gm, '')
            + '\nwindow.publicFolioTest = { capturePublicFolio, advancePublicFolio };',
    });
    await page.evaluate(() => window.publicFolioTest.advancePublicFolio());
    const state = await page.evaluate(() => {
        const content = document.querySelector('#content');
        const menu = document.querySelector('.site-navigation');
        const effects = document.getAnimations().map(a => ({
            target: a.effect.target === content ? 'content' :
                a.effect.target === menu ? 'menu' : 'other',
            pseudo: a.effect.pseudoElement || null,
            duration: a.effect.getTiming().duration,
            iterations: a.effect.getTiming().iterations,
            frames: a.effect.getKeyframes(),
        }));
        return {
            contentChildren: content.children.length,
            menuChildren: menu.children.length,
            contentLeftWidth: getComputedStyle(content, '::before').width,
            menuRightWidth: getComputedStyle(menu, '::after').width,
            effects,
        };
    });
    expect(state.contentChildren).toBe(1);
    expect(state.menuChildren).toBe(1);
    expect(state.effects.filter(x => x.pseudo)).toHaveLength(4);
    expect(state.effects.filter(x => x.target === 'content' && !x.pseudo)).toHaveLength(1);
    expect(state.effects.every(x => x.iterations === Infinity)).toBe(true);
    expect(state.effects.every(x => x.frames[0].top === undefined
        || x.frames[0].top === x.frames.at(-1).top)).toBe(true);

    // Both original outside strokes run upward OPEN; only their bottom
    // return turns a few pixels into the white menu sheet.
    const geometry = await page.evaluate(() => {
        const menu = document.querySelector('.site-navigation');
        const left = getComputedStyle(menu, '::before');
        const right = getComputedStyle(menu, '::after');
        return {
            leftNoTop: left.borderTopStyle === 'none',
            rightNoTop: right.borderTopStyle === 'none',
            leftHasBottom: left.borderBottomStyle === 'solid',
            rightHasBottom: right.borderBottomStyle === 'solid',
            leftInside: parseFloat(left.width) > Math.abs(parseFloat(left.left)),
            rightInside: parseFloat(right.width) > Math.abs(parseFloat(right.right)),
            leftAboveMenu: parseFloat(left.top) < 0,
            rightAboveMenu: parseFloat(right.top) < 0,
        };
    });
    expect(geometry).toEqual({
        leftNoTop: true, rightNoTop: true,
        leftHasBottom: true, rightHasBottom: true,
        leftInside: true, rightInside: true,
        leftAboveMenu: true, rightAboveMenu: true,
    });

    // A Livewire-like swap during the ongoing animation must start from
    // the exact rendered intermediate state of BOTH original drawings.
    await page.waitForTimeout(250);
    const replaced = await page.evaluate((newMarkup) => {
        const oldContent = document.querySelector('#content');
        const oldMenu = document.querySelector('.site-navigation');
        const priorContent = getComputedStyle(oldContent, '::before').top;
        const priorMenu = getComputedStyle(oldMenu, '::after').top;
        window.publicFolioTest.capturePublicFolio();
        document.body.innerHTML = newMarkup;
        window.publicFolioTest.advancePublicFolio();
        const content = document.querySelector('#content');
        const menu = document.querySelector('.site-navigation');
        const effects = document.getAnimations();
        const nextContent = effects.find(a =>
            a.effect.target === content && a.effect.pseudoElement === '::before');
        const nextMenu = effects.find(a =>
            a.effect.target === menu && a.effect.pseudoElement === '::after');
        return {
            priorContent, priorMenu,
            nextContent: nextContent?.effect.getKeyframes()[0].top,
            nextMenu: nextMenu?.effect.getKeyframes()[0].top,
            contentChildren: content.children.length,
            menuChildren: menu.children.length,
        };
    }, sheet('Prints'));
    expect(replaced.nextContent).toBe(replaced.priorContent);
    expect(replaced.nextMenu).toBe(replaced.priorMenu);
    expect(replaced.contentChildren).toBe(1);
    expect(replaced.menuChildren).toBe(1);
});

test('Livewire page navigation stays in one document and restarts ongoing native motion', async ({ page }) => {
    test.skip(!process.env.PUBLIC_SPA_SMOKE_URL,
        'Set PUBLIC_SPA_SMOKE_URL to the local Laravel preview.');
    await page.goto(new URL('/paintings', process.env.PUBLIC_SPA_SMOKE_URL).href);
    await page.evaluate(() => { window.__navigationMarker = 'preserved'; });
    const candidate = await page.evaluate(() => [...document.querySelectorAll('.site-navigation a[wire\\:navigate]')]
        .find(link => new URL(link.href).pathname !== location.pathname)?.href);
    expect(candidate).toBeTruthy();
    await page.locator('.site-navigation a[wire\\:navigate]').evaluateAll((links, url) =>
        links.find(link => link.href === url)?.click(), candidate);
    await expect(page).toHaveURL(candidate);
    await expect.poll(() => page.evaluate(() => window.__navigationMarker)).toBe('preserved');
    await expect.poll(() => page.evaluate(() => document.getAnimations()
        .filter(a => a.effect.pseudoElement).length)).toBeGreaterThanOrEqual(2);
});
