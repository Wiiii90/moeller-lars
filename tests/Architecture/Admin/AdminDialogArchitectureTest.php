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
        'app/Filament/Pages/Concerns/CustomPageWorkspaceLifecycle.php',
        'app/Filament/Pages/Concerns/CustomPageWorkspaceListContactActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceBatchActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceMoveActions.php',
        'app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php',
    ];

    foreach ($files as $file) {
        $path = $root.'/'.$file;

        expect(is_file($path), $file)->toBeTrue();

        $source = file_get_contents($path);
        expect($source, $file)->toBeString();
        expect($source, $file)
            ->toContain('AdminDialog::')
            ->not->toContain('->modalWidth(')
            ->not->toContain('->extraModalWindowAttributes(');
    }
});

it('keeps editorial dialog geometry and storage preview details on the shared layout contract', function (): void {
    $root = dirname(__DIR__, 3);
    $adapter = file_get_contents($root.'/app/Filament/Support/Dialogs/AdminDialog.php');
    $contract = file_get_contents($root.'/resources/css/admin/dialog-contract.css');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $mediaCss = file_get_contents($root.'/resources/css/admin/media.css');
    $homeWorkspace = file_get_contents($root.'/app/Filament/Pages/HomePresentation.php');
    $artworkPreview = file_get_contents($root.'/resources/views/filament/resources/artworks/partials/preview-dialog.blade.php');
    $mediaPreview = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/preview-dialog.blade.php');

    expect($adapter)
        ->toContain('AdminDialogSize $size = AdminDialogSize::Large')
        ->toContain('$size === AdminDialogSize::Small ? AdminDialogSize::Small : AdminDialogSize::Large');

    expect($contract)
        ->toContain('.admin-task-dialog .fi-modal-content')
        ->toContain('scrollbar-width: thin')
        ->not->toContain('.admin-task-dialog .fi-modal-content::-webkit-scrollbar {\n    display: none');

    expect($layouts)
        ->toContain("html.fi,\n.fi-sidebar-nav {\n    scrollbar-gutter: auto !important;\n}")
        ->not->toContain('scrollbar-gutter: stable');

    expect($homeWorkspace)
        ->toContain('use Filament\\Schemas\\Components\\Grid;')
        ->toContain("->columns(['md' => 2])")
        ->toContain("->modalHeading('Home settings')");

    expect(substr_count($artworkPreview, 'class="media-file-dialog__details"'))->toBe(1);
    expect(substr_count($mediaPreview, 'class="media-file-dialog__details '))->toBe(2);
    expect($mediaPreview)
        ->toContain('class="media-file-dialog__details media-file-dialog__metadata"')
        ->toContain('class="media-file-dialog__details media-file-dialog__usage"')
        ->toContain('class="media-file-dialog__metadata-grid"')
        ->toContain('class="media-file-dialog__references"');

    expect($mediaCss)
        ->toContain('.media-file-dialog .media-file-dialog__metadata-grid')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->toContain('.media-file-dialog__references a,')
        ->toContain('grid-template-columns: minmax(8rem, .55fr) minmax(0, 1fr);');
});
