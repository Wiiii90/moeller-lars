<?php

it('keeps Activity details stable while the live clock is behind an open dialog', function (): void {
    $root = dirname(__DIR__, 3);
    $clock = file_get_contents($root.'/resources/views/components/admin/activity-clock-visual.blade.php');
    $details = file_get_contents($root.'/resources/views/filament/pages/partials/activity-details-dialog.blade.php');
    $activityView = file_get_contents($root.'/resources/views/filament/pages/activity.blade.php');
    $activityPage = file_get_contents($root.'/app/Filament/Pages/Activity.php');
    $interactions = file_get_contents($root.'/resources/css/admin/dialog-interactions.css');

    expect($clock)
        ->toContain("document.querySelector('.fi-modal.fi-modal-open')");

    expect($details)
        ->toContain('admin-detail-dialog--activity')
        ->toContain("{{ \$event['timestamp'] }}")
        ->not->toContain('<dt>Date</dt>')
        ->not->toContain('Open record');

    expect($interactions)
        ->toContain('.admin-detail-dialog--activity')
        ->toContain('> a.fi-icon-btn');

    expect($activityView)
        ->toContain('wire:model="search"')
        ->toContain('wire:change="applySearch"')
        ->toContain('wire:keydown.enter.prevent="applySearch"')
        ->not->toContain('x-on:input.debounce')
        ->not->toContain('$el.form.requestSubmit()');

    expect($activityPage)
        ->toContain("#[Url(as: 'search', except: '')]")
        ->toContain('public function applySearch(): void');
});
