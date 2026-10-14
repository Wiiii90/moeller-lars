import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const source = readFileSync(
    new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const sheet = (label, height = 520) =>
    '<header class="site-header"><div class="site-navigation"><nav>' + label + '</nav></div></header>'
    + '<main id="content" class="site-content">'
    + '<article class="artwork-detail"><div style="height:' + height + 'px">' + label + '</div></article>'
    + '</main>';

test('original paper corners animate in one axis at a time, with connected horizontal returns', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body class="public-site">'
        + sheet('Painting') + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: source.replace(/^export /gm, '')
            + '\nwindow.publicFolioTest = { capturePublicFolio, advancePublicFolio };',
    });
    await page.evaluate(() => window.publicFolioTest.advancePublicFolio());
    const state = await page.evaluate(() => {
        const main = document.querySelector('#content');
        const nav = document.querySelector('.site-navigation');
        const effects = document.getAnimations().map(a => ({
            target: a.effect.target === main ? 'content' :
                a.effect.target === nav ? 'menu' : 'other',
            pseudo: a.effect.pseudoElement || null,
            iterations: a.effect.getTiming().iterations,
            frames: a.effect.getKeyframes(),
        }));
        const bounds = [getComputedStyle(nav, '::before'), getComputedStyle(nav, '::after')];
        return {
            children: main.children.length,
            effects, menuTop: bounds.map(b => b.borderTopStyle),
            menuBottom: bounds.map(b => b.borderBottomStyle),
        };
    });
    expect(state.children).toBe(1);
    expect(state.effects.filter(e => e.pseudo)).toHaveLength(4);
    expect(state.effects.filter(e => e.target !== 'other' && !e.pseudo)).toHaveLength(2);
    expect(state.effects.every(e => e.iterations === Infinity)).toBe(true);
    expect(state.menuTop).toEqual(['none', 'none']);
    expect(state.menuBottom).toEqual(['solid', 'solid']);
    for (const effect of state.effects.filter(e => e.pseudo)) {
        for (let i = 1; i < effect.frames.length; i += 1) {
            const a = effect.frames[i - 1], b = effect.frames[i];
            const vertical = a.top !== b.top || a.bottom !== b.bottom;
            const horizontal = a.width !== b.width;
            expect(vertical && horizontal).toBe(false);
        }
    }
});

test('different artwork heights build or shrink the WHITE paper without scaling media', async ({ page }) => {
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body class="public-site">'
        + sheet('Tall artwork', 820) + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: source.replace(/^export /gm, '')
            + '\nwindow.publicFolioTest = { capturePublicFolio, advancePublicFolio };',
    });
    await page.evaluate(() => window.publicFolioTest.advancePublicFolio());
    const replacement = await page.evaluate((markup) => {
        const original = document.querySelector('.artwork-detail').getBoundingClientRect().height;
        window.publicFolioTest.capturePublicFolio();
        document.body.innerHTML = markup;
        window.publicFolioTest.advancePublicFolio();
        const article = document.querySelector('.artwork-detail');
        const motion = article.getAnimations()[0];
        return {
            original, newNatural: 390,
            activeOverflow: getComputedStyle(article).overflow,
            paperOverflow: getComputedStyle(document.querySelector('#content')).overflow,
            keys: motion?.effect.getKeyframes(),
            duration: motion?.effect.getTiming().duration,
        };
    }, sheet('Short artwork', 390));
    expect(replacement.original).toBeGreaterThan(750);
    expect(replacement.activeOverflow).toBe('clip');
    expect(replacement.paperOverflow).toBe('visible');
    expect(replacement.keys?.[0]?.height).toContain('px');
    expect(replacement.keys?.[1]?.height).toContain('px');
    expect(replacement.duration).toBe(700);
    await page.waitForTimeout(780);
    await expect(page.locator('.artwork-detail')).toHaveCSS('overflow', 'visible');
});

test('actual Livewire detail navigation can retain the same document', async ({ page }) => {
    test.skip(!process.env.PUBLIC_SPA_SMOKE_URL, 'Set PUBLIC_SPA_SMOKE_URL for the Laravel preview.');
    await page.goto(new URL('/paintings', process.env.PUBLIC_SPA_SMOKE_URL).href);
    const artwork = page.locator('.artwork-card__link').first();
    await expect(artwork).toBeVisible();
    await artwork.click();
    await expect(page.locator('.artwork-detail')).toBeVisible();
    await page.evaluate(() => { window.__sameDocument = true; });
    const next = page.locator('.artwork-folio__step[href]').last();
    await next.click();
    await expect(page.locator('.artwork-detail')).toBeVisible();
    expect(await page.evaluate(() => window.__sameDocument)).toBe(true);
});
