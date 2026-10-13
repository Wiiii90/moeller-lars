import { test, expect } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

const workspaces = [
  { path: '/admin/pages', count: 2, compact: 490 },
  { path: '/admin/activity', count: 4, compact: 840 },
  { path: '/admin/storage', count: 4, compact: 840 },
];

for (const workspace of workspaces) {
  test(workspace.path + ': filters remain a single working control set after resizing', async ({ page }) => {
    await signIn(page);
    await page.goto(workspace.path);
    const toolbar = page.locator('.admin-data-controls--dashboard-responsive').first();
    const owner = toolbar.locator(':scope > .admin-filter-overflow');
    const trigger = owner.locator(':scope > .admin-filter-overflow__trigger');
    const fields = owner.locator(':scope > .admin-data-controls__filters > label');

    await expect(toolbar).toBeVisible();
    await expect(fields).toHaveCount(workspace.count);
    const fieldNodes = await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()));

    await page.setViewportSize({ width: workspace.compact, height: 900 });
    await expect(trigger).toBeVisible();
    await trigger.click();
    await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);

    await page.setViewportSize({ width: 1900, height: 900 });
    await expect(trigger).toBeHidden();
    await expect(owner).toHaveAttribute('data-open', 'false');
    await expect(fields).toHaveCount(workspace.count);
    expect(await fields.evaluateAll(nodes => nodes.map(node => node.textContent.trim()))).toEqual(fieldNodes);
    await expect(toolbar.getByRole('button', { name: 'Clear', exact: true })).toHaveCount(1);
  });
}
