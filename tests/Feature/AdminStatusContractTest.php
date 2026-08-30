<?php

it('keeps workspace health status separate from shared row entity status', function (): void {
    $component = file_get_contents(resource_path('views/components/admin/status.blade.php'));
    $adminCss = file_get_contents(resource_path('css/admin.css'));
    $taskCss = file_get_contents(resource_path('css/admin/task-surfaces.css'));
    $analytics = file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $storage = file_get_contents(resource_path('views/filament/pages/storage-capacity.blade.php'));
    $storageCss = file_get_contents(resource_path('css/admin/storage.css'));

    expect($component)
        ->toContain('admin-workspace-status')
        ->not->toContain("'admin-status'")
        ->and($adminCss)
        ->toMatch('/\.admin-workspace-status\s*\{/')
        ->not->toMatch('/(?:^|\n)\.admin-status\s*\{/')
        ->and($taskCss)
        ->toMatch('/\.admin-status\s*\{/')
        ->toContain('.admin-status.is-published::before')
        ->and($analytics)
        ->toContain('<x-admin.status :tone="$workspaceStatusTone">')
        ->not->toContain('analytics-status')
        ->and($storage)
        ->toContain("'admin-status'")
        ->not->toContain('admin-storage__state')
        ->and($storageCss)
        ->toContain('.admin-storage .admin-status.is-referenced::before')
        ->toContain('.admin-storage .admin-status.is-unreferenced::before')
        ->toContain('.admin-storage .admin-status.is-uncatalogued::before')
        ->not->toContain('.admin-storage__state');
});
