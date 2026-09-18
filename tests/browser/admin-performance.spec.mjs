import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const metricsDirectory = path.resolve('artifacts/performance');
const metricsPath = path.join(metricsDirectory, 'admin-interactions.json');
const KiB = 1024;

const structuralBudgets = Object.freeze({
  warmed_navigation_dashboard_to_pages: {
    completed_on_first_click: true,
    full_navigation_count: 1,
    max_xhr_fetch_count: 0,
  },
  pages_add_page_dialog: {
    completed_on_first_click: true,
    full_navigation_count: 0,
    max_xhr_fetch_count: 2,
    max_response_bytes: 44 * KiB,
  },
  pages_create_custom_page: {
    completed_on_first_click: true,
    full_navigation_count: 0,
    max_xhr_fetch_count: 2,
  },
  home_settings_dialog: {
    completed_on_first_click: true,
    full_navigation_count: 0,
    max_xhr_fetch_count: 2,
    max_response_bytes: 76 * KiB,
  },
  activity_to_commits: {
    completed_on_first_click: true,
    full_navigation_count: 0,
    max_xhr_fetch_count: 2,
    max_response_bytes: 640 * KiB,
  },
  activity_filter_area: {
    completed_on_first_click: true,
    full_navigation_count: 1,
    max_xhr_fetch_count: 0,
  },
  activity_idle_window: {
    completed_on_first_click: true,
    full_navigation_count: 0,
    max_xhr_fetch_count: 0,
    max_response_bytes: 0,
  },
});

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
      structural_budget: structuralBudgets[name] ?? null,
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

async function shellGeometry(page) {
  return page.evaluate(() => {
    const root = document.documentElement;
    const layout = document.querySelector('.fi-layout');
    const main = document.querySelector('.fi-main');
    const rootStyle = getComputedStyle(root);

    return {
      inner_width: window.innerWidth,
      client_width: root.clientWidth,
      layout_left: layout?.getBoundingClientRect().left ?? null,
      layout_width: layout?.getBoundingClientRect().width ?? null,
      main_left: main?.getBoundingClientRect().left ?? null,
      main_width: main?.getBoundingClientRect().width ?? null,
      has_classic_scrollbar: window.innerWidth > root.clientWidth,
      modal_existing_scrollbar: root.classList.contains('admin-modal-existing-scrollbar'),
      overflow_y: rootStyle.overflowY,
      padding_right: rootStyle.paddingRight,
      scrollbar_gutter: rootStyle.scrollbarGutter,
    };
  });
}

function expectStableShellGeometry(before, during, context) {
  expect(during.client_width, `${context}: document client width shifted`).toBe(before.client_width);
  expect(during.layout_left, `${context}: layout left edge shifted`).toBeCloseTo(before.layout_left, 1);
  expect(during.layout_width, `${context}: layout width shifted`).toBeCloseTo(before.layout_width, 1);
  expect(during.main_left, `${context}: main left edge shifted`).toBeCloseTo(before.main_left, 1);
  expect(during.main_width, `${context}: main width shifted`).toBeCloseTo(before.main_width, 1);
}

function expectModalScrollbarMode(before, during, context) {
  const hadClassicScrollbar = before.inner_width > before.client_width;
  expect(
    during.modal_existing_scrollbar,
    `${context}: long-page modal mode must match the pre-open classic scrollbar state`,
  ).toBe(hadClassicScrollbar);

  if (hadClassicScrollbar) {
    expect(during.has_classic_scrollbar, `${context}: existing scrollbar disappeared`).toBe(true);
    expect(during.overflow_y, `${context}: existing scrollbar is not kept visible`).toBe('scroll');
    expect(during.padding_right, `${context}: Filament padding compensation leaked through`).toBe('0px');
    expect(during.scrollbar_gutter, `${context}: empty stable gutter was introduced`).toBe('auto');
  }
}

function expectStructuralBudget(flow) {
  const budget = structuralBudgets[flow.name];
  expect(budget, `${flow.name} must have a structural performance budget`).toBeDefined();

  expect(
    flow.completed_on_first_click,
    `${flow.name}: interaction did not complete on the first deliberate action`,
  ).toBe(budget.completed_on_first_click);

  expect(
    flow.full_navigation_count,
    `${flow.name}: measured ${flow.full_navigation_count} full navigation(s); expected ${budget.full_navigation_count}`,
  ).toBe(budget.full_navigation_count);

  expect(
    flow.xhr_fetch_count,
    `${flow.name}: measured ${flow.xhr_fetch_count} Fetch/XHR request(s); budget is <= ${budget.max_xhr_fetch_count}`,
  ).toBeLessThanOrEqual(budget.max_xhr_fetch_count);

  if (budget.max_response_bytes !== undefined) {
    expect(
      flow.response_bytes,
      `${flow.name}: response payload was not measurable; budget is <= ${budget.max_response_bytes} bytes`,
    ).not.toBeNull();
    expect(
      flow.response_bytes,
      `${flow.name}: measured ${flow.response_bytes} response bytes; budget is <= ${budget.max_response_bytes}`,
    ).toBeLessThanOrEqual(budget.max_response_bytes);
  }
}

