import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
});

test('Pages toolbar uses underline hover and field focus without restyling table actions', async ({ page }) => {
    await page.goto('/admin/pages');

    const settings = page.locator('.admin-pages__actions .admin-action').first();
    await settings.hover();
    await expect.poll(() => settings.evaluate((el) => getComputedStyle(el).boxShadow)).toContain('inset');
    await expect(settings).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    const search = page.getByRole('searchbox', { name: /search/i }).first();
    await search.focus();
    const accent = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--admin-accent').trim());
    await expect(search).toHaveCSS('border-bottom-color', accent);

    const type = page.getByRole('combobox', { name: 'Filter by page type' });
    await type.focus();
    await expect(type).toHaveCSS('border-bottom-color', accent);

    const tableAction = page.locator('.admin-pages__row-actions .admin-action:not(:disabled)').first();
    await tableAction.hover();
    // Only x-admin.controls changes: row actions retain their established fill/border hover.
    await expect(tableAction).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
});

test('Selection underline reaches the selected count and reserved terminal rail', async ({ page }) => {
    await page.goto('/admin/pages');
    const first = page.locator('.admin-pages__row input[type="checkbox"]').first();
    test.skip(await first.count() === 0, 'Requires a visible page');
    await first.check();

    const group = page.locator('.admin-pages__selection');
    const trigger = group.locator('.admin-selection__trigger');
    await expect(trigger).toBeEnabled();
    await trigger.hover();
    await expect.poll(() => group.evaluate((el) => getComputedStyle(el, '::after').opacity)).toBe('1');
    const pseudo = await group.evaluate((el) => {
        const style = getComputedStyle(el, '::after');
        return { left: style.left, right: style.right };
    });
    expect(pseudo).toEqual({ left: '0px', right: '0px' });

    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect.poll(() => group.evaluate((el) => getComputedStyle(el, '::after').height)).toBe('2px');
});

test('Storage active View and compact Filters use one accented underline', async ({ page }) => {
    await page.setViewportSize({ width: 900, height: 820 });
    await page.goto('/admin/storage');

    const active = page.locator('.media-workspace__view-option[aria-pressed="true"]');
    await expect(active).toHaveCount(1);
    await expect(active).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect.poll(() => active.evaluate((el) => getComputedStyle(el).boxShadow)).toContain('inset');

    const alternate = page.locator('.media-workspace__view-option[aria-pressed="false"]').first();
    await alternate.hover();
    await expect(alternate).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect.poll(() => alternate.evaluate((el) => getComputedStyle(el).boxShadow)).toContain('inset');

    const filters = page.getByRole('button', { name: 'Filters', exact: true });
    await expect(filters).toBeVisible();
    await filters.click();
    await expect(filters).toHaveAttribute('aria-expanded', 'true');
    await expect.poll(() => filters.evaluate((el) => getComputedStyle(el).boxShadow)).toContain('inset');
});
