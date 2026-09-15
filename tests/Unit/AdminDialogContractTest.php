<?php

it('keeps migrated admin dialog surfaces on the shared dialog contract', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [
        'app/Filament/Pages/Dashboard.php',
        'app/Filament/Pages/General.php',
        'app/Filament/Pages/HomePresentation.php',
        'app/Filament/Pages/JournalWorkspace.php',
        'app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php',
        'app/Filament/Pages/Concerns/ManagesSitePageCreateDialog.php',
        'app/Filament/Pages/Concerns/ManagesSitePageEditDialog.php',
        'app/Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php',
        'app/Filament/Pages/Concerns/CustomPageWorkspaceCvActions.php',
        'app/Filament/Pages/Concerns/CustomPageWorkspaceLifecycle.php',
        'app/Filament/Pages/Concerns/CustomPageWorkspaceListContactActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceBatchActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceMoveActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($root.'/'.$file);

        expect($source, $file)
            ->toContain('AdminDialog::')
            ->not->toContain('->modalWidth(')
            ->not->toContain('->extraModalWindowAttributes(')
            ->not->toContain('media-file-dialog')
            ->not->toContain('media-dialog-footer__')
            ->not->toContain('custom-page-dialog');
    }
});

it('keeps dialog naming and publication bridge placement canonical', function (): void {
    $root = dirname(__DIR__, 2);
    $dialogConcern = $root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php';
    $legacyConcern = $root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkModals.php';
    $bridgeHook = $root.'/resources/views/filament/partials/publication-state-bridge-hook.blade.php';
    $legacyBridgeHook = $root.'/resources/views/filament/partials/publication-state-bridge.blade.php';
    $livewireBridge = $root.'/resources/views/livewire/admin/publication-state-bridge.blade.php';
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');

    expect(file_exists($dialogConcern))->toBeTrue();
    expect(file_exists($legacyConcern))->toBeFalse();
    expect(file_exists($bridgeHook))->toBeTrue();
    expect(file_exists($legacyBridgeHook))->toBeFalse();
    expect(file_exists($livewireBridge))->toBeTrue();

    expect(file_get_contents($dialogConcern))
        ->toContain('trait GalleryWorkspaceArtworkDialogs')
        ->not->toContain('trait GalleryWorkspaceArtworkModals');

    expect($provider)
        ->toContain("view('filament.partials.publication-state-bridge-hook')")
        ->not->toContain("view('filament.partials.publication-state-bridge')");
});

it('retires the legacy dialog stylesheet without reintroducing legacy footer chrome', function (): void {
    $root = dirname(__DIR__, 2);
    $legacy = $root.'/resources/css/admin/dialogs.css';
    $contract = file_get_contents($root.'/resources/css/admin/dialog-contract.css');
    $media = file_get_contents($root.'/resources/css/admin/media.css');

    expect(file_exists($legacy))->toBeFalse();

    foreach ([$contract, $media] as $source) {
        expect($source)
            ->not->toContain('.media-file-dialog .fi-modal-footer-actions')
            ->not->toContain('media-dialog-footer__')
            ->not->toContain('admin-dialog-footer__');
    }
});

it('registers the shared admin control adapter panel wide', function (): void {
    $root = dirname(__DIR__, 2);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adapter = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');
    $controls = file_get_contents($root.'/resources/css/admin/controls.css');

    expect($provider)
        ->toContain('AdminControl::register();');

    expect($adapter)
        ->toContain('admin-control-field admin-form-controls')
        ->toContain('TextInput::configureUsing')
        ->toContain('Select::configureUsing')
        ->toContain('Textarea::configureUsing')
        ->toContain('Checkbox::configureUsing')
        ->toContain('Toggle::configureUsing');

    expect($controls)
        ->toContain('.fi-select-input-dropdown')
        ->toContain('.fi-select-input-search-input')
        ->toContain('.fi-select-input-option')
        ->toContain('.fi-select-input-option.fi-selected')
        ->toContain('.fi-select-input-option-group-header')
        ->toContain('.fi-select-input-selected-item');
});
