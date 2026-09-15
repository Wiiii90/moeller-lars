<?php

it('routes migrated admin dialogs through the shared dialog adapter', function (): void {
    $root = dirname(__DIR__, 3);
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
            ->not->toContain('->extraModalWindowAttributes(');
    }
});
