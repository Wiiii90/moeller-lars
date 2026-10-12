import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin test credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
});

async function expectSelection(header, rows, countBadge, selected, total) {
    await expect.poll(async () => rows.evaluateAll((inputs) => inputs.filter((input) => input.checked).length))
        .toBe(selected);
    await expect(countBadge).toHaveText(String(selected));
    await expect(header).toHaveJSProperty('checked', selected === total);
    await expect(header).toHaveJSProperty('indeterminate', selected > 0 && selected < total);
    await expect(header).toHaveAttribute(
        'aria-checked',
        selected > 0 && selected < total ? 'mixed' : (selected === total ? 'true' : 'false'),
    );
}

test('Pages select-all stays synchronized after mixed selection and repeated clicks', async ({ page }) => {
    await page.goto('/admin/pages');
    const header = page.getByRole('checkbox', { name: 'Select all visible pages' });
    const rows = page.locator('.admin-pages__row input[type="checkbox"]');
    const countBadge = page.locator('.admin-pages__selection .admin-selection__count');
    const total = await rows.count();
    test.skip(total < 2, 'Requires at least two visible Pages for a mixed-selection test');

    await expectSelection(header, rows, countBadge, 0, total);
    await header.click();
    await expectSelection(header, rows, countBadge, total, total);

    await rows.first().uncheck();
    await expectSelection(header, rows, countBadge, total - 1, total);

    // Mixed -> all; all -> none. A remembered partial selection is not restored.
    await header.click();
    await expectSelection(header, rows, countBadge, total, total);
    await header.click();
    await expectSelection(header, rows, countBadge, 0, total);

    await rows.first().check();
    await expectSelection(header, rows, countBadge, 1, total);
    await header.click();
    await expectSelection(header, rows, countBadge, total, total);
    await header.click();
    await expectSelection(header, rows, countBadge, 0, total);
});

test('Dashboard select-all follows the same native three-state selection contract', async ({ page }) => {
    const header = page.getByRole('checkbox', { name: 'Select all visible feed entries' });
    const rows = page.locator('.admin-dashboard__feed-table tbody input[type="checkbox"]');
    const countBadge = page.locator('.admin-dashboard__selection .admin-selection__count');
    const total = await rows.count();
    test.skip(total < 2, 'Requires at least two visible feed entries for a mixed-selection test');

    await expectSelection(header, rows, countBadge, 0, total);
    await header.click();
    await expectSelection(header, rows, countBadge, total, total);
    await rows.first().uncheck();
    await expectSelection(header, rows, countBadge, total - 1, total);
    await header.click();
    await expectSelection(header, rows, countBadge, total, total);
    await header.click();
    await expectSelection(header, rows, countBadge, 0, total);
});
