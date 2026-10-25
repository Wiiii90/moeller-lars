import { test, expect } from '@playwright/test';
import { randomUUID } from 'node:crypto';
import { signIn } from '../../support/admin.mjs';

test('creating an unpublished Page persists across reload without publishing a public route', async ({ page }, testInfo) => {
  await signIn(page);
  await page.goto('/admin/pages');
  // Targeted reruns may reuse the same disposable database.
  const suffix = randomUUID() + '-' + testInfo.repeatEachIndex + '-' + testInfo.retry;
  const title = 'Browser creation ' + suffix;
  const slug = 'browser-creation-' + suffix;

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
