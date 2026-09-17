<?php

it('keeps metric toolbars scoped without forcing dense toolbars into six cells', function (): void {
    $root = dirname(__DIR__, 3);
    $component = file_get_contents($root.'/resources/views/components/admin/controls.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/six-cell-contract.css');
    $tableCss = file_get_contents($root.'/resources/css/admin/table-contract.css');
    $dashboard = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed.blade.php');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php');
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');
    $vite = file_get_contents($root.'/vite.config.js');

    expect($component)
        ->toContain("'admin-data-controls--filters-'.\$normalizedFilterCount")
        ->toContain("'admin-data-controls--six-cell' => \$metricGrid")
        ->toContain("'admin-data-controls--six-cell-filters-'.\$normalizedFilterCount => \$metricGrid")
        ->toContain('@if ($hasUtility)')
        ->toContain('class="admin-data-controls__utility"')
        ->not->toContain('$hasCompleteDataToolbar')
        ->not->toContain('$usesMetricGrid');

    expect($css)
        ->toContain('grid-template-columns: repeat(6, minmax(0, 1fr));')
        ->toContain('.custom-page-workspace__controls')
        ->toContain('.journal-workspace__entries > .admin-data-controls')
        ->toContain(".admin-data-controls__utility {\n    display: flex;")
        ->toContain('grid-template-columns: max-content minmax(max-content, 1fr) minmax(9.5rem, auto);')
        ->toContain(".admin-data-controls__utility .admin-action {\n    white-space: nowrap;")
        ->toContain('var(--admin-table-selection-width)')
        ->toContain('justify-self: center;');

    expect($tableCss)
        ->toContain('.admin-task-controls--pages')
        ->toContain('grid-template-columns: repeat(6, minmax(0, 1fr));');

    expect($dashboard)
        ->toContain('admin-dashboard__feed-controls')
        ->not->toContain('metric-grid');

    expect($storage)
        ->toContain('media-workspace__controls')
        ->not->toContain('metric-grid');

    foreach ([$theme, $vite] as $loader) {
        expect($loader)->toContain('resources/css/admin/six-cell-contract.css');
    }
});
