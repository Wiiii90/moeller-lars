import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const metricsDirectory = path.resolve('artifacts/performance');
const metricsPath = path.join(metricsDirectory, 'admin-interactions.json');

function byteLength(value) {
  return value ? Buffer.byteLength(value) : 0;
}

function requestPath(request) {
  try {
    return new URL(request.url()).pathname;
  } catch {
    return request.url();
  }
}

class InteractionProfiler {
  constructor(page) {
    this.page = page;
    this.active = null;
    this.pending = new Set();
    this.records = new Map();
    this.flows = [];

    page.on('request', (request) => this.onRequest(request));
    page.on('response', (response) => this.onResponse(response));
    page.on('requestfinished', (request) => this.onRequestFinished(request, null));
    page.on('requestfailed', (request) => this.onRequestFinished(request, request.failure()?.errorText ?? 'request failed'));
    page.on('console', (message) => {
      if (this.active && message.type() === 'error') {
        this.active.console_errors.push(message.text());
      }
    });
    page.on('pageerror', (error) => {
      if (this.active) {
        this.active.page_errors.push(error.message);
      }
    });
  }

  observed(request) {
    const type = request.resourceType();
    return request.isNavigationRequest() || type === 'fetch' || type === 'xhr';
  }

  onRequest(request) {
    if (!this.active || !this.observed(request)) return;

    const startedAt = Date.now();
    const record = {
      method: request.method(),
      path: requestPath(request),
      resource_type: request.resourceType(),
      navigation: request.isNavigationRequest(),
      request_bytes: byteLength(request.postData()),
      response_bytes: null,
      status: null,
      duration_ms: null,
      failure: null,
      started_at_offset_ms: startedAt - this.active.started_at_ms,
      response: null,
      startedAt,
    };

    this.active.requests.push(record);
    this.records.set(request, record);
    this.pending.add(request);
  }

  onResponse(response) {
    const record = this.records.get(response.request());
    if (!record) return;

    record.status = response.status();
    record.response = response;
  }

  onRequestFinished(request, failure) {
    const record = this.records.get(request);
    if (!record) return;

    record.duration_ms = Date.now() - record.startedAt;
    record.failure = failure;
    this.pending.delete(request);
  }

  async run(name, action, verify) {
    if (this.active) throw new Error('Nested interaction profiling is not supported.');

    const flow = {
      name,
      started_at_ms: Date.now(),
      requests: [],
      console_errors: [],
      page_errors: [],
      completed_on_first_click: false,
      duration_ms: null,
      full_navigation_count: null,
      xhr_fetch_count: null,
      request_bytes: null,
      response_bytes: null,
    };
    this.flows.push(flow);
    this.active = flow;

    try {
      await action();
      await verify();
      await expect.poll(() => this.pending.size, {
        message: `${name} should settle its observed request chain`,
        timeout: 10_000,
        intervals: [50, 100, 200],
      }).toBe(0);
      flow.completed_on_first_click = true;
    } finally {
      flow.duration_ms = Date.now() - flow.started_at_ms;
      this.active = null;
    }

    for (const record of flow.requests) {
      const contentLength = record.response?.headers()['content-length'];
      if (contentLength && /^\d+$/.test(contentLength)) {
        record.response_bytes = Number(contentLength);
      } else if (record.response) {
        try {
          record.response_bytes = (await record.response.body()).length;
        } catch {
          record.response_bytes = null;
        }
      }

      delete record.response;
      delete record.startedAt;
    }

    flow.full_navigation_count = flow.requests.filter((request) => request.navigation).length;
    flow.xhr_fetch_count = flow.requests.filter((request) => request.resource_type === 'fetch' || request.resource_type === 'xhr').length;
    flow.request_bytes = flow.requests.reduce((total, request) => total + request.request_bytes, 0);
    const measurableResponseBytes = flow.requests
      .map((request) => request.response_bytes)
      .filter((value) => Number.isInteger(value));
    flow.response_bytes = measurableResponseBytes.length === flow.requests.length
      ? measurableResponseBytes.reduce((total, value) => total + value, 0)
      : null;

    return flow;
  }

