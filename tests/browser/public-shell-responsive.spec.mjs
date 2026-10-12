import { test, expect } from '@playwright/test';

// CSS geometry regression with real public styles but no application server,
// database, or persistent browser profile.
test('document scrollbar, sticky header, symmetric page gap and artwork footer', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html lang="en"><head>',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        '</head><body class="public-site">',
        '<header class="site-header">',
        '<h1><a class="site-title" href="#"><span class="site-title__first">Lars</span><span class="site-title__last">Möller</span></a></h1>',
        '<div class="site-navigation"><div class="site-navigation__rail"><nav aria-label="Main navigation">',
        '<div class="site-navigation__item"><div class="site-navigation__primary"><a href="#">Home</a></div></div>',
        '<div class="site-navigation__item"><div class="site-navigation__primary"><a href="#">Painting</a></div></div>',
        '<div class="site-navigation__item"><div class="site-navigation__primary"><a href="#">Drawings</a></div></div>',
        '</nav></div></div></header>',
        '<main id="content" class="site-content">',
        '<article class="artwork-card"><a class="artwork-card__link" href="#">',
        '<div class="artwork-card__image" style="aspect-ratio: 1"></div></a>',
        '<div class="artwork-card__footer"><a href="#" class="artwork-label-trigger"><div class="artwork-label">',
        '<div class="artwork-label__heading"><span class="artwork-label__title">A.G.</span><span class="artwork-label__year">2025</span></div>',
        '<div class="artwork-label__facts"><span>drawing</span><span>25 × 25</span></div>',
        '</div></a><nav class="artwork-card__actions" aria-label="Artwork navigation">',
        '<a class="artwork-context-button" href="#">Drawings →</a></nav></div>',
        '</article><div aria-hidden="true" style="height:1400px"></div></main></body></html>',
    ].join(''));
    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });

    // Same configurable dimensions that the Public Blade layout would render.
    await page.evaluate(() => {
        document.documentElement.style.setProperty('--public-shell-max', '720px');
        document.documentElement.style.setProperty('--public-art-max', '520px');
        document.documentElement.style.setProperty('--public-content-padding', '100px');
    });

    for (const width of [1600, 1009, 706, 506, 420, 320]) {
        await page.setViewportSize({ width, height: 800 });
        await page.evaluate(() => window.scrollTo(0, 0));
        const geometry = await page.evaluate(() => {
            const rect = (selector) => {
                const r = document.querySelector(selector).getBoundingClientRect();
                return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width };
            };
            const scrollingElement = document.scrollingElement;
            return {
                menu: rect('.site-navigation'),
                content: rect('#content'),
                header: rect('.site-header'),
                label: rect('.artwork-label'),
                action: rect('.artwork-card__actions'),
                image: rect('.artwork-card__image'),
                scrollWidth: scrollingElement.scrollWidth,
                scrollClientWidth: scrollingElement.clientWidth,
                scrollOwner: scrollingElement === document.documentElement,
                scrollable: scrollingElement.scrollHeight > window.innerHeight,
                headerPosition: getComputedStyle(document.querySelector('.site-header')).position,
                rootGutter: getComputedStyle(document.documentElement).scrollbarGutter,
                bodyLeft: document.body.getBoundingClientRect().left,
            };
        });
        expect(Math.abs(geometry.menu.left - geometry.content.left), 'left edges at '+width).toBeLessThan(1);
        expect(Math.abs(geometry.menu.right - geometry.content.right), 'right edges at '+width).toBeLessThan(1);
        expect(geometry.content.top - geometry.header.bottom, 'header gap at '+width).toBeGreaterThan(8);
        expect(Math.abs(geometry.label.top - geometry.action.top), 'footer row at '+width).toBeLessThan(1);
        expect(geometry.label.right, 'label / action spacing at '+width).toBeLessThan(geometry.action.left);
        expect(geometry.image.width).toBeGreaterThan(0);
        expect(geometry.scrollWidth, 'horizontal overflow at '+width).toBeLessThanOrEqual(geometry.scrollClientWidth + 1);
        expect(geometry.scrollOwner, 'root scroll owner at '+width).toBe(true);
        expect(geometry.scrollable, 'long page scrolls at '+width).toBe(true);
        expect(geometry.headerPosition, 'header sticky at '+width).toBe('sticky');
        expect(geometry.rootGutter, 'normal root scrollbar at '+width).toBe('auto');
        expect(Math.abs(geometry.bodyLeft), 'no reserved left gutter at '+width).toBeLessThan(1);

        await page.evaluate(() => window.scrollTo(0, 180));
        await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
        const stickyTop = await page.locator('.site-header').evaluate((el) => el.getBoundingClientRect().top);
        expect(Math.abs(stickyTop), 'sticky header stays at viewport top at '+width).toBeLessThan(1);

        // At the document's real end, the trailing gap must equal the gap
        // between header divider and content, including Minimal viewports.
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await expect.poll(() => page.evaluate(() => {
            const doc = document.documentElement;
            return Math.abs(window.scrollY - (doc.scrollHeight - window.innerHeight));
        })).toBeLessThan(1);
        const bottomGap = await page.locator('#content').evaluate((el) =>
            window.innerHeight - el.getBoundingClientRect().bottom);
        const topGap = geometry.content.top - geometry.header.bottom;
        expect(Math.abs(bottomGap - topGap), 'equal outer content gaps at '+width).toBeLessThan(1);
    }
});


