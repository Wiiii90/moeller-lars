<?php

it('keeps hierarchical public navigation generic and operable by hover focus and keyboard', function () {
    $presentationCss = file_get_contents(resource_path('css/public-presentation.css'));
    $appCss = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $source = file_get_contents(resource_path('js/public-navigation.js'));

    expect($presentationCss)
        ->toContain('.site-header::after {', 'order: 3;')
        ->toContain('.site-navigation__submenu-region', 'grid-template-rows: 0fr;', 'grid-template-rows: 1fr;')
        ->not->toContain('box-shadow: 0 7px 16px', 'position: fixed', '--submenu-left', 'site-navigation__submenu-toggle')
        ->and($appCss)
        ->toContain('html.public-site-root {', 'body.public-site {', 'body.public-site .site-header {', 'position: relative;')
        ->toContain('body.public-site .site-scroll-region {', 'overflow-y: auto;', 'flex: 1 1 auto;')
        ->and($layout)
        ->toContain('class="public-site-root"', 'class="public-site"', 'class="site-scroll-region"', 'data-site-scroll-region')
        ->toContain("'resources/css/public-presentation.css'")
        ->not->toContain("asset('css/public-presentation.css')", 'data-navigation-submenu-toggle', 'site-navigation__submenu-toggle')
        ->and($source)
        ->toContain("event.key === 'ArrowDown'", "['ArrowDown', 'ArrowUp']", "event.key === 'Escape'")
        ->toContain("event.pointerType === 'mouse'", 'regionInner.append(controls.submenu)', 'shiftByOneItem')
        ->not->toContain('data-navigation-submenu-toggle', "toggle.addEventListener('click'");
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
    $css = file_get_contents(resource_path('css/public-presentation.css'));
    $contentCss = file_get_contents(resource_path('css/public-content.css'));
    $appCss = file_get_contents(resource_path('css/app.css'));
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
        ->not->toContain('--public-shell-max:', '--public-art-max:', '.site-navigation {')
        ->and($appCss)
        ->not->toContain('--public-shell-max:', '--public-art-max:', '.site-navigation__item {')
        ->and($gallery)
        ->toContain(':eager="$loop->index < 5"');
});

it('separates artwork zoom from artwork detail navigation', function () {
    $card = file_get_contents(resource_path('views/components/artwork-card.blade.php'));

    expect($card)
        ->toContain('class="artwork-card__link"', 'data-artwork-viewer-trigger', 'class="artwork-label-trigger"', 'View details for')
        ->toContain('$detailUrl = $preview->url(route(\'artworks.show\', $artwork->slug));', 'href="{{ $detailUrl }}"');

    $labelSegment = explode('class="artwork-label-trigger"', $card, 2)[1] ?? '';
    $labelSegment = explode('</a>', $labelSegment, 2)[0] ?? $labelSegment;

    expect($labelSegment)->not->toContain('data-artwork-viewer-trigger');
});

it('keeps exhibition schedule content structured and image albums generic', function () {
    $view = file_get_contents(resource_path('views/pages/exhibitions.blade.php'));
    $css = file_get_contents(resource_path('css/public-presentation.css'));
    $model = file_get_contents(app_path('Models/Exhibition.php'));
    $resource = file_get_contents(app_path('Filament/Resources/Exhibitions/ExhibitionResource.php'));

    expect($view)
        ->toContain('exhibition-entry__schedule', '$exhibition->opening_text', 'Vernissage', 'exhibition-entry__description-details', 'exhibition-media')
        ->not->toContain("preg_match('/\\bVernissage:", 'preg_replace')
        ->and($model)
        ->toContain("'opening_text'")
        ->and($resource)
        ->toContain("TextInput::make('opening_text')")
        ->and($css)
        ->toContain('.exhibition-entry__schedule', '.exhibition-entry__opening', 'grid-template-columns: repeat(3, minmax(0, 1fr));');
});
