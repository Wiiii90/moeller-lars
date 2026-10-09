<?php

it('keeps the shared Activity clock live and canonical behind open dialogs', function (): void {
    $root = dirname(__DIR__, 3);
    $clock = file_get_contents($root.'/resources/views/components/admin/activity-clock-visual.blade.php');
    $details = file_get_contents($root.'/resources/views/filament/pages/partials/activity-details-dialog.blade.php');
    $activityView = file_get_contents($root.'/resources/views/filament/pages/activity.blade.php');
    $activityPage = file_get_contents($root.'/app/Filament/Pages/Activity.php');
    $dashboardOverview = file_get_contents($root.'/app/Filament/Support/DashboardOverview.php');
    $projection = file_get_contents($root.'/app/Filament/Support/ActivityClockProjection.php');
    $dashboardView = file_get_contents($root.'/resources/views/filament/pages/dashboard.blade.php');
    $interactions = file_get_contents($root.'/resources/css/admin/dialog-interactions.css');

    expect($clock)
        ->toContain('window.setInterval')
        ->toContain('this.now = new Date()')
        ->not->toContain("document.querySelector('.fi-modal.fi-modal-open')")
        ->not->toContain("document.activeElement?.closest?.('.activity-workspace__controls')");

    expect($activityPage)
        ->toContain('ActivityClockProjection::class')
        ->toContain("['activity']")
        ->toContain("['peak_count']")
        ->toContain("['peak_hour']");

    expect($dashboardOverview)
        ->toContain('ActivityClockProjection::class')
        ->toContain("['activity']")
        ->toContain("['peak_count']")
        ->toContain("['peak_hour']")
        ->not->toContain('$clockActivity = []');

    expect($projection)
        ->toContain('final class ActivityClockProjection')
        ->toContain('array_fill(0, 24, 0)')
        ->toContain("'peak_hour' => $peakHour")
        ->toContain("'peak_count' => $peakCount");

    expect($dashboardView)
        ->toContain('<x-admin.activity-clock-visual');

    expect($details)
        ->toContain('admin-detail-dialog--activity')
        ->toContain("{{ \$event['timestamp'] }}")
        ->not->toContain('<dt>Date</dt>')
        ->not->toContain('Open record');

    expect($interactions)
        ->not->toContain('.admin-detail-dialog--activity')
        ->not->toContain('> a.fi-icon-btn');

    expect($activityPage)
        ->not->toContain("Action::make('openActivityRecord')")
        ->not->toContain("->label('Open record')");

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
