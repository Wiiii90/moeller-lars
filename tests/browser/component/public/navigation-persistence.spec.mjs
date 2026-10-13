import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Production navigation module against a self-contained DOM fixture. No app server or DB.
test('one-row child level persists through outside interactions and exits only explicitly', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">',
        '<style>[hidden] { display: none !important; }',
        'nav[data-navigation-window-scroll] { display: flex; width: 240px; }',
        '[data-navigation-item] { flex: 0 0 90px; }',
        '[data-navigation-content] { display: flex; }',
        '</style></head><body>',
        '<div data-site-navigation>',
        '  <div data-navigation-main hidden>',
        '    <button type="button" data-direction="previous" hidden>Previous</button>',
        '    <nav data-navigation-window-scroll>',
        '      <div data-navigation-item data-navigation-key="a" class="is-active">',
        '        <div class="site-navigation__primary">',
        '          <button type="button" data-navigation-parent-link aria-controls="sub-a" aria-expanded="true">A</button>',
        '        </div>',
        '      </div>',
        '      <div data-navigation-item data-navigation-key="b">',
        '        <div class="site-navigation__primary">',
        '          <button type="button" data-navigation-parent-link aria-controls="sub-b" aria-expanded="false">B</button>',
        '        </div>',
        '      </div>',
        '    </nav>',
        '    <button type="button" data-direction="next" hidden>Next</button>',
        '  </div>',
        '  <div id="sub-a" data-navigation-submenu>',
        '    <button type="button" data-direction="previous" hidden>Previous child</button>',
        '    <div data-navigation-content>',
        '      <button type="button" data-navigation-back aria-label="Return to main navigation"><svg aria-hidden="true"><path d="M9 14 4 9l5-5M20 20v-7a4 4 0 0 0-4-4H4"/></svg><span class="site-navigation__back-label">Parent</span></button>',
        '      <span class="site-navigation__breadcrumb-separator" aria-hidden="true"></span>',
        '      <nav data-navigation-window-scroll>',
        '        <div data-navigation-item data-navigation-key="a1" class="is-active">',
        '          <div class="site-navigation__primary"><a href="#child-a" aria-current="page">A1</a></div>',
        '        </div>',
        '      </nav>',
        '    </div>',
        '    <button type="button" data-direction="next" hidden>Next child</button>',
        '  </div>',
        '  <div id="sub-b" data-navigation-submenu hidden>',
        '    <button type="button" data-direction="previous" hidden>Previous child</button>',
        '    <div data-navigation-content>',
        '      <button type="button" data-navigation-back aria-label="Return to main navigation"><svg aria-hidden="true"><path d="M9 14 4 9l5-5M20 20v-7a4 4 0 0 0-4-4H4"/></svg><span class="site-navigation__back-label">Parent</span></button>',
        '      <span class="site-navigation__breadcrumb-separator" aria-hidden="true"></span>',
        '      <nav data-navigation-window-scroll>',
        '        <div data-navigation-item data-navigation-key="b1">',
        '          <div class="site-navigation__primary"><a href="#child-b">B1</a></div>',
        '        </div>',
        '      </nav>',
        '    </div>',
        '    <button type="button" data-direction="next" hidden>Next child</button>',
        '  </div>',
        '</div>',
        '<button type="button" id="outside">Outside content</button>',
        '</body></html>',
    ].join(''));

    const moduleSource = readFileSync(
        new URL('../../../../resources/js/public-navigation.js', import.meta.url), 'utf8',
    );
    await page.addScriptTag({
        content: moduleSource.replace(/^export /gm, '')
            + '\nwindow.initializePublicNavigation = initializePublicNavigation;',
    });
    await page.evaluate(() => window.initializePublicNavigation());

    const main = page.locator('[data-navigation-main]');
    const first = page.locator('#sub-a');
    const second = page.locator('#sub-b');
    await expect(main).toBeHidden();
    await expect(first).toBeVisible();
    await expect(second).toBeHidden();

    // Outside focus and clicks must not mutate the selected child branch.
    await first.locator('a').focus();
    await page.locator('#outside').focus();
    await page.locator('#outside').click();
    await expect(first).toBeVisible();

    // A named, non-arrow return exposes the original top-level window.
    await first.locator('[data-navigation-back]').click();
    await expect(main).toBeVisible();
    await expect(first).toBeHidden();

    // A different navigation-only parent switches into its own single-row level.
    await page.locator('[data-navigation-parent-link][aria-controls="sub-b"]').click();
    await expect(main).toBeHidden();
    await expect(second).toBeVisible();
    await expect(first).toBeHidden();
    await expect(page.locator('[data-navigation-main]:visible, [data-navigation-submenu]:visible')).toHaveCount(1);

    // Escape is the other deliberate exit, restoring focus to the parent.
    await second.locator('[data-navigation-back]').focus();
    await page.keyboard.press('Escape');
    await expect(main).toBeVisible();
    await expect(second).toBeHidden();
    await expect(page.locator('[data-navigation-parent-link][aria-controls="sub-b"]')).toBeFocused();
});
