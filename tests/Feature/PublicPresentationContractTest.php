<?php

it('keeps hierarchical public navigation operable in one expanding sticky shell for mouse touch and keyboard', function () {
    $css = file_get_contents(public_path('css/public-presentation.css'));
    $source = file_get_contents(resource_path('js/public-navigation.js'));

    expect($css)
        ->toContain('.site-header {', 'position: sticky;', '.site-header::after {', 'order: 3;')
        ->toContain('.site-navigation__submenu-toggle', '.site-navigation__submenu-region', 'grid-template-rows: 0fr;', 'grid-template-rows: 1fr;')
        ->not->toContain('box-shadow: 0 7px 16px', 'position: fixed', '--submenu-left')
        ->and($source)
        ->toContain("toggle.addEventListener('click'", "event.key === 'ArrowDown'", "['ArrowDown', 'ArrowUp']", "event.key === 'Escape'")
        ->toContain("event.pointerType === 'mouse'", 'regionInner.append(controls.submenu)', 'shiftByOneItem');
});

it('keeps gallery sequence markup free from synthetic leading separators', function () {
    $gallery = file_get_contents(resource_path('views/pages/artworks/index.blade.php'));
    $card = file_get_contents(resource_path('views/components/artwork-card.blade.php'));

    expect($gallery)
        ->toContain('@forelse ($artworks as $artwork)', '<x-artwork-card')
        ->not->toContain('separator', 'divider')
        ->and($card)
        ->not->toContain('separator', 'divider', '::before', '::after');
});

it('keeps menu gallery cv and exhibitions on one canonical visible grid', function () {
    $css = file_get_contents(public_path('css/public-presentation.css'));
    $contentCss = file_get_contents(resource_path('css/public-content.css'));
    $gallery = file_get_contents(resource_path('views/pages/artworks/index.blade.php'));

    expect($css)
        ->toContain('--public-shell-max: 800px;', '--public-art-max: 650px;', '--public-mobile-inset: .65rem;')
        ->toContain('.site-navigation {', 'var(--public-shell-max)')
        ->toContain('.site-header nav {', 'var(--public-art-max)')
        ->toContain('.site-navigation__item {', 'flex: 1 0 max-content;', 'border-left: 1px solid #e4e1de;')
        ->toContain('.site-navigation__item:last-child {', 'border-right: 1px solid #e4e1de;')
        ->toContain('.site-navigation__primary::after', 'background: var(--public-active);')
        ->toContain('.artwork-list {', '.cv-page,', '.exhibitions-page,', 'var(--public-art-max)')
        ->toContain('@media (max-width: 760px)', 'padding: var(--public-mobile-inset);')
        ->toContain('@media (max-width: 1040px)', '.site-title {')
        ->and($contentCss)
        ->not->toContain('Public acceptance grid', '--public-shell-max:', '--public-art-max:')
        ->and($gallery)
        ->toContain(':eager="$loop->index < 5"');
});

it('separates artwork zoom from artwork detail navigation', function () {
    $card = file_get_contents(resource_path('views/components/artwork-card.blade.php'));

    expect($card)
        ->toContain('class="artwork-card__link"', 'data-artwork-viewer-trigger', 'class="artwork-label-trigger"', 'View details for')
        ->toContain('href="{{ route(\'artworks.show\', $artwork->slug) }}"');

    $labelSegment = explode('class="artwork-label-trigger"', $card, 2)[1] ?? '';
    $labelSegment = explode('</a>', $labelSegment, 2)[0] ?? $labelSegment;

    expect($labelSegment)->not->toContain('data-artwork-viewer-trigger');
});

it('keeps exhibition dates openings text and image albums in one editorial entry', function () {
    $view = file_get_contents(resource_path('views/pages/exhibitions.blade.php'));
    $css = file_get_contents(public_path('css/public-presentation.css'));

    expect($view)
        ->toContain('exhibition-entry__schedule', 'Vernissage', 'exhibition-entry__description-details', 'exhibition-media')
        ->toContain("preg_match('/\\bVernissage:")
        ->and($css)
        ->toContain('.exhibition-entry__schedule', '.exhibition-entry__opening', 'grid-template-columns: repeat(3, minmax(0, 1fr));');
});
