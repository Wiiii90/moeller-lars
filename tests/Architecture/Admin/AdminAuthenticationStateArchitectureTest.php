<?php

it('never live-syncs application-owned authentication fields', function (): void {
    $authFiles = glob(app_path('Filament/Auth/*.php')) ?: [];
    $files = [
        ...$authFiles,
        app_path('Filament/Support/AccountMenuAction.php'),
        app_path('Filament/Support/AdminPasswordField.php'),
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)
            ->not->toContain('->live(')
            ->not->toContain('->liveOnBlur(')
            ->not->toContain('wire:model.live');
    }
});
