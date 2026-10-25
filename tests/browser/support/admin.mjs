import { expect } from '@playwright/test';

export async function signIn(page) {
  const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
  const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
  expect(email, 'Disposable browser admin email is required').toBeTruthy();
  expect(password, 'Disposable browser admin password is required').toBeTruthy();

  await page.goto('/admin/login');
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.getByRole('button', { name: /sign in/i }).click();
  await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/, { timeout: 15_000 });
}
