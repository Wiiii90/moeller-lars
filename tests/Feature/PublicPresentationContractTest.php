<?php

it('keeps hierarchical public navigation operable for coarse pointers and keyboard without decorative shadow', function () {
    $css = file_get_contents(public_path('css/public-navigation-hierarchy.css'));
    $source = file_get_contents(resource_path('js/public-navigation.js'));

    expect($css)
        ->toContain('(hover: none)', '(pointer: coarse)', '.site-navigation__submenu-toggle', 'display: block;')
        ->not->toContain('box-shadow: 0 7px 16px')
        ->and($source)
        ->toContain("toggle.addEventListener('click'", "event.key === 'ArrowDown'", "['ArrowDown', 'ArrowUp']", "event.key === 'Escape'")
        ->toContain("event.pointerType === 'mouse'");
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
