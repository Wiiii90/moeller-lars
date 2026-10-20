import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const source = (file) => readFileSync(new URL('../../' + file, import.meta.url), 'utf8');

test('public paper cannot reintroduce DOM measurement feedback', () => {
    const app = source('resources/js/app.js');
    const header = source('resources/js/public-header.js');
    const presentation = source('resources/css/public-presentation.css');

    // This document's contour is presentation-only. Its former observers
    // measured content and then changed its ancestor's geometry.
    assert.equal(existsSync(new URL('../../resources/js/public-paper-frame.js', import.meta.url)), false);
    assert.doesNotMatch(app, /public-paper-frame\.js|initializePublicPaperFrame/);
    assert.doesNotMatch(presentation, /--public-paper-content-end/);

    // A header that changes its own stacked class must never observe its
    // own dimensions or an element whose width that class mutates.
    assert.doesNotMatch(header, /\bnew\s+(?:ResizeObserver|MutationObserver)\b|\b(?:requestAnimationFrame|setInterval)\s*\(/);
    assert.match(header, /window\.addEventListener\('resize', update/);
    assert.match(header, /window\.removeEventListener\('resize', update/);
});

test('authored public CSS has no transition or animation properties', () => {
    for (const file of [
        'resources/css/app.css',
        'resources/css/public-content.css',
        'resources/css/public-presentation.css',
        'resources/css/custom-pages.css',
        'resources/css/journal-presentation.css',
    ]) {
        assert.doesNotMatch(
            source(file),
            /^\s*(?:-webkit-)?(?:animation|transition)(?:-[\w-]+)?\s*:/gm,
            file + ' unexpectedly introduced public CSS motion',
        );
    }
});

test('public paper follows real content height rather than unused viewport height', () => {
    const foundation = source('resources/css/app.css');
    const presentation = source('resources/css/public-presentation.css');
    assert.match(foundation, /body\.public-site \.public-paper-frame\s*\{[^}]*flex:\s*0 0 auto;/);
    assert.match(foundation, /body\.public-site\s*\{[^}]*min-height:\s*100dvh;/);
    assert.doesNotMatch(foundation, /\.public-paper-frame\s*\{[^}]*flex:\s*1 0 auto;/);
    assert.match(presentation, /\.public-paper-decoration::before,\s*\.public-paper-decoration::after\s*\{/);
    assert.match(presentation, /100% - var\(--public-content-block-inset\) - var\(--public-paper-return-inset\)/);
    assert.doesNotMatch(presentation, /--public-paper-content-end/);
});

test('public Menu has no implicit title-only divider fragment', () => {
    const presentation = source('resources/css/public-presentation.css');
    const baseMenu = presentation.match(/^\.site-navigation\s*\{([^}]*)\}/m)?.[1];
    assert.ok(baseMenu, 'canonical Menu CSS must exist');
    assert.doesNotMatch(baseMenu, /box-shadow\s*:|border-bottom\s*:/);
    assert.match(presentation, /\.site-header--divider-menu \.site-navigation\s*\{[^}]*border-bottom:\s*1px solid var\(--public-separator-color/);
    assert.match(presentation, /\.site-header--divider-fine \.site-navigation\s*\{[^}]*border-bottom:\s*0;/);
});

test('copyright and artwork caption share a single bounded row, even for long titles', () => {
    const presentation = source('resources/css/public-presentation.css');
    const soleRights = presentation.match(/^\.artwork-card__footer--with-rights\s*\{([^}]*)\}/m)?.[1];
    const sideBySide = presentation.match(/^\.artwork-card__footer--with-rights:has\(\.artwork-caption-link\)\s*\{([^}]*)\}/m)?.[1];
    const reversed = presentation.match(/^\.artwork-card__footer--rights-right:has\(\.artwork-caption-link\)\s*\{([^}]*)\}/m)?.[1];
    const caption = presentation.match(/^\.artwork-card__footer--with-rights \.artwork-caption-link\s*\{([^}]*)\}/m)?.[1];

    assert.ok(soleRights && sideBySide && reversed && caption, 'one shared footer with both alignments');
    assert.match(soleRights, /grid-template-columns:\s*minmax\(0,\s*1fr\);/);
    assert.match(sideBySide, /grid-template-columns:\s*fit-content\(45%\) minmax\(0,\s*1fr\);/);
    assert.match(reversed, /grid-template-columns:\s*minmax\(0,\s*1fr\) fit-content\(45%\);/);
    assert.match(caption, /width:\s*100%;/);
    assert.match(caption, /min-width:\s*0;/);
    assert.match(caption, /margin-inline:\s*0;/);
    assert.match(presentation, /\.artwork-card__footer--rights-right:has\(\.artwork-caption-link\) \.artwork-card__rights\s*\{[^}]*grid-column:\s*2;[^}]*grid-row:\s*1;/);
    assert.match(presentation, /\.artwork-card__footer--rights-right \.artwork-caption-link\s*\{[^}]*grid-column:\s*1;[^}]*grid-row:\s*1;/);
    assert.doesNotMatch(presentation, /\.artwork-caption__title\s*\{[^}]*text-overflow:\s*ellipsis;/);
});

test('menu-to-page divider gradients reuse the existing stroke without extra pseudo-lines', () => {
    const presentation = source('resources/css/public-presentation.css');
    const layout = source('resources/views/layouts/app.blade.php');
    const menu = presentation.match(/^\.site-header--divider-menu \.site-navigation\s*\{([^}]*)\}/m)?.[1];
    const full = presentation.match(/^\.site-header--divider-full::after\s*\{([^}]*)\}/m)?.[1];
    const fullGap = presentation.match(/^\.site-header--divider-full_gap::after\s*\{([^}]*)\}/m)?.[1];

    assert.ok(menu, 'existing Menu-width divider remains the owner');
    assert.match(menu, /border-bottom: 1px solid var\(--public-separator-color/);
    assert.match(menu, /border-image-source:\s*var\(--public-separator-gradient, none\);/);

    for (const line of [full, fullGap]) {
        assert.ok(line, 'full-width divider still has one pseudo-element');
        assert.match(line, /background:\s*var\(--public-separator-paint, var\(--public-separator-color/);
    }

    assert.match(layout, /--public-separator-gradient:/);
    assert.match(layout, /--public-separator-paint:/);
    assert.match(layout, /nonce="{{ \$publicStyleNonce }}"/);
});
