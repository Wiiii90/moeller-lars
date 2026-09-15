<?php

it('keeps the Activity workspace stable while only the table mode switches', function (): void {
    $page = file_get_contents(app_path('Filament/Pages/Activity.php'));
    $view = file_get_contents(resource_path('views/filament/pages/activity.blade.php'));

    expect($page)
        ->toContain("#[Url(as: 'view', except: self::VIEW_ACTIVITY, history: true)]")
        ->toContain('public function setViewMode(string $viewMode): void')
        ->toContain('private function buildWorkspaceSnapshot(): array')
        ->toContain("['label' => 'Changes'")
        ->toContain("['label' => 'Commits'")
        ->toContain("['label' => 'Committed changes'")
        ->toContain("['label' => 'Pending'")
        ->toContain("'timelineItemLabel' => 'changes'");

    expect($view)
        ->toContain('wire:click="setViewMode(\'activity\')"')
        ->toContain('wire:click="setViewMode(\'commits\')"')
        ->toContain('wire:target="setViewMode"')
        ->toContain('aria-label="Activity statistics"')
        ->toContain('aria-label="Activity timeline"')
        ->not->toContain('$viewUrls = [')
        ->not->toContain('admin-view-switch__item');
});
