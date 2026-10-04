<?php

it('routes migrated admin dialogs through the shared dialog adapter', function (): void {
    $root = dirname(__DIR__, 3);
    $files = [
        'app/Filament/Pages/Activity.php',
        'app/Filament/Pages/Dashboard.php',
        'app/Filament/Pages/General.php',
        'app/Filament/Pages/HomePresentation.php',
        'app/Filament/Pages/JournalWorkspace.php',
        'app/Filament/Pages/SitePages.php',
        'app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php',
        'app/Livewire/Admin/SiteStorageReclaimControl.php',
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
    $dashboardFeedCss = file_get_contents($root.'/resources/css/admin/dashboard-feed.css');
    $dashboardFeedDialog = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed-dialog.blade.php');
    $activityCommitDialog = file_get_contents($root.'/resources/views/filament/pages/partials/activity-commit-details-dialog.blade.php');
    $homeWorkspace = file_get_contents($root.'/app/Filament/Pages/HomePresentation.php');
    $homeSettingsDialog = file_get_contents($root.'/app/Filament/Support/HomeSettingsDialog.php');
    $artworkEditDialog = file_get_contents($root.'/app/Filament/Support/ArtworkEditDialog.php');
    $galleryArtworkDialogs = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php');
    $homeView = file_get_contents($root.'/resources/views/filament/pages/home-presentation.blade.php');
    $artworkPreview = file_get_contents($root.'/resources/views/filament/resources/artworks/partials/preview-dialog.blade.php');
    $mediaPreview = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/preview-dialog.blade.php');

    expect($adapter)
        ->toContain('AdminDialogSize $size = AdminDialogSize::Large')
        ->toContain('$size === AdminDialogSize::Small ? AdminDialogSize::Small : AdminDialogSize::Large')
        ->toContain('self::base($action, AdminDialogType::Confirm, AdminDialogSize::Small)')
        ->toContain('->modal($condition)')
        ->toContain('->modalAlignment(Alignment::Start)')
        ->toContain('->modalFooterActionsAlignment(Alignment::Start)')
        ->not->toContain('->requiresConfirmation(')
        ->not->toContain('AdminDialogSize::Mini');

    expect($contract)
        ->toContain('.admin-task-dialog .fi-modal-content')
        ->toContain('scrollbar-width: thin')
        ->toContain('transform-origin: top right;')
        ->toContain('transition-duration: 200ms !important;')
        ->toContain('transform: translate3d(.3rem, -.3rem, 0) scale(.985) !important;')
        ->toContain('transition-duration: 260ms !important;')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->not->toContain('--admin-dialog-width-mini')
        ->not->toContain('.admin-dialog--mini')
        ->toContain('/* Shared read-only detail content used by Viewer dialogs. */')
        ->toContain('.admin-detail-dialog__meta')
        ->toContain(':has(.admin-detail-dialog--feed) .fi-modal-heading')
        ->not->toContain(':has(.admin-detail-dialog) .fi-modal-heading')
        ->not->toContain('.admin-task-dialog .fi-modal-content::-webkit-scrollbar {\n    display: none');

    expect($dashboardFeedCss)->not->toContain('.admin-detail-dialog');
    expect($dashboardFeedDialog)->toContain('admin-detail-dialog admin-detail-dialog--feed');
    expect(substr_count($activityCommitDialog, '<dt>'))->toBe(3);
    expect($activityCommitDialog)
        ->toContain('<dt>Status</dt>')
        ->toContain('<dt>Commit</dt>')
        ->toContain('<dt>Published</dt>')
        ->toContain('<span>Publication</span>');

    $panelProvider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adminViz = file_get_contents($root.'/resources/js/admin-viz.js');

    expect($layouts)
        ->toContain("html.fi,\n.fi-sidebar-nav {\n    scrollbar-gutter: auto !important;\n}")
        ->toContain('Filament owns modal scroll locking.')
        ->not->toContain('admin-modal-scroll')
        ->not->toContain('admin-modal-existing-scrollbar')
        ->not->toContain('html.fi:has(.fi-modal.fi-modal-open)');

    expect($panelProvider)
        ->not->toContain("view('filament.partials.admin-modal-bootstrap')")
        ->and($adminViz)
        ->not->toContain('admin-modal-scroll')
        ->not->toContain('initializeAdminModalScrollBehavior')
        ->and(is_file($root.'/resources/js/admin-modal-scroll.js'))->toBeFalse()
        ->and(is_file($root.'/resources/views/filament/partials/admin-modal-bootstrap.blade.php'))->toBeFalse();

    expect($homeWorkspace)
        ->toContain('app(HomeSettingsDialog::class)')
        ->toContain("->modalHeading('Home settings')")
        ->not->toContain('use Filament\\Schemas\\Components\\Grid;');

    expect($homeSettingsDialog)
        ->toContain('use Filament\\Schemas\\Components\\Grid;')
        ->toContain("->columns(['md' => 2])")
        ->toContain("->afterStateUpdated(function (\$livewire): void {")
        ->toContain("persistMountedAdminEdit");

    expect($artworkEditDialog)
        ->toContain('final class ArtworkEditDialog')
        ->toContain('public function fill(Artwork $artwork): array')
        ->toContain('public function schema(): array')
        ->toContain('public function save(Artwork $artwork, array $data): bool');

    expect($galleryArtworkDialogs)
        ->toContain('app(ArtworkEditDialog::class)')
        ->toContain('->schema($dialog->schema())')
        ->not->toContain('ArtworkDimensions::split');

    expect($homeWorkspace)
        ->toContain('app(ArtworkEditDialog::class)')
        ->toContain("Action::make('editArtwork')")
        ->toContain('use WithFileUploads;')
        ->not->toContain("ArtworkResource::getUrl('edit'");

    expect($homeView)
        ->toContain("mountAction('editArtwork', { artwork:")
        ->toContain('AdminRowAction::Edit')
        ->not->toContain("candidate['edit_url']");

    expect(substr_count($artworkPreview, 'class="media-file-dialog__details"'))->toBe(1);
    expect(substr_count($mediaPreview, 'class="media-file-dialog__details '))->toBe(2);
    expect($mediaPreview)
        ->toContain('class="media-file-dialog__content storage-media-dialog"')
        ->toContain('class="media-file-dialog__details media-file-dialog__metadata"')
        ->toContain('class="media-file-dialog__details media-file-dialog__usage"')
        ->toContain('class="media-file-dialog__metadata-grid"')
        ->toContain('class="media-file-dialog__references"');

    expect($mediaCss)
        ->toContain('.storage-media-dialog .media-file-dialog__metadata-grid')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->toContain('.media-file-dialog__references a,')
        ->toContain('grid-template-columns: minmax(8rem, .55fr) minmax(0, 1fr);');
});

it('does not reintroduce Filament confirmation mode in admin application code', function (): void {
    $root = dirname(__DIR__, 3);
    $scanRoots = [
        $root.'/app/Filament',
        $root.'/app/Livewire/Admin',
    ];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source !== false && str_contains($source, '->requiresConfirmation(')) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});

it('does not use browser-native Livewire confirmation prompts in admin views', function (): void {
    $root = dirname(__DIR__, 3);
    $scanRoots = [
        $root.'/resources/views/filament',
        $root.'/resources/views/livewire/admin',
    ];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source !== false && str_contains($source, 'wire:confirm=')) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});
