<?php

it('uses one direct Pages Skip Home toggle and no retired Skip Home modal', function (): void {
    $trait = file_get_contents(app_path('Filament/Pages/Concerns/ManagesHomePagePresentation.php'));
    $row = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $settings = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));

    expect($trait)
        ->toContain('public function toggleSkipHome(): void')
        ->toContain('$routing->update($settings, $enable, null)')
        ->toContain('if ($enable && ! $resolver->skipTarget() instanceof SiteSection)')
        ->toContain('$this->loadSections();')
        ->not->toContain('function skipHomeAction')
        ->not->toContain("Action::make('skipHome')")
        ->not->toContain('HomeRoutingDialog::class')
        ->not->toContain('AdminDialog::command(');

    expect($row)
        ->toContain('wire:click="toggleSkipHome"')
        ->toContain("($homeState['skip_home'] ?? false) ? 'Show Home' : 'Skip Home'")
        ->toContain('Change Skip Home target in Home settings')
        ->not->toContain("mountAction('skipHome')");

    // Route details have one existing editor, not a second modal implementation.
    expect($settings)
        ->toContain('$this->routingDialog->schema()')
        ->toContain('$this->routingDialog->save($data)');
});
