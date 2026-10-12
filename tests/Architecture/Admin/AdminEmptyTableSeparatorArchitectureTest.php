<?php

it('assigns table-empty separators to canonical table and add-row boundaries', function (): void {
    $styles = file_get_contents(resource_path('css/admin/data-workspace.css'));
    $base = file_get_contents(resource_path('css/admin/primitives.css'));
    $custom = file_get_contents(resource_path('css/admin/custom-page.css'));

    // Standalone empty states retain their own block boundaries. In a table,
    // the header and subsequent Add row/Pager already own those separators.
    expect($base)->toContain('border-block: 1px solid var(--admin-line);')
        ->and($styles)->toContain(".admin-table .admin-empty-state {\n    border: 0;\n}")
        ->toContain(".admin-empty-state + .admin-add-row {\n    border-top: 0;\n}")
        ->toContain(".admin-add-row + .admin-pager {\n    border-top: 0;\n}")
        ->and($custom)->not->toContain('.admin-empty-state');
});

it('uses shared table empty states for Custom Page and other editorial tables', function (): void {
    $relativePaths = [
        'filament/pages/partials/custom-page-workspace-sequence.blade.php',
        'filament/pages/site-pages.blade.php',
        'filament/pages/partials/dashboard-feed.blade.php',
        'filament/pages/activity.blade.php',
        'filament/pages/home-presentation.blade.php',
        'filament/pages/journal-workspace.blade.php',
        'filament/pages/analytics.blade.php',
        'filament/schemas/components/general-social-links.blade.php',
    ];

    foreach ($relativePaths as $path) {
        $view = file_get_contents(resource_path('views/'.$path));

        expect($view)->toContain('<x-admin.empty-state');
    }

    $custom = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $footer = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-footer.blade.php'));
    $storage = file_get_contents(resource_path('views/filament/resources/media-assets/partials/storage-library.blade.php'));

    expect($custom)->toContain('<x-admin.table>')
        ->toContain('custom-page-component-sequence admin-hierarchy')
        ->toContain('title="No components"')
        ->toContain('title="No matching components"')
        ->and($footer)->toContain('<x-admin.add-row')
        ->and($storage)->toContain('<x-admin.empty-state')
        ->toContain('<x-admin.add-row');
});
