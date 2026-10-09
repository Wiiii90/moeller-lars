<?php

it('routes every admin dialog consumer through the shared dialog adapter', function (): void {
    $root = dirname(__DIR__, 3);
    $scanRoots = [
        $root.'/app/Filament',
        $root.'/app/Livewire/Admin',
    ];
    $consumers = [];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false || ! str_contains($source, 'AdminDialog::')) {
                continue;
            }

            $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            if ($relative === 'app/Filament/Support/Dialogs/AdminDialog.php') {
                continue;
            }

            $consumers[] = $relative;

            if (str_contains($source, '->modalWidth(')
                || str_contains($source, '->extraModalWindowAttributes(')
                || str_contains($source, '->requiresConfirmation(')) {
                $violations[] = $relative;
            }
        }
    }

    sort($consumers);
    sort($violations);

    $rawModalConsumers = [];
    $modalTokens = [
        '->modalHeading(',
        '->modalDescription(',
        '->modalContent(',
        '->modalSubmitAction(',
        '->extraModalFooterActions(',
        '->createOptionModalHeading(',
        '->createOptionForm(',
        '->createOptionAction(',
    ];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false || str_ends_with($file->getPathname(), 'AdminDialog.php')) {
                continue;
            }

            $usesModalApi = collect($modalTokens)->contains(
                static fn (string $token): bool => str_contains($source, $token),
            );

            if ($usesModalApi && ! str_contains($source, 'AdminDialog::')) {
                $rawModalConsumers[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    sort($rawModalConsumers);

    expect($consumers)->not->toBe([])
        ->and($violations)->toBe([])
        ->and($rawModalConsumers)->toBe([]);
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
    $dialogGeometry = file_get_contents($root.'/resources/js/admin-dialog-geometry.js');
    $homeWorkspace = file_get_contents($root.'/app/Filament/Pages/HomePresentation.php');
    $homeSettingsDialog = file_get_contents($root.'/app/Filament/Support/HomeSettingsDialog.php');
    $artworkEditDialog = file_get_contents($root.'/app/Filament/Support/ArtworkEditDialog.php');
    $galleryArtworkDialogs = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php');
    $homeView = file_get_contents($root.'/resources/views/filament/pages/home-presentation.blade.php');
    $artworkPreview = file_get_contents($root.'/resources/views/filament/resources/artworks/partials/preview-dialog.blade.php');
    $mediaPreview = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/preview-dialog.blade.php');
    $gallerySelection = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceSelectionSupport.php');
    $homeRoutingDialog = file_get_contents($root.'/app/Filament/Support/HomeRoutingDialog.php');
    $sitePages = file_get_contents($root.'/app/Filament/Pages/SitePages.php');
    $journalWorkspace = file_get_contents($root.'/app/Filament/Pages/JournalWorkspace.php');
    $gallerySettings = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php');
    $mediaAssets = file_get_contents($root.'/app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php');

    expect($adapter)
        ->toContain('AdminDialogSize $size = AdminDialogSize::Large')
        ->toContain('self::base($action, AdminDialogType::Edit, $size)')
        ->not->toContain('$size === AdminDialogSize::Small ? AdminDialogSize::Small : AdminDialogSize::Large')
        ->toContain('self::base($action, AdminDialogType::Confirm, AdminDialogSize::Small)')
        ->toContain('->modal($condition)')
        ->toContain('->modalAlignment(Alignment::Start)')
        ->toContain('->modalFooterActionsAlignment(Alignment::Start)')
        ->not->toContain('->requiresConfirmation(')
        ->not->toContain('AdminDialogSize::Mini');

    expect($sitePages)
        ->toContain("AdminDialogSize::Small,\n        );");

    expect($journalWorkspace)
        ->toContain('return AdminDialog::edit($action, AdminDialogSize::Default);')
        ->toContain('return AdminDialog::edit($action, AdminDialogSize::Large);');

    expect(substr_count($gallerySettings, 'AdminDialog::edit($action, AdminDialogSize::Default)'))->toBe(2);

    expect(substr_count($mediaAssets, 'AdminDialog::edit($action, AdminDialogSize::Default)'))->toBe(2);

    expect($contract)
        ->toContain('.admin-task-dialog .fi-modal-content')
        ->toContain('--admin-dialog-header-reserve')
        ->toContain('.admin-dialog--header-actions .fi-modal-heading')
        ->toContain('min-height: var(--admin-dialog-header-action-size);')
        ->toContain('.admin-dialog--header-actions:has(.fi-modal-footer-actions > :nth-child(4))')
        ->toContain('scrollbar-width: thin')
        ->toContain('transform-origin: top right;')
        ->toContain('transition-duration: 200ms !important;')
        ->toContain('transform: translate3d(.3rem, -.3rem, 0) scale(.985) !important;')
        ->toContain('transition-duration: 260ms !important;')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('--admin-dialog-layer-inline-start')
        ->toContain('inset: 0 !important')
        ->toContain('background: rgba(0, 0, 0, .42) !important')
        ->toContain('html.fi.dark .fi-modal:has(')
        ->toContain('background: rgba(0, 0, 0, .56) !important')
        ->toContain('inset-block: var(--admin-dialog-layer-block-start) 0 !important')
        ->toContain('min-height: 0 !important')
        ->toContain('overflow: hidden !important;')
        ->toContain('scrollbar-gutter: auto;')
        ->toContain('width: min(var(--admin-dialog-width), 100%)')
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
        ->toContain('Filament owns modal open/close/focus and document scroll locking.')
        ->not->toContain('admin-modal-visible-scrollbar-lock')
        ->not->toContain('overflow-y: scroll !important;')
        ->not->toContain('padding-right: 0 !important;')
        ->toContain('--admin-workspace-center-shift: clamp(')
        ->toContain("calc(\n                50%\n                - (var(--admin-workspace-max-width) / 2)")
        ->toContain('inset-inline-start: calc(0rem - var(--admin-workspace-center-shift));')
        ->toContain('padding-inline: var(--admin-workspace-gutter) !important;')
        ->toContain('transform: none;')
        ->not->toContain('50vw')
        ->not->toContain('padding-right: var(--admin-header-control-rail)')
        ->not->toContain('admin-modal-scrollbar-gutter')
        ->not->toContain('scrollbar-gutter: stable !important;')
        ->not->toContain('admin-modal-scroll')
        ->not->toContain('html.fi:has(.fi-modal.fi-modal-open)');

    expect($panelProvider)
        ->not->toContain("view('filament.partials.admin-modal-bootstrap')")
        ->and($adminViz)
        ->toContain("import './admin-dialog-geometry.js';")
        ->not->toContain('admin-modal-scroll')
        ->not->toContain('initializeAdminModalScrollBehavior')
        ->and($dialogGeometry)
        ->toContain("window.addEventListener('open-modal'")
        ->toContain("document.addEventListener('x-modal-opened'")
        ->toContain("element.scrollTop = 0")
        ->toContain("--admin-dialog-layer-inline-start")
        ->not->toContain("modal-closed")
        ->not->toContain("window.scrollTo")
        ->not->toContain("window.addEventListener('scroll'")
        ->not->toContain("window.addEventListener('wheel'")
        ->not->toContain("window.addEventListener('touchmove'")
        ->not->toContain("document.addEventListener('keydown'")
        ->not->toContain("admin-modal-visible-scrollbar-lock")
        ->not->toContain("lockedScrollPosition")
        ->not->toContain("document.documentElement.style.overflow")
        ->not->toContain("document.documentElement.style.paddingRight")
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
        ->not->toContain("ArtworkResource::getUrl('edit'"));

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

    expect($gallerySelection)
        ->toContain('private array $actionArtworkCache = []')
        ->toContain('isset($this->actionArtworkCache[$artworkId])')
        ->toContain('return $this->actionArtworkCache[$artworkId] = $artwork;');

    expect($homeRoutingDialog)
        ->toContain('private ?array $targetOptionsCache = null;')
        ->toContain('if ($this->targetOptionsCache !== null)')
        ->toContain('return $this->targetOptionsCache = SiteSection::query()');
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
