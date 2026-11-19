import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

// Isolated real-browser regression: no Laravel server, DB or application session.
const style = readFileSync(new URL('../../../../resources/css/public-presentation.css', import.meta.url), 'utf8');
const navigationScript = readFileSync(new URL('../../../../resources/js/public-navigation.js', import.meta.url), 'utf8');

function fixture(path) {
    const withinAbout = path === '/about' || path === '/biography';
    const alignedSide = path === '/aligned-left' ? 'left' : path === '/aligned-right' ? 'right' : null;
    const parentLink = `<a href="/about" data-navigation-parent-link aria-controls="child-about" aria-expanded="${withinAbout}"><span class="site-navigation__label">ABOUT</span></a>`;
    const link = (label, url, selected = false) =>
        `<div class="site-navigation__item ${selected ? 'is-active' : ''}" data-navigation-item data-navigation-key="${url}"><div class="site-navigation__primary"><a href="${url}" ${selected ? 'aria-current="page"' : ''}><span class="site-navigation__label">${label}</span></a></div></div>`;
    const mainItems = alignedSide
        ? [link('PAINTINGS', '/paintings'), link('DRAWINGS', '/drawings'),
            `<div class="site-navigation__item" data-navigation-item data-navigation-key="about"><div class="site-navigation__primary">${parentLink}</div></div>`].join('')
        : [link('HOME', '/'), link('PRINTS', '/prints'), link('PAINTING', '/painting'),
            `<div class="site-navigation__item ${withinAbout ? 'is-active' : ''}" data-navigation-item data-navigation-key="about"><div class="site-navigation__primary">${parentLink}</div></div>`,
            link('DRAWINGS', '/drawings'), link('EXHIBITIONS', '/exhibitions')].join('');
    const childItems = alignedSide
        ? [link('ABOUT', '/about'), link('GALLERY ONE', '/gallery-one'), link('GALLERY TWO', '/gallery-two')].join('')
        : [link('ABOUT', '/about', path === '/about'), link('BIOGRAPHY', '/biography', path === '/biography'),
            link('CHRONOLOGY', '/chronology'), link('EXHIBITIONS', '/about-exhibitions'), link('CONTACT', '/contact')].join('');
    return `<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${style}${alignedSide ? ':root { --public-shell-max: 1000px; }' : ''}</style></head>
        <body class="public-site">
        <header class="site-header ${alignedSide ? `site-header--name-inside site-header--title-align-${alignedSide}` : ''}">
          ${alignedSide ? '<div class="site-navigation-surface">' : ''}
          <div class="site-navigation" data-site-navigation>
            ${alignedSide ? '<h1 class="site-name-heading"><a href="/" class="site-title">Lars Möller</a></h1>' : ''}
            <div class="site-navigation__rail" data-navigation-main ${withinAbout ? 'hidden' : ''}>
              <button type="button" class="site-navigation__control" data-direction="previous" aria-label="Previous page" hidden>‹</button>
              <nav aria-label="Main navigation" data-navigation-window-scroll>
                ${mainItems}
              </nav>
              <button type="button" class="site-navigation__control" data-direction="next" aria-label="Next page" hidden>›</button>
            </div>
            <div class="site-navigation__submenu" id="child-about" data-navigation-submenu ${withinAbout ? '' : 'hidden'}>
              <button type="button" class="site-navigation__control" data-direction="previous" aria-label="Previous subpage" hidden>‹</button>
              <div class="site-navigation__content" data-navigation-content>
                <button class="site-navigation__back" type="button" data-navigation-back aria-label="Return to main navigation from About" title="Return to main navigation">
                    <svg class="public-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 14 4 9l5-5M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
                    <span class="site-navigation__back-label">ABOUT</span>
                </button>
                <span class="site-navigation__breadcrumb-separator" aria-hidden="true"></span>
                <nav aria-label="About subnavigation" data-navigation-window-scroll>
                  ${childItems}
                </nav>
              </div>
              <button type="button" class="site-navigation__control" data-direction="next" aria-label="Next subpage" hidden>›</button>
            </div>
          </div>
          ${alignedSide ? '</div>' : ''}
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

test('parent switches to contextual child navigation and back', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 700 });
    await page.goto('https://navigation.test/');
    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
    await page.locator('[data-navigation-parent-link]').click();

    await expect(page.locator('[data-navigation-main]')).toBeHidden();
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('[data-navigation-back] .site-navigation__back-label')).toHaveText('ABOUT');
    await expect(page.locator('[data-navigation-back] .public-icon')).toBeVisible();
    await expect(page.locator('[data-navigation-back]')).toHaveAttribute('aria-label', 'Return to main navigation from About');
    expect(await page.locator('[data-navigation-main]:visible, [data-navigation-submenu]:visible').count()).toBe(1);

    // A parent with its own URL remains reachable from the new level.
    await page.locator('#child-about a[href="/about"]').click();
    await expect(page).toHaveURL('https://navigation.test/about');
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('#child-about a[href="/about"]')).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: 'Return to main navigation from About' }).click();
    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
});

test('direct child route remains selected and Escape returns to main', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 700 });
    await page.goto('https://navigation.test/biography');
    await expect(page.locator('#child-about')).toBeVisible();
    await expect(page.locator('[data-navigation-main]')).toBeHidden();
    await expect(page.locator('#child-about a[href="/biography"]')).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: 'Return to main navigation from About' }).focus();
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-navigation-main]')).toBeVisible();
    await expect(page.locator('#child-about')).toBeHidden();
});

test('Inside title side anchors ordered navigation and mirrors the nested return control', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 800 });

    for (const side of ['left', 'right']) {
        await page.goto(`https://navigation.test/aligned-${side}`);
        const main = page.locator('[data-navigation-main]');
        await expect(main).toBeVisible();
        await expect(main).not.toHaveClass(/is-overflowing/);
        const mainLayout = await page.evaluate(() => {
            const menu = document.querySelector('.site-navigation');
            const nav = document.querySelector('[data-navigation-main] > nav');
            const box = menu.getBoundingClientRect();
            const inset = getComputedStyle(menu);
            const pages = [...nav.querySelectorAll('[data-navigation-item]:not([hidden])')];
            return {
                readingLeft: box.left + Number.parseFloat(inset.paddingLeft),
                readingRight: box.right - Number.parseFloat(inset.paddingRight),
                firstLeft: pages[0].getBoundingClientRect().left,
                lastRight: pages.at(-1).getBoundingClientRect().right,
                order: pages.map(item => item.textContent.trim()),
            };
        });
        expect(mainLayout.order).toEqual(['PAINTINGS', 'DRAWINGS', 'ABOUT']);
        if (side === 'left') expect(Math.abs(mainLayout.lastRight - mainLayout.readingRight)).toBeLessThan(2);
        else expect(Math.abs(mainLayout.firstLeft - mainLayout.readingLeft)).toBeLessThan(2);

        await page.locator('[data-navigation-parent-link]').click();
        const submenu = page.locator('#child-about');
        await expect(submenu).toBeVisible();
        await expect(submenu).not.toHaveClass(/is-overflowing/);
        const childLayout = await page.evaluate(() => {
            const submenu = document.querySelector('#child-about');
            const nav = submenu.querySelector('nav');
            const back = submenu.querySelector('[data-navigation-back]').getBoundingClientRect();
            const divider = submenu.querySelector('.site-navigation__breadcrumb-separator').getBoundingClientRect();
            const children = [...nav.querySelectorAll('[data-navigation-item]:not([hidden])')];
            return {
                order: children.map(item => item.textContent.trim()),
                firstLeft: children[0].getBoundingClientRect().left,
                lastRight: children.at(-1).getBoundingClientRect().right,
                backLeft: back.left,
                backRight: back.right,
                dividerLeft: divider.left,
                dividerRight: divider.right,
                direction: getComputedStyle(submenu.querySelector('[data-navigation-content]')).flexDirection,
            };
        });
        expect(childLayout.order).toEqual(['ABOUT', 'GALLERY ONE', 'GALLERY TWO']);
        if (side === 'left') {
            expect(childLayout.direction).toBe('row-reverse');
            expect(Math.abs(childLayout.backRight - mainLayout.readingRight)).toBeLessThan(2);
            expect(childLayout.lastRight).toBeLessThan(childLayout.dividerLeft);
            expect(childLayout.dividerRight).toBeLessThan(childLayout.backLeft);
        } else {
            expect(childLayout.direction).toBe('row');
            expect(Math.abs(childLayout.backLeft - mainLayout.readingLeft)).toBeLessThan(2);
            expect(childLayout.backRight).toBeLessThan(childLayout.dividerLeft);
            expect(childLayout.dividerRight).toBeLessThan(childLayout.firstLeft);
        }
        await submenu.locator('[data-navigation-back]').click();
        await expect(main).toBeVisible();
    }
});
