<?php

it('registers the shared admin control adapter panel wide', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adapter = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');

    expect($provider)->toContain('AdminControl::register();');

    expect($adapter)
        ->toContain('TextInput::configureUsing')
        ->toContain('Select::configureUsing')
        ->toContain('Textarea::configureUsing')
        ->toContain('Checkbox::configureUsing')
        ->toContain('Toggle::configureUsing');
});
