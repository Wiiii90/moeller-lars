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
        'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkModals.php',
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

it('keeps legacy dialog footer chrome out of the shared geometry layer', function (): void {
    $root = dirname(__DIR__, 2);
    $dialogs = file_get_contents($root.'/resources/css/admin/dialogs.css');

    expect($dialogs)
        ->not->toContain('.media-file-dialog .fi-modal-footer-actions')
        ->not->toContain('media-dialog-footer__')
        ->not->toContain('admin-dialog-footer__');
});

it('registers the shared admin control adapter panel wide', function (): void {
    $root = dirname(__DIR__, 2);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adapter = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');

    expect($provider)
        ->toContain('AdminControl::register();');

    expect($adapter)
        ->toContain("admin-control-field admin-form-controls")
        ->toContain('TextInput::configureUsing')
        ->toContain('Select::configureUsing')
        ->toContain('Textarea::configureUsing')
        ->toContain('Checkbox::configureUsing')
        ->toContain('Toggle::configureUsing');
});
