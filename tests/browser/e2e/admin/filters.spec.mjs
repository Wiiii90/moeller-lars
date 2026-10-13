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
    const filters = toolbar.getByRole('group', { name: 'Filters' });
    const trigger = toolbar.getByRole('button', { name: 'Filters', exact: true });
    const fields = filters.locator('label');

    await expect(toolbar).toBeVisible();
    const labels = await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()));
    expect(labels.length, 'Workspace must expose at least one actual filter').toBeGreaterThan(0);

    await page.setViewportSize({ width: 390, height: 850 });
    await expect(trigger).toBeVisible();
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(filters).toBeVisible();
    await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);

    await page.setViewportSize({ width: 1600, height: 900 });
    await expect(trigger).toBeHidden();
    await expect(filters).toBeVisible();
    await expect(fields).toHaveCount(labels.length);
    expect(await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()))).toEqual(labels);
    await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);
  });
}

test('Pages status filter changes the results and survives compact-to-inline transition', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/pages');
  const fixture = page.getByText('Browser fixture page 1', { exact: true });
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
