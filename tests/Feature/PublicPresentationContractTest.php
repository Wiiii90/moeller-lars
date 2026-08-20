<?php

it('keeps hierarchical public navigation operable in one expanding shell for mouse touch and keyboard', function () {
    $css = file_get_contents(public_path('css/public-presentation.css'));
    $source = file_get_contents(resource_path('js/public-navigation.js'));

    expect($css)
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

it('keeps navigation gallery and cv on one narrow responsive content measure', function () {
    $css = file_get_contents(resource_path('css/public-content.css'));
    $gallery = file_get_contents(resource_path('views/pages/artworks/index.blade.php'));

    expect($css)
        ->toContain('--public-shell-max: 800px;', '--public-art-max: 760px;', '--public-content-inset: 20px;')
        ->toContain('body .site-header .site-navigation__item', 'border-left: 1px solid #e4e1de;')
        ->toContain('@media (max-width: 920px)', 'body .site-header .site-title')
        ->and($gallery)
        ->toContain(':eager="$loop->index < 5"');
});
