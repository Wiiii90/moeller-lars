<?php

it('keeps admin table pagination on the shared pager primitive', function (): void {
    $root = dirname(__DIR__, 3);
    $views = [
        file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed.blade.php'),
        file_get_contents($root.'/resources/views/filament/pages/analytics.blade.php'),
        file_get_contents($root.'/resources/views/filament/pages/activity.blade.php'),
        file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php'),
    ];

    foreach ($views as $view) {
        expect($view)->toContain('<x-admin.pager');
    }

    expect($views[2])
        ->not->toContain('admin-pager__leading')
        ->not->toContain('admin-pager__meta');

    expect($views[3])->not->toContain('media-workspace__pager');

    $picker = file_get_contents($root.'/resources/views/components/admin/page-size-picker.blade.php');
    $pager = file_get_contents($root.'/resources/views/components/admin/pager.blade.php');

    expect($picker)
        ->not->toContain('urls')
        ->not->toContain('Alpine.navigate')
        ->not->toContain('window.location.assign')
        ->and($pager)
        ->not->toContain('pageSizeUrls')
        ->not->toContain('previousUrl')
        ->not->toContain('nextUrl')
        ->not->toContain('wire:navigate')
        ->and($views[2])
        ->toContain('page-size-wire-model="perPage"')
        ->toContain('previous-wire-action="previousActivityPage"')
        ->toContain('next-wire-action="nextActivityPage"')
        ->toContain('previous-wire-action="previousCommitPage"')
        ->toContain('next-wire-action="nextCommitPage"');
});
