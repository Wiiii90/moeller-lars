import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const motionSource = readFileSync(
    new URL('../../resources/js/public-folio-motion.js', import.meta.url), 'utf8',
);
const sheet = (title) => '<main id="content" class="site-content">'
    + '<h2>' + title + '</h2></main>';

test('the ORIGINAL CSS pseudos animate directly, without drawing extra corners', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setContent('<!doctype html><html><head>'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">'
        + '</head><body class="public-site">' + sheet('Painting') + '</body></html>');
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addScriptTag({
        content: motionSource.replace(/^export /gm, '')
            + '\nwindow.publicFolioTest = { capturePublicFolio, advancePublicFolio };',
    });

    const initial = await page.evaluate(() => {
        const el = document.querySelector('#content');
        const left = getComputedStyle(el, '::before');
        const right = getComputedStyle(el, '::after');
        return {
            children: el.children.length,
            left: { top: left.top, bottom: left.bottom, width: left.width, x: left.left },
            right: { top: right.top, bottom: right.bottom, width: right.width, x: right.right },
        };
    });
    expect(initial.children).toBe(1);

    await page.evaluate(() => window.publicFolioTest.advancePublicFolio());
    const animations = await page.evaluate(() => {
        const el = document.querySelector('#content');
        const tracked = document.getAnimations().filter((animation) =>
            animation.effect.target === el && !!animation.effect.pseudoElement).map((animation) => ({
            pseudo: animation.effect.pseudoElement,
            duration: animation.effect.getTiming().duration,
            frames: animation.effect.getKeyframes(),
        }));
        return {
            children: el.children.length,
            tracked,
            shadows: document.getAnimations().filter((animation) =>
                animation.effect.target === el && !animation.effect.pseudoElement).map((animation) => ({
                duration: animation.effect.getTiming().duration,
                frames: animation.effect.getKeyframes(),
            })),
            leftWidth: getComputedStyle(el, '::before').width,
            rightWidth: getComputedStyle(el, '::after').width,
            leftOffset: getComputedStyle(el, '::before').left,
            rightOffset: getComputedStyle(el, '::after').right,
        };
    });
    expect(animations.children).toBe(1);
    expect(animations.tracked).toHaveLength(2);
    expect(animations.shadows).toHaveLength(1);
    expect(animations.shadows[0].duration).toBe(1150);
    expect(animations.shadows[0].frames[1].boxShadow).toContain('var(--public-folio-depth)');
    expect(animations.tracked.map((a) => a.pseudo).sort()).toEqual(['::after', '::before']);
    expect(animations.tracked.every((a) => a.duration === 1150)).toBe(true);
    expect(animations.leftWidth).toBe(initial.left.width);
    expect(animations.rightWidth).toBe(initial.right.width);
    expect(animations.leftOffset).toBe(initial.left.x);
    expect(animations.rightOffset).toBe(initial.right.x);

    // Transfer an in-progress pseudo animation to a newly inserted sheet.
    await page.waitForTimeout(170);
    const interrupted = await page.evaluate((html) => {
        const old = document.querySelector('#content');
        const before = getComputedStyle(old, '::before').top;
        window.publicFolioTest.capturePublicFolio();
        old.outerHTML = html;
        window.publicFolioTest.advancePublicFolio();
        const replacement = document.querySelector('#content');
        const effect = document.getAnimations().find((a) =>
            a.effect.target === replacement && a.effect.pseudoElement === '::before');
        return { before, frame: effect.effect.getKeyframes()[0].top, children: replacement.children.length };
    }, sheet('Prints'));
    expect(interrupted.frame).toBe(interrupted.before);
    expect(interrupted.children).toBe(1);
});

test('the Livewire preview retains its document across public navigation', async ({ page }) => {
    test.skip(!process.env.PUBLIC_SPA_SMOKE_URL,
        'Set PUBLIC_SPA_SMOKE_URL to the local preview to verify the real Livewire transport.');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(new URL('/paintings', process.env.PUBLIC_SPA_SMOKE_URL).href);
    await page.evaluate(() => { window.__publicDocumentMarker = 'unchanged'; });
    const candidate = await page.evaluate(() => [...document.querySelectorAll('.site-navigation a[wire\\:navigate]')]
        .find((link) => new URL(link.href).pathname !== location.pathname)?.href);
    expect(candidate).toBeTruthy();
    await page.locator('.site-navigation a[wire\\:navigate]').evaluateAll((links, destination) => {
        links.find((element) => element.href === destination)?.click();
    }, candidate);
    await expect(page).toHaveURL(candidate);
    await expect.poll(() => page.evaluate(() => window.__publicDocumentMarker)).toBe('unchanged');
    await expect.poll(() => page.evaluate(() =>
        document.querySelector('#content').style.getPropertyValue('--public-folio-left-top')
    )).not.toBe('');
    await expect(page.locator('.public-folio-decoration')).toHaveCount(0);
});
