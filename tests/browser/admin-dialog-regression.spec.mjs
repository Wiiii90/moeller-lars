import { expect, test } from '@playwright/test';

test('Dashboard dialog stays positioned and background stays locked after checkbox autosave', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin test credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Settings', exact: true }).first().click();

    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();
    const window = modal.locator(':scope > .fi-modal-window-ctn > .fi-modal-window');
    const before = await window.boundingBox();
    expect(before).not.toBeNull();

    const checkbox = modal.getByRole('checkbox', { name: 'Delete messages without confirmation' });
    const initial = await checkbox.isChecked();

    try {
        await checkbox.click();
        await expect(checkbox).toBeChecked({ checked: !initial });
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
        if (await checkbox.isVisible() && (await checkbox.isChecked()) !== initial) {
            await checkbox.click();
            await expect(checkbox).toBeChecked({ checked: initial });
            await page.waitForLoadState('networkidle');
        }
        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
    }
});
