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
