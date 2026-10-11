import { expect, test } from '@playwright/test';

async function signIn(page) {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
}

const cases = [
    { path: '/admin/pages', count: 2, narrow: 490, wide: 1900 },
    { path: '/admin/activity', count: 4, narrow: 840, wide: 1900 },
    { path: '/admin/storage', count: 4, narrow: 840, wide: 1900 },
];

for (const scenario of cases) {
    test(scenario.path + ': opening Filters then widening never clones controls or Clear', async ({ page }) => {
        await signIn(page);
        await page.goto(scenario.path);
        const toolbar = page.locator('.admin-data-controls--dashboard-responsive').first();
        await expect(toolbar).toBeVisible();

        const owner = toolbar.locator(':scope > .admin-filter-overflow');
        const trigger = owner.locator(':scope > .admin-filter-overflow__trigger');
        const panel = owner.locator(':scope > .admin-data-controls__filters');
        const filterControls = panel.locator(':scope > label');
        await expect(filterControls).toHaveCount(scenario.count);

        const initialIds = await filterControls.evaluateAll((fields) =>
            fields.map((field) => field.querySelector('input, select')?.getAttribute('wire:model.live')
                ?? field.querySelector('input, select')?.getAttribute('wire:model')
                ?? field.textContent.trim().slice(0, 50)),
        );

        for (let cycle = 0; cycle < 2; cycle++) {
            await page.setViewportSize({ width: scenario.narrow, height: 950 });
            await expect(trigger).toBeVisible();
            await trigger.click();
            await expect.poll(async () => panel.evaluate((node) => getComputedStyle(node).display)).toBe('grid');
            await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);

            const popup = await panel.evaluate((node) => {
                const boxes = [...node.children].filter((child) => child.matches('label'))
                    .map((child) => child.getBoundingClientRect().toJSON());
                const rect = node.getBoundingClientRect();
                return {
                    boxes,
                    left: rect.left,
                    right: rect.right,
                    viewport: window.innerWidth,
                    overflow: node.scrollWidth - node.clientWidth,
                };
            });
            expect(popup.boxes).toHaveLength(scenario.count);
            expect(popup.left).toBeGreaterThanOrEqual(7);
            expect(popup.right).toBeLessThanOrEqual(popup.viewport - 7);
            expect(popup.overflow).toBeLessThanOrEqual(1);
            for (let i = 1; i < popup.boxes.length; i++) {
                expect(popup.boxes[i].top, 'filter ' + i + ' must start below previous')
                    .toBeGreaterThanOrEqual(popup.boxes[i - 1].bottom);
                expect(popup.boxes[i].left).toBeGreaterThanOrEqual(popup.left);
                expect(popup.boxes[i].right).toBeLessThanOrEqual(popup.right);
            }

            // Expand WITHOUT closing Filters: no leftover Clear row.
            await page.setViewportSize({ width: scenario.wide, height: 950 });
            await expect(trigger).toBeHidden();
            await expect.poll(async () => owner.getAttribute('data-open')).toBe('false');
            await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);
            await expect(panel.locator('.admin-filter-overflow__reset')).toBeHidden();

            const wide = await toolbar.evaluate((node) => {
                const search = node.firstElementChild.getBoundingClientRect();
                const reset = node.querySelector('.admin-data-controls__utility--has-reset');
                return {
                    ownerCount: node.querySelectorAll(':scope > .admin-filter-overflow').length,
                    fieldsCount: node.querySelectorAll('.admin-filter-overflow > .admin-data-controls__filters > label').length,
                    height: node.getBoundingClientRect().height,
                    resetTop: reset?.getBoundingClientRect().top,
                    searchTop: search.top,
                    scrollOver: node.scrollWidth - node.clientWidth,
                };
            });
            expect(wide.ownerCount).toBe(1);
            expect(wide.fieldsCount).toBe(scenario.count);
            expect(wide.height, 'Filters must not create a second toolbar row').toBeLessThan(90);
            expect(wide.scrollOver, 'Toolbar must not overflow horizontally').toBeLessThanOrEqual(1);
            expect(Math.abs(wide.resetTop - wide.searchTop), 'Clear slipped below Search').toBeLessThan(55);

            const nowIds = await filterControls.evaluateAll((fields) =>
                fields.map((field) => field.querySelector('input, select')?.getAttribute('wire:model.live')
                    ?? field.querySelector('input, select')?.getAttribute('wire:model')
                    ?? field.textContent.trim().slice(0, 50)),
            );
            expect(nowIds, 'Responsive conversion must reuse the same filter controls').toEqual(initialIds);
        }
    });
}
