import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const motionSource = readFileSync(
    new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const drawing = '<div class="public-folio-decoration" aria-hidden="true">'
    + '<span class="public-folio-decoration__left"></span>'
    + '<span class="public-folio-decoration__right"></span></div>';
const sheet = (title) => '<main id="content" class="site-content">'
    + drawing + '<h2>' + title + '</h2></main>';

test('connected paper corners animate on a real browser from the original geometry', async ({ page }) => {
    await page.setContent('<!doctype html><html><head>'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">'
        + '</head><body class="public-site">' + sheet('Painting') + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: motionSource.replace(/^export /gm, '')
            + '\nwindow.publicFolioTest = { capturePublicFolio, advancePublicFolio };',
    });
    await page.evaluate(() => window.publicFolioTest.advancePublicFolio());

    const left = page.locator('.public-folio-decoration__left');
    const right = page.locator('.public-folio-decoration__right');
    await expect(page.locator('.public-folio-decoration')).toHaveAttribute('data-folio-active', '');
    const state = await page.evaluate(() => {
        const left = document.querySelector('.public-folio-decoration__left');
        const right = document.querySelector('.public-folio-decoration__right');
        const animation = left.getAnimations()[0];
        return {
            leftAnimations: left.getAnimations().length,
            rightAnimations: right.getAnimations().length,
            keyframes: animation?.effect.getKeyframes(),
            initialFallbackHidden: getComputedStyle(document.querySelector('#content'), '::before').visibility === 'hidden',
        };
    });
    expect(state.leftAnimations).toBe(1);
    expect(state.rightAnimations).toBe(1);
    expect(state.keyframes).toHaveLength(2);
    // The browser normalizes CSS lengths (1.10rem -> 1.1rem).
    expect(parseFloat(state.keyframes[0].top)).toBeCloseTo(1.1, 2);
    expect(parseFloat(state.keyframes[0].width)).toBeCloseTo(.55, 2);
    expect(state.initialFallbackHidden).toBe(true);
    await expect(left).toBeVisible();
    await expect(right).toBeVisible();

    // Reproduce a second navigation DURING the animation, like Livewire's
    // body replacement, and prove the next effect starts at the exact
    // computed intermediate geometry rather than restarting at variant 1.
    await page.waitForTimeout(170);
    const interrupted = await page.evaluate((newMarkup) => {
        const current = document.querySelector('.public-folio-decoration__left');
        const from = getComputedStyle(current).top;
        window.publicFolioTest.capturePublicFolio();
        document.querySelector('#content').outerHTML = newMarkup;
        window.publicFolioTest.advancePublicFolio();
        const next = document.querySelector('.public-folio-decoration__left').getAnimations()[0];
        return { from, nextStart: next.effect.getKeyframes()[0].top };
    }, sheet('Prints'));
    expect(interrupted.nextStart).toBe(interrupted.from);
});

test('live Laravel preview keeps one document and activates folio only after SPA navigation', async ({ page }) => {
    test.skip(!process.env.PUBLIC_SPA_SMOKE_URL,
        'Set PUBLIC_SPA_SMOKE_URL to the local preview base URL for a real Laravel/Livewire test.');
    await page.goto(new URL('/paintings', process.env.PUBLIC_SPA_SMOKE_URL).href);
    await page.evaluate(() => { window.__publicDocumentMarker = 'unchanged'; });
    const candidate = await page.evaluate(() => [...document.querySelectorAll('.site-navigation a[wire\\:navigate]')]
        .find((link) => new URL(link.href).pathname !== location.pathname)?.href);
    expect(candidate, 'The seeded preview should have another visible public navigation link').toBeTruthy();
    await page.locator('.site-navigation a[wire\\:navigate]').evaluateAll((links, destination) => {
        const link = links.find((element) => element.href === destination);
        link?.click();
    }, candidate);
    await expect(page).toHaveURL(candidate);
    await expect.poll(() => page.evaluate(() => window.__publicDocumentMarker)).toBe('unchanged');
    await expect(page.locator('.public-folio-decoration')).toHaveAttribute('data-folio-active', '');
    await expect(page.locator('.public-folio-decoration__left')).toBeVisible();
});

