import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// The production navigation module runs against a self-contained DOM fixture.
// No application server, real content database, or additional navigation logic.
test('expanded child navigation persists until an explicit top-level selection', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">',
        '<style> [hidden] { display: none !important; }',
        'nav[data-navigation-window-scroll] { display: flex; width: 170px; }',
        '[data-navigation-item] { flex: 0 0 90px; }',
        '</style></head><body>',
        '<div data-site-navigation>',
        '  <div data-navigation-main>',
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
        '  <div data-navigation-submenu-region data-open="true">',
        '    <div data-navigation-submenu-region-inner>',
        '      <div id="sub-a" data-navigation-submenu>',
        '        <button type="button" data-direction="previous" hidden>Previous child</button>',
        '        <nav data-navigation-window-scroll>',
        '          <div data-navigation-item data-navigation-key="a1" class="is-active">',
        '            <div class="site-navigation__primary"><a href="#child-a" aria-current="page">A1</a></div>',
        '          </div>',
        '        </nav>',
        '        <button type="button" data-direction="next" hidden>Next child</button>',
        '      </div>',
        '      <div id="sub-b" data-navigation-submenu hidden>',
        '        <button type="button" data-direction="previous" hidden>Previous child</button>',
        '        <nav data-navigation-window-scroll>',
        '          <div data-navigation-item data-navigation-key="b1">',
        '            <div class="site-navigation__primary"><a href="#child-b">B1</a></div>',
        '          </div>',
        '        </nav>',
        '        <button type="button" data-direction="next" hidden>Next child</button>',
        '      </div>',
        '    </div>',
        '  </div>',
        '</div>',
        '<button type="button" id="outside">Outside content</button>',
        '</body></html>',
    ].join(''));

    const moduleSource = readFileSync(
        new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8',
    );
    await page.addScriptTag({
        content: moduleSource.replace(/^export /gm, '')
            + '\nwindow.initializePublicNavigation = initializePublicNavigation;',
    });
    await page.evaluate(() => window.initializePublicNavigation());

    const first = page.locator('#sub-a');
    const second = page.locator('#sub-b');
    await expect(first).toBeVisible();
    await expect(second).toBeHidden();

    // Leaving the child with the keyboard and clicking the content must not
    // dismiss it, even if the current child was selected on the server.
    await first.locator('a').focus();
    await page.locator('#outside').focus();
    await page.locator('#outside').click();
    await expect(first).toBeVisible();

    // Cycling the top-level WINDOW is not a top-level PAGE selection.
    await page.locator('[data-navigation-main] > button[data-direction="next"]').click();
    await expect(first).toBeVisible();

    // Deliberately selecting another top-level group replaces the child level.
    await page.locator('[data-navigation-parent-link][aria-controls="sub-b"]').click();
    await expect(first).toBeHidden();
    await expect(second).toBeVisible();

    // Selecting that same parent button again explicitly closes its children.
    await page.locator('[data-navigation-parent-link][aria-controls="sub-b"]').click();
    await expect(second).toBeHidden();
});
