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


test('short Dashboard stays scrollbar-free during closing transition', async ({ page }) => {
    const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
    const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Local admin test credentials are required');

    // Guarantee an initially scrollbar-free Dashboard without modifying CSS.
    await page.setViewportSize({ width: 1440, height: 3000 });
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);

    const noInitialScrollbar = await page.evaluate(
        () => document.documentElement.scrollHeight <= document.documentElement.clientHeight,
    );
    expect(noInitialScrollbar, 'Dashboard must be short at this viewport for the regression test').toBe(true);

    await page.getByRole('button', { name: 'Settings', exact: true }).first().click();
    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.body.style.position)).toBe('');

    // Capture every animation frame through the native Escape/close transition.
    await page.evaluate(() => {
        window.__modalReleaseFrames = [];
        const started = performance.now();
        function measure() {
            const root = document.documentElement;
            const main = document.querySelector('.fi-main');
            window.__modalReleaseFrames.push({
                scrollbarWidth: window.innerWidth - root.clientWidth,
                mainLeft: main?.getBoundingClientRect().left ?? null,
                bodyPosition: document.body.style.position,
            });
            if (performance.now() - started < 500) requestAnimationFrame(measure);
        }
        requestAnimationFrame(measure);
    });
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await page.waitForTimeout(550);
    const frames = await page.evaluate(() => window.__modalReleaseFrames);
    expect(frames.length).toBeGreaterThan(4);
    const left = frames[0].mainLeft;
    for (const frame of frames) {
        expect(frame.scrollbarWidth, 'Closing briefly showed a native scrollbar').toBe(0);
        expect(frame.bodyPosition, 'Closing toggled body positioning on a short page').toBe('');
        expect(Math.abs(frame.mainLeft - left), 'Closing shifted the workspace').toBeLessThan(1);
    }
});
