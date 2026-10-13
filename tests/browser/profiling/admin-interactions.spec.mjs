import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { signIn } from '../support/admin.mjs';

const metricsFile = path.resolve('artifacts/performance/admin-interactions.json');

test('representative admin operations finish without unexpected navigation or runtime errors', async ({ page }) => {
  const flows = [];
  async function measure(name, action, verify) {
    const observed = [];
    const errors = [];
    const started = Date.now();
    const onRequest = request => {
      if (request.isNavigationRequest() || ['fetch', 'xhr'].includes(request.resourceType())) observed.push({
        navigation: request.isNavigationRequest(),
        resource: request.resourceType(),
        request_bytes: Buffer.byteLength(request.postData() ?? ''),
      });
    };
    const onConsole = message => { if (message.type() === 'error') errors.push(message.text()); };
    const onPageError = error => errors.push(error.message);
    page.on('request', onRequest);
    page.on('console', onConsole);
    page.on('pageerror', onPageError);
    try {
      await action();
      await verify();
    } finally {
      page.off('request', onRequest);
      page.off('console', onConsole);
      page.off('pageerror', onPageError);
      flows.push({
        name,
        full_navigation_count: observed.filter(r => r.navigation).length,
        xhr_fetch_count: observed.filter(r => r.resource === 'fetch' || r.resource === 'xhr').length,
        request_bytes: observed.reduce((sum, r) => sum + r.request_bytes, 0),
        duration_ms: Date.now() - started,
        console_errors: errors,
      });
    }
    const flow = flows.at(-1);
    expect(flow.console_errors, name + ': runtime errors').toEqual([]);
    expect(flow.full_navigation_count, name + ': unexpected document navigation').toBe(0);
  }

  try {
    await signIn(page);
    await page.goto('/admin/pages');
    await expect(page.getByRole('heading', { name: 'Pages', exact: true })).toBeVisible();
    await page.goto('/admin/dashboard');
    await measure('warmed_navigation_dashboard_to_pages',
      () => page.getByRole('link', { name: 'Pages', exact: true }).first().click(),
      () => expect(page.getByRole('heading', { name: 'Pages', exact: true })).toBeVisible(),
    );
    await measure('pages_add_page_dialog',
      () => page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click(),
      () => expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible(),
    );
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();

    await page.goto('/admin/activity');
    await expect(page.getByRole('heading', { name: 'Activity', exact: true })).toBeVisible();
    await measure('activity_to_commits',
      () => page.getByRole('button', { name: 'Commits', exact: true }).click(),
      () => expect(page.getByRole('button', { name: 'Commits', exact: true })).toHaveAttribute('aria-pressed', 'true'),
    );
  } finally {
    fs.mkdirSync(path.dirname(metricsFile), { recursive: true });
    fs.writeFileSync(metricsFile, JSON.stringify({ flows }, null, 2));
  }
});
