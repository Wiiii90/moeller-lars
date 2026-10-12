import { expect, test } from '@playwright/test';

async function resolvedAdminColor(page, token) {
    return page.evaluate((name) => {
        const probe = document.createElement('span');
        probe.style.color = `var(${name})`;
        document.body.append(probe);
        const result = getComputedStyle(probe).color;
        probe.remove();
        return result;
    }, token);
}

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
    await expect(settings).toHaveCSS('border-bottom-width', '1px');
    await expect(settings).toHaveCSS('border-bottom-color', await resolvedAdminColor(page, '--admin-line-strong'));
    await expect(settings).toHaveCSS('box-shadow', 'none');
    await expect(settings).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    const search = page.getByRole('searchbox', { name: /search/i }).first();
    await search.focus();
    const accent = await resolvedAdminColor(page, '--admin-accent');
    await expect(search).toHaveCSS('border-bottom-color', accent);
    await expect(search).toHaveCSS('border-bottom-width', '1px');

    const type = page.getByRole('combobox', { name: 'Filter by page type' });
    await type.focus();
    await expect(type).toHaveCSS('border-bottom-color', accent);
    await expect(type).toHaveCSS('border-bottom-width', '1px');

    // Clear initially has no action and is disabled; once a filter is active,
    // it shares the exact same thin hover underline as other toolbar actions.
    await search.fill('toolbar regression');
    const clear = page.getByRole('button', { name: 'Clear', exact: true });
    await expect(clear).toBeEnabled();
    await clear.hover();
    await expect(clear).toHaveCSS('border-bottom-width', '1px');
    await expect(clear).toHaveCSS('border-bottom-color', await resolvedAdminColor(page, '--admin-line-strong'));
    await expect(clear).toHaveCSS('box-shadow', 'none');

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
    await expect.poll(() => group.evaluate((el) => getComputedStyle(el, '::after').height)).toBe('1px');
    await expect(trigger).toHaveCSS('box-shadow', 'none');
    await expect(trigger).toHaveCSS('border-bottom-color', 'rgba(0, 0, 0, 0)');
    const pseudo = await group.evaluate((el) => {
        const style = getComputedStyle(el, '::after');
        return { left: style.left, right: style.right };
    });
    expect(pseudo).toEqual({ left: '0px', right: '0px' });

    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect.poll(() => group.evaluate((el) => getComputedStyle(el, '::after').height)).toBe('1px');
    await expect.poll(() => group.evaluate((el) => getComputedStyle(el, '::after').backgroundColor))
        .toBe(await resolvedAdminColor(page, '--admin-accent'));
    await expect(trigger).toHaveCSS('box-shadow', 'none');
});

test('Storage active View and compact Filters use one accented underline', async ({ page }) => {
    await page.setViewportSize({ width: 900, height: 820 });
    await page.goto('/admin/storage');

    const active = page.locator('.media-workspace__view-option[aria-pressed="true"]');
    await expect(active).toHaveCount(1);
    await expect(active).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(active).toHaveCSS('border-bottom-width', '1px');
    await expect(active).toHaveCSS('border-bottom-color', await resolvedAdminColor(page, '--admin-accent'));
    await expect(active).toHaveCSS('box-shadow', 'none');

    const alternate = page.locator('.media-workspace__view-option[aria-pressed="false"]').first();
    await alternate.hover();
    await expect(alternate).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(alternate).toHaveCSS('border-bottom-width', '1px');
    await expect(alternate).toHaveCSS('border-bottom-color', await resolvedAdminColor(page, '--admin-line-strong'));
    await expect(alternate).toHaveCSS('box-shadow', 'none');

    const filters = page.getByRole('button', { name: 'Filters', exact: true });
    await expect(filters).toBeVisible();
    await filters.click();
    await expect(filters).toHaveAttribute('aria-expanded', 'true');
    await expect(filters).toHaveCSS('border-bottom-width', '1px');
    await expect(filters).toHaveCSS('border-bottom-color', await resolvedAdminColor(page, '--admin-accent'));
    await expect(filters).toHaveCSS('box-shadow', 'none');
});
