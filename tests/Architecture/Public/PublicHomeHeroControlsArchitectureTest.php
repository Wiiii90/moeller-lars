<?php

it('uses the shared artwork renderer for independent Hero controls without a second publication path', function (): void {
    $root = dirname(__DIR__, 3);
    $hero = file_get_contents($root.'/resources/views/pages/home/artwork.blade.php');
    $card = file_get_contents($root.'/resources/views/components/artwork-card.blade.php');
    $resolver = file_get_contents($root.'/app/Domain/Content/HomePresentationResolver.php');
    $settings = file_get_contents($root.'/app/Filament/Support/HomeSettingsDialog.php');

    expect($hero)
        ->toContain('<x-artwork-card')
        ->toContain(':hero-navigation="$navigableHero"')
        ->toContain(':enable-viewer="$enableViewer"')
        ->toContain(':show-category-link="$showGalleryLink"')
        ->toContain(':show-details="$showDetails"')
        ->toContain('data-home-hero-slide');

    expect($card)
        ->toContain('@if ($enableViewer)')
        ->toContain('data-artwork-viewer-trigger')
        ->toContain('artwork-card__link--static')
        ->toContain('data-home-hero-direction="previous"')
        ->toContain('data-home-hero-direction="next"')
        ->not->toContain('<x-public-icon name="arrow-right" />');

    expect($resolver)
        ->toContain("'heroGroup' => \$heroResolution['group']")
        ->toContain("'showNavigation' => \$heroConfiguration['show_navigation']")
        ->toContain("'enableViewer' => \$heroConfiguration['enable_viewer']");

    expect($settings)
        ->toContain("heroDisplayToggle('show_navigation'")
        ->toContain("heroDisplayToggle('enable_viewer'")
        ->toContain('AdminControl::toggle($field, $label)')
        ->toContain('afterStateUpdated(');
});
