<?php

it('keeps persisted General layout controls on discrete blur commits', function (): void {
    $root = dirname(__DIR__, 3);
    $layout = file_get_contents($root.'/resources/views/livewire/admin/general-layout-controls.blade.php');

    expect($layout)
        ->toContain('wire:model.blur="pageWidth"')
        ->toContain('wire:model.blur="contentPadding"')
        ->not->toContain('wire:model.live.debounce');
});


it('uses one shared read-only live-search transport and keeps persistence event-driven', function (): void {
    $root = dirname(__DIR__, 3);
    $searchInput = file_get_contents($root.'/resources/views/components/admin/search-input.blade.php');
    $dialog = file_get_contents($root.'/app/Filament/Support/Dialogs/AdminDialog.php');
    $autosave = file_get_contents($root.'/app/Filament/Support/Dialogs/InteractsWithAdminEditDialogAutosave.php');

    expect($searchInput)
        ->toContain('wire:model.live.debounce.300ms="{{ $model }}"')
        ->toContain('x-on:keydown.enter.prevent');

    expect($dialog)
        ->toContain("'wire:change' => 'persistMountedAdminEdit'");

    expect($autosave)
        ->toContain('public function persistMountedAdminEdit(): void')
        ->toContain('adminEditDialogPersistedFingerprints');

    $rawSearchInputs = [];
    $unexpectedDebounces = [];
    foreach ([
        $root.'/resources/views/filament',
        $root.'/resources/views/livewire/admin',
    ] as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                continue;
            }

            $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (str_contains($source, 'type="search"')) {
                $rawSearchInputs[] = $relative;
            }
            if (str_contains($source, 'wire:model.live.debounce')
                || str_contains($source, 'x-on:input.debounce')) {
                $unexpectedDebounces[] = $relative;
            }
        }
    }

    expect($rawSearchInputs)->toBe([]);
    expect($unexpectedDebounces)->toBe([]);

    $remoteSearchViolations = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/app/Filament', FilesystemIterator::SKIP_DOTS),
    );
    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            continue;
        }

        $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
        if (str_contains($source, 'getSearchResultsUsing(')
            && ! str_contains($source, 'searchDebounce(AdminControl::SEARCH_DEBOUNCE_MS)')) {
            $remoteSearchViolations[] = $relative;
        }

        if (str_contains($source, '->debounce(')
            || str_contains($source, 'live(debounce:')) {
            $remoteSearchViolations[] = $relative.' -> persistence debounce';
        }
    }

    sort($remoteSearchViolations);
    expect($remoteSearchViolations)->toBe([]);
});

it('does not make text-like Filament controls live per keystroke', function (): void {
    $root = dirname(__DIR__, 3);
    $constructors = [
        'TextInput::make(' => true,
        'Textarea::make(' => true,
        'MarkdownEditor::make(' => true,
        'Select::make(' => false,
        'Toggle::make(' => false,
        'Checkbox::make(' => false,
        'DatePicker::make(' => false,
        'DateTimePicker::make(' => false,
        'FileUpload::make(' => false,
        'MediaAssetSelect::make(' => false,
        'MediaAssetSelect::makeId(' => false,
        'AdminColorControl::make(' => true,
    ];
    $violations = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/app/Filament', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            continue;
        }

        $offset = 0;
        while (($liveOffset = strpos($source, '->live()', $offset)) !== false) {
            $prefix = substr($source, 0, $liveOffset);
            $nearestConstructor = null;
            $nearestOffset = -1;

            foreach ($constructors as $constructor => $textLike) {
                $candidateOffset = strrpos($prefix, $constructor);
                if ($candidateOffset !== false && $candidateOffset > $nearestOffset) {
                    $nearestConstructor = $constructor;
                    $nearestOffset = $candidateOffset;
                }
            }

            if ($nearestConstructor !== null && $constructors[$nearestConstructor]) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname())
                    .' -> '.$nearestConstructor.'->live()';
            }

            $offset = $liveOffset + strlen('->live()');
        }
    }

    sort($violations);
    expect($violations)->toBe([]);
});

it('keeps dialog fields on the canonical control grammar', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $control = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');
    $css = file_get_contents($root.'/resources/css/admin/controls.css');

    expect($provider)
        ->toContain('AdminControl::register();');

    expect($control)
        ->toContain('TextInput::configureUsing')
        ->toContain('Textarea::configureUsing')
        ->toContain('Select::configureUsing')
        ->toContain('Checkbox::configureUsing')
        ->toContain('Toggle::configureUsing')
        ->toContain('DatePicker::configureUsing')
        ->toContain('DateTimePicker::configureUsing')
        ->toContain('FileUpload::configureUsing')
        ->toContain('MarkdownEditor::configureUsing')
        ->toContain('OneTimeCodeInput::configureUsing')
        ->toContain('ColorPicker::configureUsing')
        ->toContain('selectablePlaceholder(fn (): bool => ! $field->isRequired())');

    expect($css)
        ->toContain('.admin-control-field .fi-fo-field-label:has(> .fi-checkbox-input)')
        ->toContain('grid-template-columns: max-content minmax(0, 1fr);')
        ->toContain('.admin-control-field .fi-fo-field-label > .fi-checkbox-input')
        ->toContain('margin: 0 !important;')
        ->toContain('.admin-dialog--edit .admin-control-field')
        ->toContain('.fi-fo-field-label-required-mark')
        ->toContain('.fi-one-time-code-input-digit')
        ->toContain('.admin-task-dialog .fi-modal-content .fi-ac-btn-action')
        ->toContain('.admin-task-dialog .fi-modal-content .fi-ac-link-action')
        ->toContain('display: none !important;');
});

it('keeps dialog media selection on the lazy Storage picker', function (): void {
    $root = dirname(__DIR__, 3);
    $picker = file_get_contents($root.'/app/Filament/Support/MediaAssetSelect.php');
    $artwork = file_get_contents($root.'/app/Filament/Support/ArtworkEditDialog.php');
    $galleryCreate = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceFormSupport.php');
    $galleryImages = file_get_contents($root.'/app/Filament/Resources/Artworks/RelationManagers/GalleryImagesRelationManager.php');

    expect($picker)
        ->toContain('?array $allowedMimeTypes = null')
        ->toContain('?Closure $modifyQueryUsing = null')
        ->toContain("->placeholder('Choose from Storage')")
        ->toContain('searchDebounce(AdminControl::SEARCH_DEBOUNCE_MS)')
        ->toContain("->limit(30)->get()");

    expect($artwork)
        ->toContain('MediaAssetSelect::makeId(')
        ->toContain('allowedMimeTypes: self::primaryMimeTypes()')
        ->toContain("->placeholder('No primary media')")
        ->not->toContain("->placeholder('No primary media')\n                        ->selectablePlaceholder(false)")
        ->not->toContain('primaryMediaOptions()')
        ->not->toContain("Select::make('primary_media_asset_id')")
        ->not->toContain('->preload()');

    expect($galleryCreate)
        ->toContain('MediaAssetSelect::makeId(')
        ->toContain('allowedMimeTypes: self::primaryMimeTypes()')
        ->not->toContain('primaryMediaOptions()')
        ->not->toContain("Select::make('primary_media_asset_id')")
        ->not->toContain('->preload()');

    expect($galleryImages)
        ->toContain('MediaAssetSelect::makeId(')
        ->toContain('imagesOnly: true')
        ->toContain('modifyQueryUsing:')
        ->toContain('usedMediaAssetIds()')
        ->not->toContain('availableMediaOptions()')
        ->not->toContain("Select::make('media_asset_id')");
});
