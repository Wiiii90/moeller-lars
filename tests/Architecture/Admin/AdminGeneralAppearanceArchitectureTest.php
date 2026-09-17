<?php

it('keeps General background modes and controlled select semantics canonical', function (): void {
    $root = dirname(__DIR__, 3);
    $general = file_get_contents($root.'/app/Filament/Pages/General.php');
    $appearance = file_get_contents($root.'/app/Domain/Content/PublicAppearance.php');

    expect($appearance)
        ->toContain("self::MODE_DEFAULT => 'Default'")
        ->toContain("self::MODE_SOLID => 'Solid color'")
        ->toContain("self::MODE_GRADIENT => 'Linear gradient'");

    expect($general)
        ->toContain("$data['background_mode'] = PublicAppearance::MODE_DEFAULT;")
        ->toContain('->options(PublicAppearance::modeOptions())')
        ->toContain('->placeholder(null)')
        ->toContain('->selectablePlaceholder(false)')
        ->toContain("->disabled(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_DEFAULT)")
        ->not->toContain("$data['background_mode'] = PublicAppearance::MODE_SOLID;");
});
