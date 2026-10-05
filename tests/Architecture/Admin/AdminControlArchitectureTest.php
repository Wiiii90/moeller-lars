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
