import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

async function expectSelection(header, rows, countBadge, selected, total) {
    await expect.poll(async () => rows.evaluateAll((inputs) => inputs.filter((input) => input.checked).length))
        .toBe(selected);
    await expect(countBadge).toHaveText(String(selected));
    await expect(header).toHaveJSProperty('checked', selected === total);
    await expect(header).toHaveJSProperty('indeterminate', selected > 0 && selected < total);
    const accessibility = await header.ariaSnapshot();
    if (selected > 0 && selected < total) {
        expect(accessibility).toContain('[checked=mixed]');
    } else {
        expect(accessibility).not.toContain('[checked=mixed]');
    }
}

test('Pages select-all stays synchronized after mixed selection and repeated clicks', async ({ page }) => {
    await page.goto('/admin/pages');
    const header = page.getByRole('checkbox', { name: 'Select all visible pages' });
    const rows = page.locator('.admin-pages__row input[type="checkbox"]');
    const countBadge = page.locator('.admin-pages__selection .admin-selection__count');
    const total = await rows.count();
    expect(total, 'Browser fixture requires two Pages').toBeGreaterThanOrEqual(2);

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
    expect(total, 'Browser fixture requires two dashboard entries').toBeGreaterThanOrEqual(2);

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
