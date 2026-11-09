import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(90_000);

test('General Preview stays mounted through Title saves and reloads with persisted options', async ({ page }) => {
  await signIn(page);
  await page.setViewportSize({ width: 1900, height: 1000 });
  await page.goto('/admin/general');

  const stage = page.locator('.general-appearance-stage');
  const preview = stage.locator('.general-appearance-stage__preview');
  const frame = preview.locator('iframe[title="Interactive live preview of the public homepage"]');
  await expect(preview).toBeVisible();
  await expect(frame).toHaveCount(1);
  await expect.poll(() => preview.locator('[x-ref="device"]').evaluate(node => node.clientWidth)).toBeGreaterThan(1);

  // Compare the actual frame node, not just its count: Livewire may morph
  // surrounding fields, but must not replace the live Public preview.
  await page.evaluate(() => {
    window.__generalBrowserFrame = document.querySelector('.general-appearance-stage__preview iframe');
  });

  await stage.getByRole('button', { name: 'Title', exact: true }).click();
  const titleFields = stage.locator('[data-appearance-panel="title"]');
  const visible = titleFields.getByRole('combobox', { name: 'Title', exact: true });
  const originalVisibility = await visible.inputValue();

  const chooseAndKeepFrame = async (control, value) => {
    const save = page.waitForResponse(response => new URL(response.url()).pathname.endsWith('/livewire/update'));
    await control.selectOption(value);
    expect((await save).ok(), 'Appearance autosave must succeed').toBe(true);
    await expect.poll(() => page.evaluate(() => {
      const frameNode = document.querySelector('.general-appearance-stage__preview iframe');
      const device = document.querySelector('.general-live-preview__device');
      return frameNode === window.__generalBrowserFrame
        && frameNode?.isConnected
        && device?.clientWidth > 1;
    })).toBe(true);
  };

  await chooseAndKeepFrame(visible, originalVisibility === '1' ? '0' : '1');
  await chooseAndKeepFrame(visible, '1');

  const reduction = titleFields.getByRole('combobox', { name: 'Responsive size', exact: true });
  const originalReduction = await reduction.inputValue();
  await chooseAndKeepFrame(reduction, originalReduction === '1' ? '0' : '1');
  await chooseAndKeepFrame(reduction, originalReduction);

  await chooseAndKeepFrame(visible, originalVisibility);
  await page.reload();
  await stage.getByRole('button', { name: 'Title', exact: true }).click();
  await expect(visible).toHaveValue(originalVisibility);
  if (originalVisibility === '1') {
    await expect(reduction).toHaveValue(originalReduction);
  }
});

test('General Site icon retains searchable Storage dropdown outside the clipped field pane', async ({ page }) => {
  await signIn(page);
  await page.setViewportSize({ width: 1900, height: 1000 });
  await page.goto('/admin/general');

  const siteIcon = page.locator('.general-site-icon-control');
  const trigger = siteIcon.locator('.fi-select-input-btn');
  await expect(trigger).toBeVisible();
  await trigger.click();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');

  const dropdown = siteIcon.locator('.fi-dropdown-panel');
  await expect(dropdown).toBeVisible();
  await expect(dropdown).toHaveCSS('position', 'fixed');

  const search = dropdown.getByRole('combobox');
  await expect(search).toBeVisible();
  await search.fill('no-browser-fixture-asset-matches-this-name');
  await expect(dropdown.getByText('No matching files in Storage')).toBeVisible();

  await search.press('Escape');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect(dropdown).toBeHidden();
});
