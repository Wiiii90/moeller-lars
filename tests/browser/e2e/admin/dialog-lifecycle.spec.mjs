import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test('admin create dialog closes with Escape and can be opened again', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/pages');

  const open = page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true });
  const heading = page.getByRole('heading', { name: 'Add page', exact: true });

  await open.click();
  await expect(heading).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(heading).toBeHidden();
  await expect(open).toBeEnabled();

  await open.click();
  await expect(heading).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(heading).toBeHidden();
});

test('the page is scrollable again after dismissing an open modal', async ({ page }) => {
  await signIn(page);
  await page.setViewportSize({ width: 1280, height: 360 });
  await page.goto('/admin/pages');
  const height = await page.evaluate(() => document.scrollingElement.scrollHeight);
  const viewport = await page.evaluate(() => document.scrollingElement.clientHeight);
  expect(height, 'The fixture must have scrollable content').toBeGreaterThan(viewport);

  await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();

  await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
  await expect.poll(() => page.evaluate(() => document.scrollingElement.scrollTop)).toBeGreaterThan(0);
});

test('admin modal closes from sidebar, topbar and outer page background', async ({ page }) => {
  await signIn(page);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/admin/pages');

  const open = page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true });
  const heading = page.getByRole('heading', { name: 'Add page', exact: true });

  for (const [region, x, y] of [
    ['sidebar', 24, 180],
    ['topbar', 500, 20],
    ['outer page edge', 1430, 220],
  ]) {
    await open.click();
    await expect(heading).toBeVisible();

    const modal = page.locator('.fi-modal.fi-modal-open').last();
    const hitArea = await modal.locator(':scope > .fi-modal-window-ctn').boundingBox();
    expect(hitArea, `${region} must be within the Filament click-away area`).not.toBeNull();
    expect(hitArea.x).toBe(0);
    expect(hitArea.y).toBe(0);
    expect(hitArea.width).toBe(1440);
    expect(hitArea.height).toBe(900);

    await page.mouse.click(x, y);
    await expect(heading, `clicking ${region} must close the dialog`).toBeHidden();
  }
});
