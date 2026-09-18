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
});

it('keeps the shared page size picker opening downward', function (): void {
    $root = dirname(__DIR__, 3);
    $picker = file_get_contents($root.'/resources/views/components/admin/page-size-picker.blade.php');
    $styles = file_get_contents($root.'/resources/css/admin/data-workspace.css');

    expect($picker)
        ->toContain('▾')
        ->toContain("scrollIntoView({ block: 'nearest' })")
        ->not->toContain('▴');

    expect($styles)
        ->toContain('top: calc(100% + .3rem);')
        ->toContain('.admin-pager:has(.admin-pager-size-picker.is-open)')
        ->not->toContain('.media-workspace__pager');
});
