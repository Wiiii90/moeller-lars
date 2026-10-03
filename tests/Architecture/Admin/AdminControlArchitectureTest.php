<?php

it('registers the shared admin control adapter panel wide', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $adapter = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');
    $selectCss = file_get_contents($root.'/resources/css/admin/selects.css');

    expect($provider)->toContain('AdminControl::register();');

    expect($adapter)
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
