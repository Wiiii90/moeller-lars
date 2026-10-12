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
        const type = await page.evaluate(() => {
            const family = (selector) => getComputedStyle(document.querySelector(selector)).fontFamily;
            return {
                body: family('body.public-site'),
                artist: family('.site-header h1'),
                navigation: family('.site-navigation__primary a'),
                artwork: family('.artwork-label'),
                action: family('.artwork-context-button'),
                headerPosition: getComputedStyle(document.querySelector('.site-title')).position,
            };
        });
        for (const family of [type.artist, type.navigation, type.artwork, type.action]) {
            expect(family).toBe(type.body);
        }
        expect(type.headerPosition).toBe('absolute');
        const surfaces = await page.evaluate(() => ({
            menu: getComputedStyle(document.querySelector('.site-navigation')).backgroundColor,
            content: getComputedStyle(document.querySelector('#content')).backgroundColor,
        }));
        expect(surfaces.menu).toBe('rgb(251, 250, 247)');
        expect(surfaces.content).toBe('rgb(251, 250, 247)');
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
        const separateRows = geometry.action.top >= geometry.label.bottom - 1;
        if (!separateRows) {
            expect(Math.abs(geometry.label.bottom - geometry.action.bottom), 'footer baseline at '+width).toBeLessThan(1);
            expect(geometry.label.right, 'label / action spacing at '+width).toBeLessThan(geometry.action.left);
        } else {
            expect(geometry.action.top, 'wrapped actions after plaque at '+width).toBeGreaterThanOrEqual(geometry.label.bottom - 1);
        }
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
            expect(state.navItemFont).toBe('12.5px');
            expect(Math.abs(state.headerHeight - 72.7)).toBeLessThan(2);
        }
        previous = state;
    }
});


test('empty public pages preserve height, header and vertical spacing across widths', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
        '<body class="public-site">',
        '<header class="site-header">',
        '<h1><a href="#" class="site-title"><span class="site-title__first">Lars</span><span class="site-title__last">Möller</span></a></h1>',
        '<div class="site-navigation"><div class="site-navigation__rail"><nav><a href="#">Home</a></nav></div></div>',
        '</header><main id="content" class="site-content"><div class="custom-page"></div></main>',
        '</body></html>',
    ].join(''));
    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    await page.addStyleTag({ path: 'resources/css/custom-pages.css' });

    // Both authored General values must survive responsive recomposition:
    // height is stable; horizontal geometry remains derived from these inputs.
    for (const settings of [
        { width: 800, padding: 75 },
        { width: 640, padding: 24 },
    ]) {
        await page.evaluate(({ width, padding }) => {
            document.documentElement.style.setProperty('--public-shell-max', width + 'px');
            document.documentElement.style.setProperty('--public-art-max', (width - padding * 2) + 'px');
            document.documentElement.style.setProperty('--public-content-padding', padding + 'px');
        }, settings);

        let previous = null;
        for (const width of [1600, 1100, 1024, 810, 770, 737, 736, 735, 545, 544, 543, 420, 360, 320]) {
            await page.setViewportSize({ width, height: 800 });
            const layout = await page.evaluate(() => {
                const main = document.querySelector('#content');
                const title = document.querySelector('.site-header h1');
                const computed = getComputedStyle(main);
                return {
                    height: main.getBoundingClientRect().height,
                    titleHeight: title.getBoundingClientRect().height,
                    titleFont: getComputedStyle(title).fontSize,
                    paddingTop: parseFloat(computed.paddingTop),
                    paddingBottom: parseFloat(computed.paddingBottom),
                    marginTop: parseFloat(computed.marginTop),
                    marginBottom: parseFloat(computed.marginBottom),
                    configuredPadding: getComputedStyle(document.documentElement).getPropertyValue('--public-content-padding').trim(),
                    menuLeft: document.querySelector('.site-navigation').getBoundingClientRect().left,
                    pageLeft: main.getBoundingClientRect().left,
                };
            });

            expect(layout.configuredPadding).toBe(settings.padding + 'px');
            expect(layout.height, 'empty page height at ' + width).toBeGreaterThanOrEqual(240);
            expect(Math.abs(layout.menuLeft - layout.pageLeft), 'shell edges at ' + width).toBeLessThan(1);
            expect(layout.marginTop).toBeCloseTo(layout.marginBottom, 2);
            expect(layout.paddingTop).toBeCloseTo(layout.paddingBottom, 2);
            if (previous !== null) {
                for (const key of ['height', 'titleHeight', 'paddingTop', 'paddingBottom', 'marginTop', 'marginBottom', 'titleFont']) {
                    if (key === 'titleFont') {
                        expect(layout[key], key + ' at ' + width).toBe(previous[key]);
                    } else {
                        expect(layout[key], key + ' at ' + width).toBeCloseTo(previous[key], 1);
                    }
                }
            }
            previous = layout;
        }
    }
});

