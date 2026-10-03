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
        ->toContain('<x-admin.search-input')
        ->toContain('model="search"')
        ->toContain('wire:model.live="areaFilter"')
        ->toContain('wire:model.live="familyFilter"')
        ->toContain('wire:model.live="dateFilter"')
        ->toContain('wire:model.live="hourFilter"')
        ->not->toContain('x-on:input.debounce')
        ->not->toContain('$el.form.requestSubmit()');

    expect($activityPage)
        ->toContain("#[Url(as: 'search', except: '')]")
        ->toContain("#[Url(as: 'area', except: '')]")
        ->toContain("#[Url(as: 'family', except: '')]")
        ->toContain("#[Url(as: 'calendar_date', except: '')]")
        ->toContain("#[Url(as: 'hour', except: '')]")
        ->toContain('public function updatedSearch(): void')
        ->toContain('private function refreshActivityFilters(): void');
});
