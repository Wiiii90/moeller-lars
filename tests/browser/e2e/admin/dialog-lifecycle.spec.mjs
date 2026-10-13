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
