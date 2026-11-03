import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

function artworks() {
    const path = process.env.PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE;
    expect(path, 'Use the independently verified disposable browser fixture manifest').toBeTruthy();
    const { gallery } = JSON.parse(readFileSync(path, 'utf8'));
    expect(gallery.artworks).toHaveLength(2);

    return gallery.artworks;
}

for (const viewport of [
    { width: 1440, height: 900 },
    { width: 844, height: 390 },
    { width: 390, height: 844 },
]) {
    for (const [index, orientation] of ['landscape', 'portrait'].entries()) {
        test(`${orientation} Artwork remains undistorted and viewport-bounded at ${viewport.width}×${viewport.height}`, async ({ page }) => {
            await page.setViewportSize(viewport);
            const artwork = artworks()[index];
            await page.goto(artwork.url);
            const content = page.locator('#content');
            await expect(content).toHaveClass(/site-content--fitted-artwork/);
            const image = page.locator('.artwork-detail__image');
            await expect(image).toBeVisible();
            await expect.poll(() => image.evaluate(element => element.complete && element.naturalWidth > 0))
                .toBe(true);

            const geometry = await image.evaluate(element => {
                const box = element.getBoundingClientRect();
                return {
                    width: box.width, height: box.height,
                    sourceWidth: element.naturalWidth, sourceHeight: element.naturalHeight,
                    viewportWidth: document.documentElement.clientWidth,
                    viewportHeight: window.visualViewport?.height ?? window.innerHeight,
                };
            });

            expect(geometry.width).toBeGreaterThan(0);
            expect(geometry.height).toBeGreaterThan(0);
            expect(geometry.width).toBeLessThanOrEqual(geometry.viewportWidth + 2);
            expect(geometry.height).toBeLessThanOrEqual(geometry.viewportHeight + 2);
            expect(Math.abs(geometry.width / geometry.height - geometry.sourceWidth / geometry.sourceHeight))
                .toBeLessThan(0.02);
        });
    }
}