test('gallery item spacing does not collapse at the Minimal transition', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
        '<body class="public-site"><main id="content" class="site-content">',
        '<div class="artwork-list">',
        '<article class="artwork-card"><div class="artwork-card__image" style="height:120px"></div></article>',
        '<article class="artwork-card"><div class="artwork-card__image" style="height:120px"></div></article>',
        '</div></main></body></html>',
    ].join(''));
    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });
    let previous = null;
    for (const width of [1200, 800, 600, 545, 544, 543, 420, 320]) {
        await page.setViewportSize({ width, height: 800 });
        const gap = await page.locator('.artwork-card').first().evaluate((el) =>
            parseFloat(getComputedStyle(el).marginBottom));
        expect(gap).toBeGreaterThan(0);
        if (previous !== null) expect(gap, 'gallery gap at ' + width).toBeCloseTo(previous, 2);
        previous = gap;
    }
});


test('only empty non-Home section pages without headings stretch to the viewport', async ({ page }) => {
    const variants = [
        { name: 'gallery empty', container: 'artwork-list', stretch: true },
        { name: 'exhibitions empty', container: 'exhibitions-page', stretch: true },
        { name: 'blog empty', container: 'blog-page', stretch: true },
        { name: 'custom empty', container: 'custom-page', stretch: true },
        { name: 'gallery with heading', container: 'artwork-list', heading: true },
        { name: 'exhibitions with heading', container: 'exhibitions-page', heading: true },
        { name: 'blog with heading', container: 'blog-page', heading: true },
        { name: 'custom with heading', container: 'custom-page', heading: true },
        { name: 'gallery populated', container: 'artwork-list', child: '<article class="artwork-card">Artwork</article>' },
        { name: 'exhibitions populated', container: 'exhibitions-page', child: '<article class="exhibition-entry">Exhibition</article>' },
        { name: 'blog populated', container: 'blog-page', child: '<article class="blog-entry">Post</article>' },
        { name: 'custom populated', container: 'custom-page', child: '<section class="custom-page__component">Content</section>' },
        { name: 'home empty components', container: 'custom-page home-components' },
        { name: 'home artwork', container: 'artwork-card', child: '<div>Artwork</div>' },
        { name: 'home without components', container: null },
    ];

    for (const variant of variants) {
        await page.setContent([
            '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
            '<body class="public-site">',
            '<header class="site-header">',
            '<h1><a href="#" class="site-title"><span class="site-title__first">Lars</span><span class="site-title__last">Möller</span></a></h1>',
            '<div class="site-navigation"><div class="site-navigation__rail"><nav>',
            '<a href="#">Home</a></nav></div></div>',
            '</header>',
            '<main id="content" class="site-content">',
            variant.heading ? '<header class="public-page-heading"><h2>Optional heading</h2></header>' : '',
            variant.container ? '<div class="' + variant.container + '">' + (variant.child ?? '') + '</div>' : '',
            '</main></body></html>',
        ].join(''));
        await page.addStyleTag({ path: 'resources/css/app.css' });
        await page.addStyleTag({ path: 'resources/css/public-presentation.css' });

        for (const width of [1440, 768, 540, 375, 320]) {
            await page.setViewportSize({ width, height: 960 });
            const geometry = await page.evaluate(() => {
                const header = document.querySelector('.site-header').getBoundingClientRect();
                const main = document.querySelector('#content');
                const content = main.getBoundingClientRect();
                const pageGap = parseFloat(getComputedStyle(main).marginBottom);
                return {
                    headerBottom: header.bottom,
                    contentTop: content.top,
                    contentBottom: content.bottom,
                    contentHeight: content.height,
                    grow: getComputedStyle(main).flexGrow,
                    documentHeight: document.documentElement.scrollHeight,
                    viewportHeight: window.innerHeight,
                    scrollY: window.scrollY,
                    pageGap,
                };
            });
            expect(geometry.contentHeight, variant.name + ' minimum at ' + width).toBeGreaterThanOrEqual(240);
            expect(Math.abs(geometry.contentTop - geometry.headerBottom - geometry.pageGap),
                variant.name + ' top gap at ' + width).toBeLessThan(1);
            if (variant.stretch) {
                expect(geometry.grow, variant.name + ' grows at ' + width).toBe('1');
                expect(geometry.documentHeight, variant.name + ' root scroll at ' + width)
                    .toBeLessThanOrEqual(geometry.viewportHeight + 1);
                expect(Math.abs(geometry.contentBottom + geometry.pageGap - geometry.viewportHeight),
                    variant.name + ' bottom gap at ' + width).toBeLessThan(1);
            } else {
                expect(geometry.grow, variant.name + ' does not grow at ' + width).toBe('0');
                expect(geometry.contentHeight, variant.name + ' retains old short page size at ' + width)
                    .toBeCloseTo(240, 1);
                expect(geometry.contentBottom + geometry.pageGap, variant.name + ' leaves normal background at ' + width)
                    .toBeLessThan(geometry.viewportHeight - 100);
            }
            expect(geometry.scrollY).toBe(0);
        }
    }
});