  write(gitSha) {
    fs.mkdirSync(metricsDirectory, { recursive: true });
    fs.writeFileSync(metricsPath, `${JSON.stringify({
      schema_version: 1,
      git_sha: gitSha,
      generated_at: new Date().toISOString(),
      flows: this.flows,
    }, null, 2)}\n`);
  }
}

function expectNoBrowserErrors(flow) {
  expect(flow.console_errors, `${flow.name} console errors`).toEqual([]);
  expect(flow.page_errors, `${flow.name} page errors`).toEqual([]);
}

test('profiles representative warmed admin interactions', async ({ page }) => {
  const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
  const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
  expect(email).toBeTruthy();
  expect(password).toBeTruthy();

  const profiler = new InteractionProfiler(page);

  try {
    await page.goto('/admin/login');

    const login = await profiler.run(
      'login_to_dashboard',
      async () => {
        await page.locator('input[type="email"]').fill(email);
        await page.locator('input[type="password"]').fill(password);
        await page.getByRole('button', { name: /sign in/i }).click();
      },
      async () => {
        await expect(page).toHaveURL(/\/admin\/dashboard(?:[/?#]|$)/);
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
      },
    );
    expectNoBrowserErrors(login);

    // Prime Pages once so the measured transition represents a warmed admin navigation.
    await page.goto('/admin/pages');
    await expect(page.getByRole('heading', { name: 'Pages', exact: true })).toBeVisible();
    await page.goto('/admin/dashboard');
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();

    const pagesNavigation = await profiler.run(
      'warmed_navigation_dashboard_to_pages',
      async () => {
        await page.getByRole('link', { name: 'Pages', exact: true }).first().click();
      },
      async () => {
        await expect(page).toHaveURL(/\/admin\/pages(?:[/?#]|$)/);
        await expect(page.getByRole('heading', { name: 'Pages', exact: true })).toBeVisible();
      },
    );
    expectNoBrowserErrors(pagesNavigation);

    const addPage = await profiler.run(
      'pages_add_page_dialog',
      async () => {
        await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
      },
    );
    expect(addPage.full_navigation_count).toBe(0);
    expectNoBrowserErrors(addPage);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();

    await page.goto('/admin/pages/home');
    await expect(page.getByRole('heading', { name: 'Home', exact: true })).toBeVisible();
    await page.waitForLoadState('networkidle');

    const homeSettings = await profiler.run(
      'home_settings_dialog',
      async () => {
        await page.getByRole('button', { name: 'Settings', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('heading', { name: 'Home settings', exact: true })).toBeVisible();
      },
    );
    expect(homeSettings.full_navigation_count).toBe(0);
    expectNoBrowserErrors(homeSettings);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Home settings', exact: true })).toBeHidden();

    await page.goto('/admin/activity');
    await expect(page.getByRole('heading', { name: 'Activity', exact: true })).toBeVisible();
    await page.waitForLoadState('networkidle');

    const commits = await profiler.run(
      'activity_to_commits',
      async () => {
        await page.getByRole('button', { name: 'Commits', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('button', { name: 'Commits', exact: true })).toHaveAttribute('aria-pressed', 'true');
      },
    );
    expect(commits.full_navigation_count).toBe(0);
    expectNoBrowserErrors(commits);

    const idle = await profiler.run(
      'activity_idle_window',
      async () => {
        // This duration is the measurement window itself, not synchronization sleep.
        await page.waitForTimeout(5_000);
      },
      async () => {},
    );
    expect(idle.full_navigation_count).toBe(0);
    expect(idle.xhr_fetch_count).toBe(0);
    expectNoBrowserErrors(idle);
  } finally {
    profiler.write(process.env.GITHUB_SHA ?? 'local');
  }
});
