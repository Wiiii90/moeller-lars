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
    // Reuse canonical browser scenarios on WebKit without a parallel test suite.
    // WebKit approximates Safari; native macOS Safari still needs a real-device gate.
    { name: 'e2e-webkit-desktop', testMatch: [
      '**/e2e/admin/gallery-ordering.spec.mjs',
      '**/e2e/public/artwork-fit.spec.mjs',
      '**/e2e/public/public-media.spec.mjs',
      '**/e2e/public/public-spa-navigation.spec.mjs',
      '**/e2e/public/public-menu-touch.spec.mjs',
      '**/e2e/admin/rich-text-editor.spec.mjs',
    ], use: { ...devices['Desktop Safari'] } },
    { name: 'e2e-webkit-mobile', testMatch: [
      '**/e2e/admin/gallery-ordering.spec.mjs',
      '**/e2e/public/artwork-fit.spec.mjs',
      '**/e2e/public/public-media.spec.mjs',
      '**/e2e/public/public-menu-touch.spec.mjs',
    ], use: { ...devices['iPhone 13'] } },
    { name: 'e2e-chromium-mobile', testMatch: [
      '**/e2e/admin/gallery-ordering.spec.mjs',
      '**/e2e/public/artwork-fit.spec.mjs',
    ], use: { ...devices['Pixel 7'] } },
    { name: 'profiling-chromium', testMatch: '**/profiling/**/*.spec.mjs', use: { ...devices['Desktop Chrome'] } },
  ],
});