test('typographic navigation keeps text-only underlines and stable rail geometry', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
        '<body class="public-site"><header class="site-header">',
        '<div class="site-navigation"><div class="site-navigation__rail"><nav aria-label="Main navigation">',
        '<div class="site-navigation__item is-active"><div class="site-navigation__primary"><a href="#" aria-current="page"><span class="site-navigation__label">Paintings</span></a></div></div>',
        '<div class="site-navigation__item"><div class="site-navigation__primary"><a href="#"><span class="site-navigation__label">Drawings</span></a></div></div>',
        '<div class="site-navigation__item"><div class="site-navigation__primary"><a href="#"><span class="site-navigation__label">Prints</span></a></div></div>',
        '</nav></div></div></header><main id="content" class="site-content"></main></body></html>',
    ].join(''));
    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });

    for (const width of [1200, 480, 320]) {
        await page.setViewportSize({ width, height: 800 });
        const before = await page.evaluate(() => {
            const menu = document.querySelector('.site-navigation').getBoundingClientRect();
            const content = document.querySelector('#content').getBoundingClientRect();
            const active = document.querySelector('.site-navigation__item.is-active .site-navigation__label');
            const inactive = document.querySelector('.site-navigation__item:not(.is-active) .site-navigation__label');
            const item = document.querySelector('.site-navigation__item:not(.is-active)');
            return {
                menuLeft: menu.left, menuRight: menu.right,
                contentLeft: content.left, contentRight: content.right,
                itemWidth: item.getBoundingClientRect().width,
                border: getComputedStyle(item).borderLeftWidth,
                activeUnderline: getComputedStyle(active).textDecorationColor,
                inactiveUnderline: getComputedStyle(inactive).textDecorationColor,
                underlineLine: getComputedStyle(active).textDecorationLine,
                scrollWidth: document.scrollingElement.scrollWidth,
                clientWidth: document.scrollingElement.clientWidth,
            };
        });

        expect(Math.abs(before.menuLeft - before.contentLeft)).toBeLessThan(1);
        expect(Math.abs(before.menuRight - before.contentRight)).toBeLessThan(1);
        expect(before.border).toBe('0px');
        expect(before.underlineLine).toBe('underline');
        expect(before.inactiveUnderline).toBe('rgba(0, 0, 0, 0)');
        expect(before.activeUnderline).not.toBe(before.inactiveUnderline);
        expect(before.scrollWidth).toBeLessThanOrEqual(before.clientWidth + 1);

        await page.locator('.site-navigation__item:not(.is-active) a').first().hover();
        await expect.poll(() => page.locator('.site-navigation__item:not(.is-active) .site-navigation__label').first()
            .evaluate((el) => getComputedStyle(el).textDecorationColor)).toBe(before.activeUnderline);
        const hoveredWidth = await page.locator('.site-navigation__item:not(.is-active)').first()
            .evaluate((el) => el.getBoundingClientRect().width);
        expect(Math.abs(hoveredWidth - before.itemWidth)).toBeLessThan(.5);
    }
});
