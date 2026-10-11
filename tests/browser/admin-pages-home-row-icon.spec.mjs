import { expect, test } from '@playwright/test';

test('Pages keeps Home row geometry and shared icon size across Skip/Show Home', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
    await page.goto('/admin/pages');

    const row = page.locator('.admin-pages__row').filter({
        has: page.locator('select[aria-label="Home template"]'),
    });
    await expect(row).toHaveCount(1);

    async function geometry() {
        return row.evaluate((node) => {
            const edit = node.querySelector('button[aria-label="Edit"] .admin-action__icon');
            const toggle = node.querySelector('button[aria-label="Show Home"] .admin-action__icon')
                ?? node.querySelector('button[aria-label="Skip Home"] .admin-action__icon');
            if (!edit || !toggle) return null;

            const rowRect = node.getBoundingClientRect();
            const editRect = edit.getBoundingClientRect();
            const toggleRect = toggle.getBoundingClientRect();
            const toggleButtonRect = toggle.closest('button').getBoundingClientRect();

            return {
                rowHeight: rowRect.height,
                editWidth: editRect.width,
                editHeight: editRect.height,
                toggleWidth: toggleRect.width,
                toggleHeight: toggleRect.height,
                buttonHeight: toggleButtonRect.height,
            };
        });
    }

    const initial = await geometry();
    expect(initial).not.toBeNull();
    expect(initial.toggleWidth).toBeCloseTo(initial.editWidth, 1);
    expect(initial.toggleHeight).toBeCloseTo(initial.editHeight, 1);
    expect(initial.toggleWidth).toBeCloseTo(16, 1);
    expect(initial.toggleHeight).toBeCloseTo(16, 1);

    const initiallySkipped = await row.getByRole('button', { name: 'Show Home', exact: true }).count() === 1;
    try {
        await row.getByRole('button', { name: initiallySkipped ? 'Show Home' : 'Skip Home', exact: true }).click();
        await expect(row.getByRole('button', { name: initiallySkipped ? 'Skip Home' : 'Show Home', exact: true })).toBeVisible();

        const changed = await geometry();
        expect(changed).not.toBeNull();
        expect(changed.toggleWidth).toBeCloseTo(initial.toggleWidth, 1);
        expect(changed.toggleHeight).toBeCloseTo(initial.toggleHeight, 1);
        expect(changed.buttonHeight).toBeCloseTo(initial.buttonHeight, 1);
        expect(changed.rowHeight, 'Show Home must never increase the row height').toBeCloseTo(initial.rowHeight, 1);
    } finally {
        const currentlySkipped = await row.getByRole('button', { name: 'Show Home', exact: true }).count() === 1;
        if (currentlySkipped !== initiallySkipped) {
            await row.getByRole('button', { name: currentlySkipped ? 'Show Home' : 'Skip Home', exact: true }).click();
            await expect(row.getByRole('button', { name: initiallySkipped ? 'Show Home' : 'Skip Home', exact: true })).toBeVisible();
        }
    }
});
