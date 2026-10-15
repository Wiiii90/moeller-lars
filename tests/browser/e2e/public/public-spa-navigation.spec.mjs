import { expect, test } from '@playwright/test';

// Requires BrowserProfilingSeeder on the disposable database. The pair of
// published public Custom Pages is committed by that seeder, never by this test.
test('public links swap content without a document reload and preserve back/forward history', async ({ page }) => {
    await page.goto('/browser-public-spa-1');
    await expect(page.locator('#content')).toContainText('Public SPA content 1');

    const secondLink = page.locator('[data-site-navigation] a[href$="/browser-public-spa-2"]').first();
    await expect(secondLink).toBeVisible();
    await expect(secondLink).toHaveAttribute('wire:navigate', '');

    // A full HTTP navigation destroys window properties; a Livewire navigate
    // keeps this document and swaps its page content in place.
    await page.evaluate(() => { window.__spaDocumentIdentity = 'retained'; });

    await secondLink.click();
    await expect(page).toHaveURL(/\/browser-public-spa-2(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 2');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');

    await page.goBack();
    await expect(page).toHaveURL(/\/browser-public-spa-1(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 1');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');

    await page.goForward();
    await expect(page).toHaveURL(/\/browser-public-spa-2(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 2');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');
});
