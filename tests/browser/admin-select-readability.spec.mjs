import { expect, test } from '@playwright/test';

async function signIn(page) {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin test credentials are required');
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
}

async function textCapacity(select) {
    return select.evaluate((el) => {
        const style = getComputedStyle(el);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        context.font = style.font;
        const selected = el.selectedOptions[0]?.textContent.trim() ?? '';
        const textWidth = context.measureText(selected).width;
        const available = el.getBoundingClientRect().width
            - Number.parseFloat(style.paddingLeft)
            - Number.parseFloat(style.paddingRight)
            - Number.parseFloat(style.borderLeftWidth)
            - Number.parseFloat(style.borderRightWidth);
        return { textWidth, available, selected };
    });
}

test('Pages template value is readable before Compact without collision', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 1880, height: 950 });
    await page.goto('/admin/pages');
    const template = page.locator('select[aria-label="Home template"]');
    await expect(template).toBeVisible();
    const size = await textCapacity(template);
    expect(size.selected).toBe('Under Construction');
    expect(size.available, 'Home template has insufficient value space').toBeGreaterThan(size.textWidth + 4);

    for (const width of [1880, 1600, 1440, 1200, 1024, 800]) {
        await page.setViewportSize({ width, height: 950 });
        const geometry = await page.evaluate(() => {
            const workspace = document.querySelector('.admin-workspace');
            const rows = [...document.querySelectorAll('.admin-pages__row')];
            const selection = rows[0]?.querySelector('[data-cell="selection"],.admin-hierarchy__selection');
            if (!workspace || !rows.length || !selection) return null;
            return {
                scrollWidth: workspace.scrollWidth,
                clientWidth: workspace.clientWidth,
                lastSelectionRight: selection.getBoundingClientRect().right,
                workspaceRight: workspace.getBoundingClientRect().right,
            };
        });
        expect(geometry, 'Pages rows not rendered at width ' + width).not.toBeNull();
        expect(geometry.scrollWidth, 'Pages overflowed at width ' + width).toBeLessThanOrEqual(geometry.clientWidth + 1);
        expect(geometry.lastSelectionRight, 'Selection crossed workspace edge').toBeLessThanOrEqual(geometry.workspaceRight + 1);
    }
});

test('Activity Type keeps long selected values readable before filter overflow', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 1880, height: 950 });
    await page.goto('/admin/activity');
    const select = page.locator('.activity-control--type select');
    await expect(select).toBeVisible();
    const option = select.locator('option', { hasText: 'Archive / delete' });
    await expect(option).toHaveCount(1);
    await select.selectOption(await option.getAttribute('value'));
    await expect(select).toHaveValue(await option.getAttribute('value'));
    const size = await textCapacity(select);
    expect(size.available, 'Activity Type has insufficient value space').toBeGreaterThan(size.textWidth + 4);

    for (const width of [1880, 1500, 1300, 1180, 1024, 800]) {
        await page.setViewportSize({ width, height: 950 });
        const layout = await page.evaluate(() => {
            const controls = document.querySelector('.activity-workspace__controls');
            const trigger = controls?.querySelector('.admin-filter-overflow__trigger');
            const select = controls?.querySelector('.activity-control--type select');
            if (!controls || !trigger || !select) return null;
            const workspace = document.querySelector('.admin-workspace');
            return {
                isPopover: getComputedStyle(trigger).display !== 'none',
                typeVisible: getComputedStyle(select).display !== 'none'
                    && select.getClientRects().length > 0
                    && select.getBoundingClientRect().width > 0,
                totalWidth: controls.scrollWidth,
                clientWidth: controls.clientWidth,
                workspaceWidth: workspace?.clientWidth ?? 0,
            };
        });
        expect(layout, 'Activity toolbar absent at width ' + width).not.toBeNull();
        expect(layout.totalWidth, 'Activity controls overlapped or expanded').toBeLessThanOrEqual(layout.clientWidth + 1);
        if (layout.workspaceWidth <= 76 * 16) {
            expect(layout.isPopover, 'Four filters must use the shared overflow at width ' + width).toBe(true);
        } else {
            expect(layout.typeVisible, 'Type should remain visible before overflow').toBe(true);
        }
    }
});
