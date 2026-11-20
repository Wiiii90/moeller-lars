import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(90_000);

test('ordinary native Select uses the canonical picker even outside an Admin workspace', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/general');

  const supportsCustomPicker = await page.evaluate(() =>
    CSS.supports('appearance', 'base-select')
    && CSS.supports('selector(::picker(select))'));
  test.skip(!supportsCustomPicker, 'Browser retains its built-in native picker fallback');

  // Filament moves Account Settings and action dialogs to a separate DOM root
  // rather than under .admin-workspace. The old CSS selector missed these.
  const metrics = await page.evaluate(() => {
    const portal = document.createElement('div');
    portal.className = 'fi-modal';
    portal.innerHTML = '<div class="admin-control-field"><select class="fi-select-input" aria-label="Dialog time"><option>24-hour (23:45)</option><option>12-hour (11:45 PM)</option></select><select class="fi-select-input" size="1" aria-label="Single row"><option>One</option></select></div>';
    document.body.append(portal);
    try {
      const select = portal.querySelector('select');
      const picker = getComputedStyle(select, '::picker(select)');
      const css = getComputedStyle(select);
      return {
        appearance: css.appearance,
        pickerAppearance: picker.appearance,
        pickerBorderStyle: picker.borderTopStyle,
        optionBackground: getComputedStyle(select.options[0]).backgroundColor,
        sizedOneAppearance: getComputedStyle(portal.querySelector('select[size="1"]')).appearance,
        insideWorkspace: Boolean(select.closest('.admin-workspace')),
      };
    } finally {
      portal.remove();
    }
  });

  expect(metrics.insideWorkspace).toBe(false);
  expect(metrics.appearance).toBe('base-select');
  expect(metrics.pickerAppearance).toBe('base-select');
  expect(metrics.sizedOneAppearance).toBe('base-select');
  expect(metrics.pickerBorderStyle).toBe('solid');
  expect(metrics.optionBackground).not.toBe('rgba(0, 0, 0, 0)');
});

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

    await page.mouse.click(x, y);
    await expect(heading, `clicking ${region} must close the dialog`).toBeHidden();
  }
});
