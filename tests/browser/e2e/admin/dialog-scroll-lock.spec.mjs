import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

async function geometry(page) {
    return page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        rootWidth: document.documentElement.clientWidth,
        viewportHeight: document.documentElement.clientHeight,
        documentHeight: document.documentElement.scrollHeight,
        scrollY: window.scrollY,
    }));
}

test('long admin pages retain their scrollbar and background position through a blocking modal', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 1280, height: 360 });
    await page.goto('/admin/pages');

    const before = await geometry(page);
    expect(before.documentHeight, 'Fixture must have actual document overflow').toBeGreaterThan(before.viewportHeight);

    const open = page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true });
    const dialogHeading = page.getByRole('heading', { name: 'Add page', exact: true });
    await open.click();
    await expect(dialogHeading).toBeVisible();

    const during = await geometry(page);
    expect(during.rootWidth, 'Opening the modal must not move the document scrollbar')
        .toBe(before.rootWidth);
    expect(during.scrollY, 'Opening the modal must not move background content')
        .toBe(before.scrollY);

    await page.mouse.move(15, 150);
    await page.mouse.wheel(0, 420);
    expect((await geometry(page)).scrollY, 'Background must remain locked while a dialog is open')
        .toBe(before.scrollY);

    await page.keyboard.press('Escape');
    await expect(dialogHeading).toBeHidden();
    const after = await geometry(page);
    expect(after.rootWidth).toBe(before.rootWidth);
    expect(after.scrollY).toBe(before.scrollY);

    await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
});

test('short admin pages do not invent a scrollbar or horizontal shift while a modal opens and closes', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 1280, height: 3000 });
    await page.goto('/admin/pages');

    const before = await geometry(page);
    expect(before.documentHeight, 'Fixture must fit in the viewport')
        .toBeLessThanOrEqual(before.viewportHeight);
    expect(before.rootWidth, 'Short pages must not reserve a fake scrollbar gutter')
        .toBe(before.viewportWidth);

    const open = page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true });
    const dialogHeading = page.getByRole('heading', { name: 'Add page', exact: true });
    await open.click();
    await expect(dialogHeading).toBeVisible();
    const during = await geometry(page);
    expect(during.rootWidth).toBe(before.rootWidth);
    expect(during.documentHeight).toBeLessThanOrEqual(during.viewportHeight);

    await page.keyboard.press('Escape');
    await expect(dialogHeading).toBeHidden();
    const after = await geometry(page);
    expect(after.rootWidth).toBe(before.rootWidth);
    expect(after.documentHeight).toBeLessThanOrEqual(after.viewportHeight);
    expect(after.scrollY).toBe(0);
});

test('Dashboard dialog stays positioned and background stays locked after checkbox autosave', async ({ page }) => {

    await signIn(page);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Dashboard settings', exact: true }).click();

    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();
    const window = modal.locator(':scope > .fi-modal-window-ctn > .fi-modal-window');
    const before = await window.boundingBox();
    expect(before).not.toBeNull();

    const checkbox = modal.getByRole('switch', { name: 'Delete messages without confirmation' });
    const initial = (await checkbox.getAttribute('aria-checked')) === 'true';

    try {
        await checkbox.click();
        await expect(checkbox).toHaveAttribute('aria-checked', String(!initial));
        await page.waitForLoadState('networkidle');

        const after = await window.boundingBox();
        expect(after).not.toBeNull();
        expect(Math.abs(after.x - before.x), 'Dialog horizontal position changed after autosave').toBeLessThan(1);
        expect(Math.abs(after.y - before.y), 'Dialog vertical position changed after autosave').toBeLessThan(1);

        const scrollBefore = await page.evaluate(() => window.scrollY);
        await page.evaluate(() => {
            window.__modalBackgroundScrollEvents = 0;
            window.addEventListener('scroll', () => window.__modalBackgroundScrollEvents++, { passive: true, once: true });
        });
        await page.mouse.move(16, Math.floor(page.viewportSize().height / 2));
        await page.mouse.wheel(0, 500);
        await page.waitForTimeout(120);
        const scrollAfter = await page.evaluate(() => ({
            y: window.scrollY,
            events: window.__modalBackgroundScrollEvents,
        }));
        expect(scrollAfter.y).toBe(scrollBefore);
        expect(scrollAfter.events, 'Backdrop wheel event chained to document scrollbar').toBe(0);
    } finally {
        if (await checkbox.isVisible() && ((await checkbox.getAttribute('aria-checked')) === 'true') !== initial) {
            await checkbox.click();
            await expect(checkbox).toHaveAttribute('aria-checked', String(initial));
            await page.waitForLoadState('networkidle');
        }
        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
    }
});


test('short Dashboard stays scrollbar-free during closing transition', async ({ page }) => {

    // Guarantee an initially scrollbar-free Dashboard without modifying CSS.
    await page.setViewportSize({ width: 1440, height: 3000 });
    await signIn(page);

    const noInitialScrollbar = await page.evaluate(
        () => document.documentElement.scrollHeight <= document.documentElement.clientHeight,
    );
    expect(noInitialScrollbar, 'Dashboard must be short at this viewport for the regression test').toBe(true);

    await page.getByRole('button', { name: 'Dashboard settings', exact: true }).click();
    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.body.style.position)).toBe('');

    // Capture every animation frame through the native Escape/close transition.
    await page.evaluate(() => {
        window.__modalReleaseFrames = [];
        const started = performance.now();
        function measure() {
            const root = document.documentElement;
            const main = document.querySelector('.fi-main');
            window.__modalReleaseFrames.push({
                scrollbarWidth: window.innerWidth - root.clientWidth,
                mainLeft: main?.getBoundingClientRect().left ?? null,
                bodyPosition: document.body.style.position,
            });
            if (performance.now() - started < 500) requestAnimationFrame(measure);
        }
        requestAnimationFrame(measure);
    });
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await page.waitForTimeout(550);
    const frames = await page.evaluate(() => window.__modalReleaseFrames);
    expect(frames.length).toBeGreaterThan(4);
    const left = frames[0].mainLeft;
    for (const frame of frames) {
        expect(frame.scrollbarWidth, 'Closing briefly showed a native scrollbar').toBe(0);
        expect(frame.bodyPosition, 'Closing toggled body positioning on a short page').toBe('');
        expect(Math.abs(frame.mainLeft - left), 'Closing shifted the workspace').toBeLessThan(1);
    }
});
