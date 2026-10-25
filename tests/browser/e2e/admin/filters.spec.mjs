import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

// The count and ordering of editorial fields are not a responsive UI contract.
// A single Filters group must survive the transition from compact to inline.
const workspaces = ['/admin/pages', '/admin/activity', '/admin/storage'];

for (const path of workspaces) {
  test(path + ': filter controls remain unique and usable after switching layout', async ({ page }) => {
    await signIn(page);
    await page.goto(path);
    const toolbar = page.locator('.admin-data-controls--dashboard-responsive').first();
    const filters = toolbar.locator('.admin-data-controls__filters');
    const trigger = toolbar.getByRole('button', { name: 'Filters', exact: true });
    const fields = filters.locator('label');

    await expect(toolbar).toBeVisible();
    const labels = await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()));
    expect(labels.length, 'Workspace must expose at least one actual filter').toBeGreaterThan(0);

    await page.setViewportSize({ width: 390, height: 850 });
    await expect(trigger).toBeVisible();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(filters).toBeVisible();
    await expect(filters.locator('.admin-filter-overflow__reset .admin-action')).toHaveCount(1);

    await page.setViewportSize({ width: 2200, height: 900 });
    const layout = await toolbar.evaluate(root => ({
      workspaceWidth: root.closest('.admin-workspace')?.getBoundingClientRect().width ?? 0,
      triggerDisplay: getComputedStyle(root.querySelector('.admin-filter-overflow__trigger')).display,
    }));
    expect(layout.workspaceWidth, 'Responsive mode must follow the actual workspace container').toBeGreaterThan(0);
    if (layout.triggerDisplay === 'none') {
      await expect(trigger).toBeHidden();
    } else {
      await expect(trigger).toBeVisible();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    }
    await expect(filters).toBeVisible();
    await expect(fields).toHaveCount(labels.length);
    expect(await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()))).toEqual(labels);
    await expect(filters.locator('.admin-filter-overflow__reset .admin-action')).toHaveCount(1);

    if (path === '/admin/activity' || path === '/admin/storage') {
      const filter = path === '/admin/activity' ? filters.getByLabel('Area') : filters.getByLabel('Type');
      const value = path === '/admin/activity' ? 'Publication' : 'image';
      const update = page.waitForResponse(response => new URL(response.url()).pathname.endsWith('/update'));
      await filter.selectOption(value);
      expect((await update).ok(), 'Responsive filter change must complete its Livewire update').toBe(true);
      await expect(filter).toHaveValue(value);
    }
  });
}

test('Pages status filter changes the results and survives compact-to-inline transition', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/pages');
  const fixture = page.getByRole('table', { name: 'Pages' })
    .getByRole('link', { name: 'Browser fixture page 1', exact: true });
  const status = page.getByRole('combobox', { name: 'Filter by page status' });
  const controls = page.getByLabel('Page controls');
  await expect(fixture).toBeVisible();

  await page.setViewportSize({ width: 390, height: 850 });
  await controls.getByRole('button', { name: 'Filters', exact: true }).click();
  await status.selectOption('published');
  await expect(fixture).toBeHidden();

  await page.setViewportSize({ width: 1600, height: 900 });
  await expect(status).toHaveValue('published');
  await controls.getByRole('button', { name: 'Clear', exact: true }).click();
  await expect(status).toHaveValue('');
  await expect(fixture).toBeVisible();
});
