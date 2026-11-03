import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/browser',
  outputDir: 'test-results',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [
    ['line'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 15_000,
  },
  projects: [
    { name: 'component-chromium', testMatch: '**/component/**/*.spec.mjs', use: { ...devices['Desktop Chrome'] } },
    { name: 'e2e-chromium', testMatch: '**/e2e/**/*.spec.mjs', use: { ...devices['Desktop Chrome'] } },
    // These projects qualify the Gallery's native Sortable handle under WebKit.
    // Desktop WebKit approximates Safari; macOS Safari acceptance is a separate gate.
    { name: 'e2e-gallery-webkit-desktop', testMatch: ['**/e2e/admin/gallery-ordering.spec.mjs', '**/e2e/public/artwork-fit.spec.mjs'], use: { ...devices['Desktop Safari'] } },
    { name: 'e2e-gallery-webkit-mobile', testMatch: ['**/e2e/admin/gallery-ordering.spec.mjs', '**/e2e/public/artwork-fit.spec.mjs'], use: { ...devices['iPhone 13'] } },
    { name: 'e2e-gallery-chromium-mobile', testMatch: ['**/e2e/admin/gallery-ordering.spec.mjs', '**/e2e/public/artwork-fit.spec.mjs'], use: { ...devices['Pixel 7'] } },
    { name: 'profiling-chromium', testMatch: '**/profiling/**/*.spec.mjs', use: { ...devices['Desktop Chrome'] } },
  ],
});
