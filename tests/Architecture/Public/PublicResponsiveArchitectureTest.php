<?php

it('uses the current content-fit public responsive contract', function (): void {
    $root = dirname(__DIR__, 3);
    $presentation = file_get_contents($root.'/resources/css/public-presentation.css');
    $foundation = file_get_contents($root.'/resources/css/app.css');
    $customPages = file_get_contents($root.'/resources/css/custom-pages.css');

    expect($presentation)
        ->toContain('@media (max-width: 64rem)')
        ->toContain('@media (max-width: 46rem)')
        ->toContain('@media (max-width: 34rem)')
        ->toContain('.site-navigation__rail > nav')
        ->toContain('.site-navigation,')
        ->toContain('--public-content-inset:')
        ->toContain('--public-content-block-inset:')
        ->toContain('--public-shell-gap:')
        ->toContain('margin-block-start: var(--public-shell-gap);')
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

    expect($foundation)
        ->toContain('@media (max-width: 40rem)')
        ->toContain('body.public-site .site-header')
        ->toContain('scrollbar-gutter: stable both-edges;')
        ->not->toContain('max-width: 550px');

    expect($customPages)
        ->toContain('@media (max-width: 42rem)');
});