test('narrowing the viewport never creates extra room for the same menu items', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
        '<body class="public-site"><header class="site-header">',
        '<h1><a class="site-title" href="#"><span class="site-title__first">Lars</span><span class="site-title__last">Möller</span></a></h1>',
        '<div class="site-navigation" data-site-navigation><div class="site-navigation__rail" data-navigation-main>',
        '<button type="button" data-direction="previous" hidden>Previous</button>',
        '<nav aria-label="Main navigation" data-navigation-window-scroll>',
        ...['Home', 'Painting', 'Drawings', 'Nav', 'Prints', 'Exhibitions', 'More', 'Longer page name']
            .map((label, index) => '<div class="site-navigation__item" data-navigation-item data-navigation-key="' + index + '">'
                + '<div class="site-navigation__primary"><a href="#">' + label + '</a></div></div>'),
        '</nav><button type="button" data-direction="next" hidden>Next</button>',
        '</div><div data-navigation-submenu-region><div data-navigation-submenu-region-inner></div></div></div>',
        '</header><main id="content" class="site-content">',
        '<div class="artwork-list"><div class="artwork-card__image" style="aspect-ratio:1"></div></div>',
        '</main></body></html>',
    ].join(''));
    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.evaluate(() => {
        document.documentElement.style.setProperty('--public-shell-max', '800px');
        document.documentElement.style.setProperty('--public-art-max', '520px');
        document.documentElement.style.setProperty('--public-content-padding', '75px');
    });
    const navigationSource = (await import('node:fs')).readFileSync(
        new URL('../../resources/js/public-navigation.js', import.meta.url), 'utf8',
    );
    await page.addScriptTag({
        content: navigationSource.replace(/^export /gm, '')
            + '\nwindow.initializePublicNavigation = initializePublicNavigation;',
    });
    await page.evaluate(() => window.initializePublicNavigation());

    let previous = null;
    for (const width of [810, 770, 745, 740, 737, 736, 735, 727, 720, 700, 660, 620, 540, 500, 420, 320]) {
        await page.setViewportSize({ width, height: 820 });
        // Wait for the real ResizeObserver to settle after every width change.
        await page.waitForFunction(() => {
            const nav = document.querySelector('[data-navigation-window-scroll]');
            const items = Array.from(nav.querySelectorAll('[data-navigation-item]'));
            return items.length > 0 && items.some((item) => !item.hidden);
        });
        await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(
            () => requestAnimationFrame(resolve),
        )));
        const state = await page.evaluate(() => {
            const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
            const items = Array.from(document.querySelectorAll(
                '[data-navigation-main] > nav > [data-navigation-item]',
            ));
            const nav = document.querySelector('[data-navigation-window-scroll]');
            const title = document.querySelector('.site-header h1');
            return {
                railWidth: rect('[data-navigation-window-scroll]').width,
                artWidth: rect('.artwork-list').width,
                navItemFont: getComputedStyle(items[0].querySelector('a')).fontSize,
                headerHeight: title.getBoundingClientRect().height,
                visibleCount: items.filter((item) => !item.hidden).length,
                hasArrows: !document.querySelector(
                    '[data-navigation-main] > button[data-direction="next"]',
                ).hidden,
                totalNaturalWidth: items.reduce((sum, item) => {
                    const box = item.getBoundingClientRect();
                    return sum + box.width;
                }, 0),
                inlinePadding: getComputedStyle(document.querySelector('#content')).paddingLeft,
                shellLeft: rect('.site-navigation').left,
                contentLeft: rect('#content').left,
                bodyScrollWidth: document.documentElement.scrollWidth,
                windowWidth: document.documentElement.clientWidth,
                navWidth: nav.clientWidth,
            };
        });
        expect(state.railWidth, 'positive navigation rail at '+width).toBeGreaterThan(0);
        expect(Math.abs(state.shellLeft - state.contentLeft), 'shared shell at '+width).toBeLessThan(1);
        expect(state.bodyScrollWidth, 'no horizontal page overflow at '+width).toBeLessThanOrEqual(state.windowWidth + 1);
        if (previous !== null) {
            expect(state.railWidth, 'rail cannot widen when viewport narrows to '+width)
                .toBeLessThanOrEqual(previous.railWidth + 1);
            expect(state.artWidth, 'artwork cannot widen when viewport narrows to '+width)
                .toBeLessThanOrEqual(previous.artWidth + 1);
            expect(state.visibleCount, 'visible pages cannot increase due to a narrower viewport at '+width)
                .toBeLessThanOrEqual(previous.visibleCount);
            if (previous.hasArrows) {
                expect(state.hasArrows, 'overflow controls cannot disappear as width narrows to '+width).toBe(true);
            }
        }
        if (width === 727) {
            expect(state.navItemFont).toBe('12px');
            expect(Math.abs(state.headerHeight - 72.7)).toBeLessThan(2);
        }
        previous = state;
    }
});
