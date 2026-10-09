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

it('keeps Filament as the MFA security workflow authority', function (): void {
    $root = dirname(__DIR__, 3);
    $mfa = file_get_contents($root.'/app/Filament/Auth/AdminAppAuthentication.php');

    expect($mfa)
        ->toContain('parent::getActions()')
        ->toContain('routeNotification')
        ->toContain("FILAMENT_NOTIFICATION_SESSION_KEY = 'filament.notifications'")
        ->toContain('AdminNotifier::class')
        ->not->toContain('SetUpAppAuthenticationAction')
        ->not->toContain('RegenerateAppAuthenticationRecoveryCodesAction')
        ->not->toContain('DisableAppAuthenticationAction')
        ->not->toContain('saveSecret(')
        ->not->toContain('saveRecoveryCodes(')
        ->not->toContain("decrypt(\$arguments['encrypted'])")
        ->not->toContain('DB::transaction');
});
