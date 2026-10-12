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
                viewport: window.innerWidth,
            };
        });
        expect(bounds, 'missing Header/Workspace/User at width ' + width).not.toBeNull();
        expect(Math.abs(bounds.n.left - bounds.w.left), 'Notification left at ' + width).toBeLessThan(1);
        expect(Math.abs(bounds.n.right - bounds.w.right), 'Notification right at ' + width).toBeLessThan(1);
        expect(bounds.b.left - bounds.w.right, 'User overlapping content at ' + width).toBeGreaterThanOrEqual(15);
        expect(bounds.b.right, 'User outside scrollport at ' + width).toBeLessThanOrEqual(bounds.viewport);
    }

    // Burger shell: the header uses separate Burger/User rails, while page
    // content below reclaims the full viewport width.
    for (const width of [900, 640, 390]) {
        await page.setViewportSize({ width, height: 900 });
        const bounds = await page.evaluate(() => {
            const n = document.querySelector('[data-admin-header-notification]');
            const b = document.querySelector('.fi-topbar-end .fi-user-menu-trigger');
            if (!n || !b) return null;
            n.hidden = false;
            n.dataset.phase = 'idle';
            return {
                n: n.getBoundingClientRect().toJSON(),
                b: b.getBoundingClientRect().toJSON(),
                viewport: window.innerWidth,
            };
        });
        expect(bounds, 'missing Header/User in Burger mode at ' + width).not.toBeNull();
        expect(bounds.n.left, 'Header entered Burger control rail at ' + width).toBeGreaterThanOrEqual(63);
        expect(bounds.b.left - bounds.n.right, 'Header entered User control rail at ' + width).toBeGreaterThanOrEqual(15);
        expect(bounds.b.right, 'User outside viewport at ' + width).toBeLessThanOrEqual(bounds.viewport);
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

test('Header Notification retains workspace width under native modal body scroll lock', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');

    // A scrollable page forces Filament's native body-fixed locking path.
    await page.setViewportSize({ width: 1440, height: 600 });
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);

    await page.getByRole('button', { name: 'Settings', exact: true }).first().click();
    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();

    try {
        await expect.poll(() => page.evaluate(() => document.body.style.position)).toBe('fixed');

        for (const width of [1900, 1600, 1440, 1200, 1100, 1024]) {
            await page.setViewportSize({ width, height: 600 });
            const bounds = await page.evaluate(() => {
                const notification = document.querySelector('[data-admin-header-notification]');
                const workspace = document.querySelector('.fi-main .admin-workspace');
                const user = document.querySelector('.fi-topbar-end .fi-user-menu-trigger');
                if (!notification || !workspace || !user) return null;

                // Geometry only: no synthetic Notification and no queue/timer changes.
                notification.hidden = false;
                notification.dataset.phase = 'idle';

                return {
                    notification: notification.getBoundingClientRect().toJSON(),
                    workspace: workspace.getBoundingClientRect().toJSON(),
                    user: user.getBoundingClientRect().toJSON(),
                    order: Boolean(workspace.compareDocumentPosition(notification)
                        & Node.DOCUMENT_POSITION_FOLLOWING),
                    bodyFixed: getComputedStyle(document.body).position === 'fixed',
                };
            });

            expect(bounds, 'Missing Header/Workspace/User at width ' + width).not.toBeNull();
            expect(bounds.order, 'Notification must follow its anchor in document order').toBe(true);
            expect(bounds.bodyFixed, 'Modal must exercise Filament body-fixed lock').toBe(true);
            expect(Math.abs(bounds.notification.left - bounds.workspace.left), 'Left edge at ' + width).toBeLessThan(1);
            expect(Math.abs(bounds.notification.right - bounds.workspace.right), 'Right edge at ' + width).toBeLessThan(1);
            expect(bounds.user.left - bounds.workspace.right, 'User collision at ' + width).toBeGreaterThanOrEqual(15);
        }
    } finally {
        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
    }
});


test('Header dismisses one current Notification at a time and advances its queue', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin credentials are required');

    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);

    await page.evaluate(() => {
        for (const index of [1, 2, 3]) {
            window.dispatchEvent(new CustomEvent('admin-header-notification', {
                detail: { id: 'dismiss-regression-' + index, title: 'Message ' + index, status: 'info' },
            }));
        }
    });

    const header = page.locator('[data-admin-header-notification]');
    const title = header.locator('[data-admin-header-notification-title]');
    const count = header.locator('[data-admin-header-notification-counter]');
    const dismiss = header.getByRole('button', { name: 'Dismiss notification' });

    await expect(title).toHaveText('Message 1');
    await expect(count).toHaveText('+2');
    await dismiss.click();
    await expect(title).toHaveText('Message 2');
    await expect(count).toHaveText('+1');
    await dismiss.click();
    await expect(title).toHaveText('Message 3');
    await expect(count).toBeHidden();
    await dismiss.click();
    await expect(header).toBeHidden();
});
