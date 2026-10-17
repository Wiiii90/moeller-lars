import { expect, test } from '@playwright/test';

// BrowserProfilingSeeder provides a published Gallery and enough top-level
// entries to overflow a phone-width menu.
test('mobile public menu arrows remain inside full-size tappable hit rails', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('/browser-gallery');

    const surface = page.locator('[data-navigation-main]');
    await expect(surface).toHaveClass(/is-overflowing/);
    const previous = surface.locator('button[data-direction="previous"]');
    const next = surface.locator('button[data-direction="next"]');
    await expect(previous).toBeVisible();
    await expect(next).toBeVisible();

    for (const control of [previous, next]) {
        const hit = await control.evaluate(button => {
            const rect = button.getBoundingClientRect();
            const rail = button.closest('[data-navigation-main]').getBoundingClientRect();
            const center = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
            return {
                width: rect.width, height: rect.height,
                withinRail: rect.left >= rail.left - 1 && rect.right <= rail.right + 1,
                clickable: center === button || button.contains(center),
            };
        });
        expect(hit.width).toBeGreaterThanOrEqual(44);
        expect(hit.height).toBeGreaterThanOrEqual(48);
        expect(hit.withinRail).toBe(true);
        expect(hit.clickable).toBe(true);
    }

    const visibleLabels = () => surface.locator('[data-navigation-item]:not([hidden])')
        .allTextContents();
    const before = await visibleLabels();
    await next.click();
    await expect.poll(visibleLabels).not.toEqual(before);
    await previous.click();
    await expect.poll(visibleLabels).toEqual(before);

    // The same layout contract holds as the viewport narrows or widens.
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(next).toBeVisible();
    const right = await next.boundingBox();
    const rail = await surface.boundingBox();
    expect(right.x + right.width).toBeLessThanOrEqual(rail.x + rail.width + 1);
});
