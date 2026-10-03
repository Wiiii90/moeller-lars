<?php

it('registers the shared admin control adapter panel wide', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adapter = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');
    $selectCss = file_get_contents($root.'/resources/css/admin/selects.css');

    expect($provider)->toContain('AdminControl::register();');

    expect($adapter)
        ->toContain('public const SEARCH_DEBOUNCE_MS = 300;')
        ->toContain('TextInput::configureUsing')
        ->toContain('Select::configureUsing')
        ->toContain('Textarea::configureUsing')
        ->toContain('Checkbox::configureUsing')
        ->toContain('Toggle::configureUsing');

    expect($selectCss)
        ->toContain(".admin-control-field .admin-select {\n    width: 100%;\n    min-width: 0;\n    flex: 1 1 100%;")
        ->toContain('.admin-control-field .fi-input-wrp:has(.admin-select)')
        ->toContain('border-bottom: 0 !important;')
        ->toContain(".admin-select__option {\n    position: relative;")
        ->toContain('text-overflow: ellipsis;')
        ->toContain('white-space: nowrap;');
});


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
