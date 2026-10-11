import { expect, test } from '@playwright/test';

test('Header Notification tracks the actual content edges and User stays outside', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
    await page.goto('/admin/general');

    for (const width of [1900, 1600, 1440, 1200, 1100, 1024]) {
        await page.setViewportSize({ width, height: 900 });
        const bounds = await page.evaluate(() => {
            const n = document.querySelector('[data-admin-header-notification]');
            const w = document.querySelector('.fi-main .admin-workspace');
            const b = document.querySelector('.fi-topbar-end .fi-user-menu-trigger');
            if (!n || !w || !b) return null;
            n.hidden = false;
            n.dataset.phase = 'idle';
            return {
                n: n.getBoundingClientRect().toJSON(),
                w: w.getBoundingClientRect().toJSON(),
                b: b.getBoundingClientRect().toJSON(),
                viewport: document.documentElement.clientWidth,
            };
        });
        expect(bounds, 'missing Header/Workspace/User at width ' + width).not.toBeNull();
        expect(Math.abs(bounds.n.left - bounds.w.left), 'Notification left at ' + width).toBeLessThan(1);
        expect(Math.abs(bounds.n.right - bounds.w.right), 'Notification right at ' + width).toBeLessThan(1);
        expect(bounds.b.left - bounds.w.right, 'User overlapping content at ' + width).toBeGreaterThanOrEqual(15);
        expect(bounds.b.right, 'User outside scrollport at ' + width).toBeLessThanOrEqual(bounds.viewport);
    }

    await page.goto('/admin/dashboard');
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    const bounds = await page.evaluate(() => {
        const n = document.querySelector('[data-admin-header-notification]');
        const w = document.querySelector('.fi-main .admin-workspace');
        if (!n || !w) return null;
        n.hidden = false;
        n.dataset.phase = 'idle';
        return { n: n.getBoundingClientRect().toJSON(), w: w.getBoundingClientRect().toJSON() };
    });
    expect(bounds).not.toBeNull();
    expect(Math.abs(bounds.n.left - bounds.w.left)).toBeLessThan(1);
    expect(Math.abs(bounds.n.right - bounds.w.right)).toBeLessThan(1);
});
