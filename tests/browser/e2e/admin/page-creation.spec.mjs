import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test('creating an unpublished Page persists across reload without publishing a public route', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/pages');
  const unique = Date.now().toString(36);
  const title = 'Browser creation ' + unique;
  const slug = 'browser-creation-' + unique;

  await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
  await page.locator('[data-admin-control="name"] input').fill(title);
  await page.locator('[data-admin-control="slug"] input').fill(slug);
  await page.getByRole('button', { name: 'Create page', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();
  await expect(page.getByText(title, { exact: true }).first()).toBeVisible();

  await page.reload();
  await expect(page.getByText(title, { exact: true }).first()).toBeVisible();
  const publicResponse = await page.request.get('/' + slug, { maxRedirects: 0 });
  expect(publicResponse.status()).toBe(404);
});
