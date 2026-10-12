import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

// Isolated real-browser regression: no Laravel server, DB or application session.
const style = readFileSync(new URL('../../resources/css/public-presentation.css', import.meta.url), 'utf8');
const navigationScript = readFileSync(new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8');

function fixture(path) {
    const withinAbout = path === '/about' || path === '/biography';
    const parentLink = `<a href="/about" data-navigation-parent-link aria-controls="child-about" aria-expanded="${withinAbout}"><span class="site-navigation__label">ABOUT</span></a>`;
    const link = (label, url, selected = false) =>
        `<div class="site-navigation__item ${selected ? 'is-active' : ''}" data-navigation-item data-navigation-key="${url}"><div class="site-navigation__primary"><a href="${url}" ${selected ? 'aria-current="page"' : ''}><span class="site-navigation__label">${label}</span></a></div></div>`;
    return `<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${style}</style></head>
        <body class="public-site">
        <header class="site-header">
          <div class="site-navigation" data-site-navigation>
            <div class="site-navigation__rail" data-navigation-main ${withinAbout ? 'hidden' : ''}>
              <button type="button" class="site-navigation__control" data-direction="previous" aria-label="Previous page" hidden>‹</button>
              <nav aria-label="Main navigation" data-navigation-window-scroll>
                ${link('HOME', '/')}
                ${link('PRINTS', '/prints')}
                ${link('PAINTING', '/painting')}
                <div class="site-navigation__item ${withinAbout ? 'is-active' : ''}" data-navigation-item data-navigation-key="about"><div class="site-navigation__primary">${parentLink}</div></div>
                ${link('DRAWINGS', '/drawings')}
                ${link('EXHIBITIONS', '/exhibitions')}
              </nav>
              <button type="button" class="site-navigation__control" data-direction="next" aria-label="Next page" hidden>›</button>
            </div>
            <div class="site-navigation__submenu" id="child-about" data-navigation-submenu ${withinAbout ? '' : 'hidden'}>
              <button type="button" class="site-navigation__control" data-direction="previous" aria-label="Previous subpage" hidden>‹</button>
              <div class="site-navigation__content" data-navigation-content>
                <button class="site-navigation__back" type="button" data-navigation-back aria-label="Return to main navigation">MENU</button>
                <span class="site-navigation__breadcrumb-separator" aria-hidden="true">/</span>
                <nav aria-label="About subnavigation" data-navigation-window-scroll>
                  ${link('ABOUT', '/about', path === '/about')}
                  ${link('BIOGRAPHY', '/biography', path === '/biography')}
                  ${link('CHRONOLOGY', '/chronology')}
                  ${link('EXHIBITIONS', '/about-exhibitions')}
                  ${link('CONTACT', '/contact')}
                </nav>
              </div>
              <button type="button" class="site-navigation__control" data-direction="next" aria-label="Next subpage" hidden>›</button>
            </div>
          </div>
        </header><main id="content"></main>
        <script type="module">import { initializePublicNavigation } from '/public-navigation.js'; initializePublicNavigation();</script>
        </body></html>`;
}

test.beforeEach(async ({ page }) => {
    await page.route('https://navigation.test/**', async (route) => {
        const path = new URL(route.request().url()).pathname;
        await route.fulfill(path === '/public-navigation.js'
            ? { status: 200, contentType: 'text/javascript', body: navigationScript }
            : { status: 200, contentType: 'text/html', body: fixture(path) });
    });
});

test('parent switches levels without a second row, with a distinct text-only return', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 700 });
    await page.goto('https://navigation.test/');
    const header = page.locator('.site-navigation');
    const originalHeight = await header.evaluate((element) => element.getBoundingClientRect().height);

    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
    await page.locator('[data-navigation-parent-link]').click();

    await expect(page.locator('[data-navigation-main]')).toBeHidden();
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('[data-navigation-back]')).toHaveText('MENU');
    expect(await header.evaluate((element) => element.getBoundingClientRect().height)).toBeCloseTo(originalHeight, 1);
    expect(await page.locator('[data-navigation-main]:visible, [data-navigation-submenu]:visible').count()).toBe(1);

    // A parent with its own URL remains reachable from the new level.
    await page.locator('#child-about a[href="/about"]').click();
    await expect(page).toHaveURL('https://navigation.test/about');
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('#child-about a[href="/about"]')).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: 'Return to main navigation' }).click();
    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
});

test('direct child routes remain in the one-row child level; Escape returns to main', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 700 });
    await page.goto('https://navigation.test/biography');
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('[data-navigation-main]')).toBeHidden();
    await expect(page.locator('#child-about a[href="/biography"]')).toHaveAttribute('aria-current', 'page');

    const geometry = await page.evaluate(() => {
        const menu = document.querySelector('.site-navigation').getBoundingClientRect();
        const content = document.querySelector('#content').getBoundingClientRect();
        return { leftGap: Math.abs(menu.left - content.left), rightGap: Math.abs(menu.right - content.right) };
    });
    expect(geometry.leftGap).toBeLessThan(1);
    expect(geometry.rightGap).toBeLessThan(1);

    await page.getByRole('button', { name: 'Return to main navigation' }).focus();
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
});
