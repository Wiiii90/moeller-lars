<?php

it('uses Filament built-in sidebar hydration rather than a second navigation runtime', function (): void {
    $pages = file_get_contents(app_path('Filament/Pages/SitePages.php'));
    $sidebarNavigation = file_get_contents(app_path('Filament/Support/SiteNavigation.php'));
    $publicNavigation = file_get_contents(app_path('Domain/Content/PublicNavigationService.php'));
    $home = file_get_contents(app_path('Filament/Pages/Concerns/ManagesHomePagePresentation.php'));

    expect($pages)
        ->toContain('private function loadSections(bool $navigationChanged = false): void')
        ->toContain("if ($navigationChanged) {")
        ->toContain("\$this->dispatch('refresh-sidebar');")
        ->toContain('navigationChanged: $changed > 0')
        ->toContain('navigationChanged: $deleted > 0');

    expect(substr_count($pages, "\$this->dispatch('refresh-sidebar');"))->toBe(1);

    expect($sidebarNavigation)
        ->not->toContain("->where('state', 'published')")
        ->not->toContain("->where('show_in_navigation', true)")
        ->toContain('$this->home->skipEnabled()')
        ->toContain("->orderBy('position')")
        ->not->toContain('wire:poll');

    expect($publicNavigation)
        ->toContain("->where('show_in_navigation', true)")
        ->toContain("->where('state', 'published')")
        ->toContain('$this->home->skipEnabled()')
        ->toContain("->where('type', '<>', SiteSectionType::Home->value)");

    expect($home)
        ->toContain('navigationChanged: $navigationChanged')
        ->not->toContain('Livewire.navigate')
        ->not->toContain('window.location.reload');
});
