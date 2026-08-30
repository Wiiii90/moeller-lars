<?php

it('binds General color controls to the shared flat ColorPicker chrome without changing persistence', function (): void {
    $colorSource = file_get_contents(app_path('Filament/Support/AdminColorControl.php'));
    $generalSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $formsCss = file_get_contents(resource_path('css/admin/forms.css'));

    expect($colorSource)
        ->toContain('ColorPicker::make($name)')
        ->toContain("->extraAttributes(['class' => 'admin-color-control'])")
        ->not->toContain('Alpine')
        ->not->toContain('JavaScript')
        ->not->toContain('Pickr')
        ->and($generalSource)
        ->toContain("AdminColorControl::make('background_primary_color', 'Primary color')")
        ->toContain("AdminColorControl::make('background_secondary_color', 'Secondary color')")
        ->toContain("->extraFieldWrapperAttributes(['class' => 'general-color-control'])")
        ->toContain("\$livewire->persistAppearanceColor('primary', \$state);")
        ->toContain("\$livewire->persistAppearanceColor('secondary', \$state);")
        ->toContain("TextInput::make('background_gradient_angle')")
        ->toContain("->suffix('°')")
        ->toContain("Select::make('background_mode')")
        ->and($formsCss)
        ->toContain('.admin-color-control.fi-fo-color-picker {')
        ->toContain('.admin-color-control.fi-fo-color-picker .fi-input-wrp-content')
        ->toContain('min-height: calc(var(--admin-control-height) - 1px);')
        ->toContain('.admin-color-control.fi-fo-color-picker .fi-input {')
        ->toContain('.admin-color-control.fi-fo-color-picker .fi-fo-color-picker-preview {')
        ->toContain('border-radius: 0 !important;')
        ->toContain('.admin-color-control.fi-fo-color-picker .fi-fo-color-picker-panel,')
        ->toContain('.general-color-control .fi-fo-color-picker-panel')
        ->toContain('border: 1px solid var(--admin-line-strong) !important;')
        ->toContain('background: var(--admin-surface) !important;')
        ->toContain('.admin-form-controls .fi-input-wrp:focus-within')
        ->toContain('border-bottom-color: var(--admin-accent) !important;');
});
