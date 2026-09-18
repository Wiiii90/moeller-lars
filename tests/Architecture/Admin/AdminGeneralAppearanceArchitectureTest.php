<?php

it('keeps General background modes and controlled select semantics canonical', function (): void {
    $root = dirname(__DIR__, 3);
    $general = file_get_contents($root.'/app/Filament/Pages/General.php');

    expect($general)
        ->toContain("PublicAppearance::MODE_SOLID => 'Solid'")
        ->toContain("PublicAppearance::MODE_GRADIENT => 'Linear gradient'")
        ->toContain("\$data['background_mode'] = PublicAppearance::MODE_SOLID;")
        ->toContain("\$data['background_color'] = PublicAppearance::DEFAULT_PAGE_COLOR;")
        ->not->toContain("PublicAppearance::MODE_DEFAULT => 'Default'")
        ->not->toContain('->options(PublicAppearance::modeOptions())')
        ->not->toContain("\$data['background_mode'] = PublicAppearance::MODE_DEFAULT;");
});
