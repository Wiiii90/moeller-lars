<?php

it('uses the current content-fit public responsive contract', function (): void {
    $root = dirname(__DIR__, 3);
    $presentation = file_get_contents($root.'/resources/css/public-presentation.css');
    $foundation = file_get_contents($root.'/resources/css/app.css');
    $customPages = file_get_contents($root.'/resources/css/custom-pages.css');
    $content = file_get_contents($root.'/resources/css/public-content.css');

    expect($presentation)
        ->toContain('@media (max-width: 64rem)')
        ->toContain('@media (max-width: 46rem)')
        ->toContain('@media (max-width: 34rem)')
        ->toContain('.site-navigation__rail > nav')
        ->toContain('.site-navigation__rail[hidden]')
        ->toContain('.site-navigation__back')
        ->not->toContain('.site-navigation__submenu-region')
        ->not->toContain('--public-submenu-duration')
        ->toContain('.site-navigation,')
        ->toContain('--public-content-inset: max(')
        ->toContain('calc((100% - var(--public-art-max)) / 2)')
        ->toContain('min(var(--public-content-padding)')
        ->toContain('--public-content-block-inset: 2rem;')
        ->toContain('--public-shell-gap: .75rem;')
        ->toContain('--public-short-page-min-height: 15rem;')
        ->toContain('--public-section-gap: 3.55rem;')
        ->toContain('min-height: var(--public-short-page-min-height);')
        ->toContain('flex: 1 0 auto;')
        ->toContain('margin-bottom: var(--public-section-gap);')
        ->toContain('.artwork-label {')
        ->toContain('border: 1px solid var(--public-artwork-line);')
        ->toContain('flex: 0 1 19rem;')
        ->toContain('background: #fffdf9;')
        ->toContain('padding: .74rem .85rem .7rem;')
        ->toContain('align-items: start;')
        ->toContain('align-items: flex-end;')
        ->toContain('.artwork-context-button + .artwork-context-button')
        ->not->toContain('.artwork-label__title-qualifier')
        ->not->toContain('margin-bottom: var(--public-mobile-inset);')
        ->not->toContain('min-height: clamp(4rem, 10vw, 4.7rem)')
        ->not->toContain('font-size: clamp(1.3rem, 5vw, 1.75rem)')
        ->not->toContain('padding-block: clamp(')
        ->not->toContain('margin-bottom: clamp(3.2rem, 6vw, 5rem)')
        ->toContain('margin-block: var(--public-shell-gap);')
        ->toContain('html:has(.artwork-viewer[open])')
        ->toContain('scrollbar-gutter: stable;')
        ->toContain('overflow-x: clip;')
        ->not->toContain('scrollbar-gutter: auto;')
        ->toContain('padding-inline: var(--public-content-inset);')
        ->toContain('padding-block: var(--public-content-block-inset);')
        ->toContain('flex: 0 0 max-content;')
        ->toContain('.site-navigation__rail.is-overflowing .site-navigation__item')
        ->toContain('.site-navigation__submenu.is-overflowing .site-navigation__item')
        ->toContain('flex: 1 0 max-content;')
        ->toContain('.site-navigation__item[hidden]')
        ->toContain('flex-wrap: nowrap;')
        ->not->toContain('--public-navigation-inset')
        ->not->toContain('.site-header::after')
        ->not->toContain('overflow-x: auto')
        ->not->toContain('max-width: 550px')
        ->not->toContain('max-width: 980px')
        ->not->toContain('max-width: 1400px');

    // One geometric owner: no parallel width, label-size or heading rules
    // changing the shell at the arbitrary 46rem threshold.
    expect(substr_count($presentation, '--public-content-inset:'))->toBe(1);
    expect(substr_count($presentation, '.site-header h1 {'))->toBe(1);

    expect($foundation)
        ->toContain('@media (max-width: 40rem)')
        ->toContain('html:has(> body.public-site)')
        ->toContain('body.public-site .site-header')
        ->toContain('display: flex;')
        ->toContain('flex-direction: column;')
        ->not->toContain('display: flow-root;')
        ->toContain('position: sticky;')
        ->toContain('top: 0;')
        ->not->toContain('scrollbar-gutter: stable both-edges;')
        ->not->toContain('.site-scroll-region')
        ->not->toContain('max-height: 100dvh;')
        ->not->toContain('max-width: 550px');

    // CV styles have one component owner: shared Rich Text and the public
    // shell no longer carry CV-specific typography or composition.
    expect($presentation)->not->toContain('.cv-entry {')->not->toContain('.cv-biography {');
    expect($content)->not->toContain('.cv-entry');
    expect($customPages)
        ->toContain('.cv-biography {')
        ->toContain('.cv-entry {')
        ->toContain('.cv-entry__date {')
        ->toContain('.cv-entry .rich-text {')
        ->toContain('@media (max-width: 42rem)');
});