test('profiles representative warmed admin interactions', async ({ page }, testInfo) => {
  const email = process.env.PLAYWRIGHT_ADMIN_EMAIL;
  const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD;
  expect(email).toBeTruthy();
  expect(password).toBeTruthy();

  const profiler = new InteractionProfiler(page);
  const profilePageName = `Playwright profile page ${testInfo.retry + 1}`;
  const profilePageSlug = `playwright-profile-page-${testInfo.retry + 1}`;

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
    expectStructuralBudget(pagesNavigation);
    expectNoBrowserErrors(pagesNavigation);

    const pagesBeforeDialogGeometry = await shellGeometry(page);

    const addPage = await profiler.run(
      'pages_add_page_dialog',
      async () => {
        await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
      },
    );
    expectStructuralBudget(addPage);
    expectNoBrowserErrors(addPage);
    const pagesDuringDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(pagesBeforeDialogGeometry, pagesDuringDialogGeometry, 'Pages Add page open');
    expectModalScrollbarMode(pagesBeforeDialogGeometry, pagesDuringDialogGeometry, 'Pages Add page open');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();
    const pagesAfterDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(pagesBeforeDialogGeometry, pagesAfterDialogGeometry, 'Pages Add page close');
    expect(pagesAfterDialogGeometry.modal_existing_scrollbar, 'Pages Add page close: modal scrollbar class leaked').toBe(false);

    // Explicitly cover a page that already has a classic vertical scrollbar.
    // The production bug only appeared on long table pages, while short pages
    // were already stable.
    await page.evaluate(() => {
      const spacer = document.createElement('div');
      spacer.dataset.modalScrollbarTestSpacer = 'true';
      spacer.style.height = '200vh';
      spacer.style.pointerEvents = 'none';
      document.querySelector('.fi-main')?.append(spacer);
    });
    const longPageBeforeDialogGeometry = await shellGeometry(page);
    expect(longPageBeforeDialogGeometry.has_classic_scrollbar, 'Long-page fixture must have a classic scrollbar').toBe(true);

    await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
    const longPageDuringDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(longPageBeforeDialogGeometry, longPageDuringDialogGeometry, 'Pages long-page Add page open');
    expectModalScrollbarMode(longPageBeforeDialogGeometry, longPageDuringDialogGeometry, 'Pages long-page Add page open');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();
    const longPageAfterDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(longPageBeforeDialogGeometry, longPageAfterDialogGeometry, 'Pages long-page Add page close');
    expect(longPageAfterDialogGeometry.modal_existing_scrollbar, 'Pages long-page Add page close: modal scrollbar class leaked').toBe(false);
    await page.evaluate(() => document.querySelector('[data-modal-scrollbar-test-spacer]')?.remove());

    await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeVisible();
    await page.locator('[data-admin-control="name"] input').fill(profilePageName);
    await page.locator('[data-admin-control="slug"] input').fill(profilePageSlug);

    const createPage = await profiler.run(
      'pages_create_custom_page',
      async () => {
        await page.getByRole('button', { name: 'Create page', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('heading', { name: 'Add page', exact: true })).toBeHidden();
        await expect(page.getByText(profilePageName, { exact: true }).first()).toBeVisible();
      },
    );
    expectStructuralBudget(createPage);
    expectNoBrowserErrors(createPage);

    await page.goto('/admin/pages/home');
    await expect(page.getByRole('heading', { name: 'Home', exact: true })).toBeVisible();
    await page.waitForLoadState('networkidle');

    const homeBeforeDialogGeometry = await shellGeometry(page);

    const homeSettings = await profiler.run(
      'home_settings_dialog',
      async () => {
        await page.getByRole('button', { name: 'Settings', exact: true }).click();
      },
      async () => {
        await expect(page.getByRole('heading', { name: 'Home settings', exact: true })).toBeVisible();
      },
    );
    expectStructuralBudget(homeSettings);
    expectNoBrowserErrors(homeSettings);
    const homeDuringDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(homeBeforeDialogGeometry, homeDuringDialogGeometry, 'Home settings open');
    expectModalScrollbarMode(homeBeforeDialogGeometry, homeDuringDialogGeometry, 'Home settings open');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: 'Home settings', exact: true })).toBeHidden();
    const homeAfterDialogGeometry = await shellGeometry(page);
    expectStableShellGeometry(homeBeforeDialogGeometry, homeAfterDialogGeometry, 'Home settings close');
    expect(homeAfterDialogGeometry.modal_existing_scrollbar, 'Home settings close: modal scrollbar class leaked').toBe(false);

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
    expectStructuralBudget(commits);
    expectNoBrowserErrors(commits);

    const areaFilter = await profiler.run(
      'activity_filter_area',
      async () => {
        const trigger = page.getByRole('combobox', { name: 'Area' });
        await trigger.click();
        await page.getByRole('listbox', { name: 'Area' }).getByText('Website', { exact: true }).click();
      },
      async () => {
        await expect(page).toHaveURL(/(?:\?|&)area=Website(?:&|$)/);
        await expect(page.getByRole('combobox', { name: 'Area' })).toContainText('Website');
      },
    );
    expectStructuralBudget(areaFilter);
    expectNoBrowserErrors(areaFilter);

    const idle = await profiler.run(
      'activity_idle_window',
      async () => {
        // This duration is the measurement window itself, not synchronization sleep.
        await page.waitForTimeout(5_000);
      },
      async () => {},
    );
    expectStructuralBudget(idle);
    expectNoBrowserErrors(idle);
  } finally {
    profiler.write(process.env.GITHUB_SHA ?? 'local');
  }
});
