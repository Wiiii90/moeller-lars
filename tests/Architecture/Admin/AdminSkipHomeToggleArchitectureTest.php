<?php

it('uses one direct Pages Skip Home toggle and no retired Skip Home modal', function (): void {
    $trait = file_get_contents(app_path('Filament/Pages/Concerns/ManagesHomePagePresentation.php'));
    $row = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $settings = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));
    $routing = file_get_contents(app_path('Filament/Support/HomeRoutingDialog.php'));

    expect($trait)
        ->toContain('public function toggleSkipHome(): void')
        ->toContain('$routing->update($settings, $enable, null)')
        ->toContain('if ($enable && ! $resolver->skipTarget() instanceof SiteSection)')
        ->toContain('public function changeSkipHomeTarget(string $target): void')
        ->toContain('$routing->update($settings, true, $targetId)')
        ->toContain('$this->loadSections();')
        ->not->toContain('function skipHomeAction')
        ->not->toContain("Action::make('skipHome')")
        ->not->toContain('HomeRoutingDialog::class')
        ->not->toContain('AdminDialog::command(');

    expect($routing)
        ->toContain('public function targetOptions(): array')
        ->toContain("'skip_target_options' => $this->targetOptions()");

    expect($row)
        ->toContain('wire:click="toggleSkipHome"')
        ->toContain("($homeState['skip_home'] ?? false) ? \\App\\Filament\\Support\\AdminRowAction::ShowHome : \\App\\Filament\\Support\\AdminRowAction::SkipHome")
        ->toContain('wire:click="changeSkipHomeTarget(\'\')"')
        ->toContain('Skip Home destination:')
        ->toContain('AdminRowAction::ShowHome')
        ->not->toContain('mountAction(\'editHome\')')
        ->not->toContain("mountAction('skipHome')");

    // Route details have one existing editor, not a second modal implementation.
    expect($settings)
        ->toContain('$this->routingDialog->schema()')
        ->toContain('$this->routingDialog->save($data)');
});
