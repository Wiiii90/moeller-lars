import { test, expect } from '@playwright/test';

// CSS/geometry regressions using the real shared public stylesheet; no Laravel data.
test('framed artwork labels preserve gallery semantics and stable exhibition rhythm', async ({ page }) => {
    await page.setContent([
        '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
        '<body class="public-site"><header class="site-header"><h1>',
        '<a href="#" class="site-title"><span class="site-title__first">Lars</span><span class="site-title__last">Möller</span></a>',
        '</h1></header><main id="content" class="site-content"><div class="artwork-list">',
        '<article class="artwork-card">',
        '<a href="#" class="artwork-card__link"><div class="artwork-card__image" style="height:300px"></div></a>',
        '<div class="artwork-card__footer"><a href="#" class="artwork-label-trigger">',
        '<span class="artwork-label"><span class="artwork-label__heading">',
        '<span class="artwork-label__title">Grüner Planet (in cooperation with Krys Ubo)</span><span class="artwork-label__year">2023</span>',
        '</span><span class="artwork-label__facts"><span>Oil on canvas</span>',
        '<span class="artwork-label__dimensions">50 × 40</span></span>',
        '<span class="artwork-label__note">An authored short description.</span></span></a>',
        '<nav class="artwork-card__actions" aria-label="Artwork navigation">',
        '<button class="artwork-context-button artwork-context-button--icon" type="button" aria-label="Previous artwork">‹</button>',
        '<a class="artwork-context-button artwork-context-button--category" href="#">Paintings</a>',
        '<button class="artwork-context-button artwork-context-button--icon" type="button" aria-label="Next artwork">›</button>',
        '</nav>',
        '</div></article>',
        '<article class="artwork-card"><div class="artwork-card__image" style="height:180px"></div>',
        '<div class="artwork-card__footer"><a href="#" class="artwork-label-trigger">',
        '<span class="artwork-label"><span class="artwork-label__heading">',
        '<span class="artwork-label__title">A longer, deliberately varied artwork title</span>',
        '<span class="artwork-label__year">2021</span></span>',
        '<span class="artwork-label__facts"><span>Mixed media</span><span>100 × 70 cm</span></span>',
        '</span></a></div></article>',
        '</div></main></body></html>',
    ].join(''));

    await page.addStyleTag({ path: 'resources/css/app.css' });
    await page.addStyleTag({ path: 'resources/css/public-presentation.css' });

    let previousGap = null;
    for (const width of [1200, 800, 546, 544, 420, 320]) {
        await page.setViewportSize({ width, height: 800 });
        const state = await page.evaluate(() => {
            const first = document.querySelector('.artwork-card');
            const second = first.nextElementSibling;
            const image = first.querySelector('.artwork-card__image').getBoundingClientRect();
            const footer = first.querySelector('.artwork-card__footer').getBoundingClientRect();
            const label = first.querySelector('.artwork-label');
            const context = first.querySelector('.artwork-card__actions').getBoundingClientRect();
            const title = first.querySelector('.artwork-label__title');
            const facts = first.querySelector('.artwork-label__facts');
            const siteTitle = document.querySelector('.site-title').getBoundingClientRect();
            const rootTitle = document.querySelector('.site-header h1').getBoundingClientRect();
            const card = first.getBoundingClientRect();
            const next = second.getBoundingClientRect();
            const labelStyle = getComputedStyle(label);
            const box = label.getBoundingClientRect();
            return {
                border: labelStyle.borderTopWidth,
                radius: labelStyle.borderTopLeftRadius,
                padding: labelStyle.paddingLeft,
                background: labelStyle.backgroundColor,
                titleSize: parseFloat(getComputedStyle(title).fontSize),
                factSize: parseFloat(getComputedStyle(facts).fontSize),
                captionGap: footer.top - image.bottom,
                artworkGap: next.top - card.bottom,
                marginGap: parseFloat(getComputedStyle(first).marginBottom),
                actionsAligned: Math.abs(context.bottom - footer.bottom),
                noOverlap: box.right <= context.left,
                labelWidth: box.width,
                secondLabelWidth: second.querySelector('.artwork-label').getBoundingClientRect().width,
                captionBottomAligned: Math.abs(box.bottom - footer.bottom),
                rootTitleHeight: rootTitle.height,
                headerTitleLeft: siteTitle.left,
                headerTitleCenter: (siteTitle.left + siteTitle.right) / 2,
                headerCenter: (rootTitle.left + rootTitle.right) / 2,
                menuShellLeft: document.querySelector('#content').getBoundingClientRect().left,
                docOverflow: document.scrollingElement.scrollWidth > document.scrollingElement.clientWidth + 1,
                actionBorder: getComputedStyle(first.querySelector('.artwork-card__actions')).borderTopWidth,
                actionGap: getComputedStyle(first.querySelector('.artwork-card__actions')).gap,
                actionBackground: getComputedStyle(first.querySelector('.artwork-card__actions')).backgroundColor,
                actionButtonBorders: Array.from(first.querySelectorAll('.artwork-context-button')).map((item) => getComputedStyle(item).borderTopWidth),
                actionSeparators: Array.from(first.querySelectorAll('.artwork-context-button')).map((item) => getComputedStyle(item).borderLeftWidth),
                actionLeft: context.left,
                actionRight: context.right,
                footerRight: footer.right,
                lineCount: first.querySelector('.artwork-label__heading').children.length,
                actualTitle: first.querySelector('.artwork-label__title').textContent.trim(),
            };
        });

        expect(state.border, 'framed label at ' + width).toBe('1px');
        expect(state.radius).toBe('0px');
        expect(parseFloat(state.padding), 'framed label padding').toBeGreaterThan(0);
        expect(state.background).toBe('rgb(255, 255, 255)');
        expect(state.actualTitle).toBe('Grüner Planet (in cooperation with Krys Ubo)');
        expect(state.lineCount).toBe(2);
        expect(state.actionBorder).toBe('1px');
        expect(state.actionGap).toBe('0px');
        expect(state.actionBackground).toBe('rgb(255, 255, 255)');
        expect(state.actionButtonBorders).toEqual(['0px', '0px', '0px']);
        expect(state.actionSeparators).toEqual(['0px', '1px', '1px']);
        expect(state.actionRight).toBeLessThanOrEqual(state.footerRight + 1);
        expect(state.titleSize, 'title hierarchy').toBeGreaterThan(state.factSize);
        expect(state.captionGap).toBeGreaterThanOrEqual(12);
        expect(state.artworkGap).toBeCloseTo(state.marginGap, 1);
        expect(state.actionsAligned, 'action rail shares label bottom baseline at '+width).toBeLessThan(1);
        expect(state.captionBottomAligned, 'label shares footer bottom baseline at '+width).toBeLessThan(1);
        if (width >= 546) {
            expect(Math.abs(state.labelWidth - state.secondLabelWidth), 'same full-size label width at '+width).toBeLessThan(1);
        } else {
            // At phone widths the action rail takes its legitimate share.
            expect(state.labelWidth, 'responsive label width at '+width).toBeLessThanOrEqual(state.secondLabelWidth + 1);
        }
        expect(state.noOverlap, 'caption and actions are separate at ' + width).toBe(true);
        expect(state.rootTitleHeight).toBeCloseTo(75.2, 0);
        expect(state.docOverflow).toBe(false);
        if (width > 1024) expect(Math.abs(state.headerTitleLeft - state.menuShellLeft)).toBeLessThan(1);
        else expect(Math.abs(state.headerTitleCenter - state.headerCenter)).toBeLessThan(1);
        if (previousGap !== null) expect(state.marginGap).toBeCloseTo(previousGap, 2);
        previousGap = state.marginGap;
    }
});
