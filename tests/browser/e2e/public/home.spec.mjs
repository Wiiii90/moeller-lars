import { test, expect } from '@playwright/test';

test('public Home renders a real HTML response', async ({ page }) => {
  const response = await page.goto('/');
  expect(response).not.toBeNull();
  expect(response.status()).toBe(200);
  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('main')).toHaveCount(1);
});
