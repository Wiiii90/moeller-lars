import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('Hero group arrows cycle only the rendered group members and retain keyboard focus', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>',
        '<div data-home-hero data-artwork-viewer-sequence>',
        ...['one', 'two', 'three'].map((name, index) => [
            '<div data-home-hero-slide ' + (index ? 'hidden' : '') + ' data-test-slide="' + name + '">',
            // Paired with regular Artwork context controls, without a gallery button.
            '<nav class="artwork-card__actions">',
            '<button data-home-hero-direction="previous">Previous</button>',
            '<button data-home-hero-direction="next">Next</button>',
            '</nav>',
            '</div>',
        ].join('')),
        '</div>',
        '</body></html>',
    ].join(''));

    const source = readFileSync(
        new URL('../../../../resources/js/home-hero.js', import.meta.url), 'utf8',
    );
    await page.addScriptTag({
        content: source.replace(/^export /gm, '')
            + '\nwindow.initializeHomeHero = initializeHomeHero;',
    });
    await page.evaluate(() => window.initializeHomeHero());

    const slides = page.locator('[data-home-hero-slide]');
    await expect(slides.nth(0)).toBeVisible();
    await slides.nth(0).locator('[data-home-hero-direction="previous"]').click();
    await expect(slides.nth(2)).toBeVisible();
    await expect(slides.nth(0)).toBeHidden();
    await expect(slides.nth(2).locator('[data-home-hero-direction="previous"]')).toBeFocused();

    await slides.nth(2).locator('[data-home-hero-direction="next"]').click();
    await expect(slides.nth(0)).toBeVisible();

    await slides.nth(0).locator('[data-home-hero-direction="next"]').focus();
    await page.keyboard.press('Enter');
    await expect(slides.nth(1)).toBeVisible();
    await expect(slides.nth(1).locator('[data-home-hero-direction="next"]')).toBeFocused();
});
