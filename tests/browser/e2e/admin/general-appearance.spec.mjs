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

  // Make Title visible before checking reduction, then exercise both
  // directions. The first step is conditional so no unchanged selection is
  // mistaken for an autosave that should issue a Livewire request.
  if (originalVisibility === '0') {
    await chooseAndKeepFrame(visible, '1');
  }
  await chooseAndKeepFrame(visible, '0');
  await chooseAndKeepFrame(visible, '1');

  const reduction = titleFields.getByRole('combobox', { name: 'Responsive size', exact: true });
  const originalReduction = await reduction.inputValue();
  await chooseAndKeepFrame(reduction, originalReduction === '1' ? '0' : '1');
  await chooseAndKeepFrame(reduction, originalReduction);

  await stage.getByRole('button', { name: 'Menu', exact: true }).click();
  const divider = stage.locator('[data-appearance-panel="menu"]')
    .getByRole('combobox', { name: 'Menu to page', exact: true });
  const originalDivider = await divider.inputValue();
  await chooseAndKeepFrame(divider, originalDivider === 'full' ? 'fine' : 'full');
  await chooseAndKeepFrame(divider, originalDivider);

  // Local tabs and expanded Stage controls must not remount the iframe.
  await stage.getByRole('button', { name: 'More space' }).click();
  for (const tab of ['Page', 'Global', 'Title']) {
    await stage.getByRole('button', { name: tab, exact: true }).click();
  }
  await stage.getByRole('button', { name: 'Less space' }).click();
  await expect.poll(() => page.evaluate(() =>
    document.querySelector('.general-appearance-stage__preview iframe') === window.__generalBrowserFrame
  )).toBe(true);

  if (originalVisibility === '0') {
    await chooseAndKeepFrame(visible, '0');
  }
  await page.reload();
  await stage.getByRole('button', { name: 'Title', exact: true }).click();
  await expect(visible).toHaveValue(originalVisibility);
  if (originalVisibility === '1') {
    await expect(reduction).toHaveValue(originalReduction);
  }
});

test('Inside Menu swaps title and navigation sides and stacks above them on phones', async ({ page, context }) => {
  await signIn(page);
  await page.setViewportSize({ width: 1900, height: 1000 });
  await page.goto('/admin/general');

  const stage = page.locator('.general-appearance-stage');
  await stage.getByRole('button', { name: 'Title', exact: true }).click();
  const fields = stage.locator('[data-appearance-panel="title"]');
  const visibility = fields.getByRole('combobox', { name: 'Title', exact: true });
  const originalVisibility = await visibility.inputValue();

  const save = async (control, value) => {
    if (await control.inputValue() === value) return;
    const response = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/livewire/update'));
    await control.selectOption(value);
    expect((await response).ok(), 'Title autosave must succeed').toBe(true);
  };

  const publicPage = await context.newPage();
  await publicPage.setViewportSize({ width: 1900, height: 1000 });
  let position, alignment, responsive, initialPosition, initialAlignment, initialResponsive;
  const bounds = () => publicPage.locator('.site-header').evaluate(header => {
    const rect = selector => {
      const element = header.querySelector(selector);
      if (!element) throw new Error('Missing Public header element: ' + selector);
      const box = element.getBoundingClientRect();
      return { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
    };
    return {
      heading: rect('.site-navigation > .site-name-heading'),
      title: rect('.site-title'),
      navigation: rect('.site-navigation__rail:not([hidden]), .site-navigation__submenu:not([hidden])'),
      menu: rect('.site-navigation'),
      stacked: header.classList.contains('is-title-stacked'),
    };
  });

  try {
    await save(visibility, '1');
    position = fields.getByRole('combobox', { name: 'Position', exact: true });
    alignment = fields.getByRole('combobox', { name: 'Alignment', exact: true });
    responsive = fields.getByRole('combobox', { name: 'Responsive alignment', exact: true });
    initialPosition = await position.inputValue();
    initialAlignment = await alignment.inputValue();
    initialResponsive = await responsive.inputValue();
    const options = await position.locator('option').evaluateAll(nodes => nodes.map(node => node.value));
    expect(options).toContain('inside');
    expect(options).toContain('above');
    expect(options).toContain('below');
    expect(options).not.toContain('outside');

    await save(position, 'inside');
    await save(alignment, 'left');
    await publicPage.goto('/preview');
    await expect(publicPage.locator('.site-title')).toBeVisible();
    const left = await bounds();
    expect(left.stacked).toBe(false);
    expect(left.heading.right).toBeLessThanOrEqual(left.navigation.left + 1);
    expect(left.heading.left).toBeGreaterThanOrEqual(left.menu.left - 1);

    await save(alignment, 'right');
    await publicPage.goto('/preview');
    const right = await bounds();
    expect(right.stacked).toBe(false);
    expect(right.heading.left).toBeGreaterThanOrEqual(right.navigation.right - 1);
    expect(right.heading.right).toBeLessThanOrEqual(right.menu.right + 1);
    expect(right.title.left - left.title.left).toBeGreaterThan(100);
    expect(right.navigation.left).toBeLessThan(left.navigation.left);

    await save(responsive, 'center');
    await publicPage.setViewportSize({ width: 520, height: 860 });
    await expect(publicPage.locator('.site-header')).toHaveClass(/is-title-stacked/);
    const mobile = await bounds();
    expect(mobile.heading.bottom).toBeLessThanOrEqual(mobile.navigation.top + 1);
    const titleCenter = (mobile.title.left + mobile.title.right) / 2;
    const menuCenter = (mobile.menu.left + mobile.menu.right) / 2;
    expect(Math.abs(titleCenter - menuCenter)).toBeLessThan(8);
  } finally {
    if (initialPosition && position) await save(position, initialPosition);
    if (initialAlignment && alignment) await save(alignment, initialAlignment);
    if (initialResponsive && responsive) await save(responsive, initialResponsive);
    await save(visibility, originalVisibility);
    await publicPage.close();
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
