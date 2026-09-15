<?php

it('never live-syncs application-owned authentication fields', function (): void {
    $appPath = dirname(__DIR__, 3).'/app';
    $authFiles = glob($appPath.'/Filament/Auth/*.php') ?: [];
    $files = [
        ...$authFiles,
        $appPath.'/Filament/Support/AccountMenuAction.php',
        $appPath.'/Filament/Support/AdminPasswordField.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)
            ->not->toContain('->live(')
            ->not->toContain('->liveOnBlur(')
            ->not->toContain('wire:model.live');
    }
});
