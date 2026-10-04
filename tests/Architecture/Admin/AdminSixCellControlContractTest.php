<?php

it('keeps metric toolbars scoped without forcing dense toolbars into six cells', function (): void {
    $root = dirname(__DIR__, 3);
    $component = file_get_contents($root.'/resources/views/components/admin/controls.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/six-cell-contract.css');
    $tableCss = file_get_contents($root.'/resources/css/admin/table-contract.css');
    $dashboard = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed.blade.php');
    $dashboardRow = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed-row.blade.php');
    $dashboardCss = file_get_contents($root.'/resources/css/admin/dashboard-feed.css');
    $dashboardLayoutCss = file_get_contents($root.'/resources/css/admin/dashboard.css');
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php');
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');
    $vite = file_get_contents($root.'/vite.config.js');

    expect($component)
        ->toContain("'admin-data-controls--filters-'.\$normalizedFilterCount")
        ->toContain("'admin-data-controls--six-cell' => \$metricGrid")
        ->toContain("'admin-data-controls--six-cell-filters-'.\$normalizedFilterCount => \$metricGrid")
        ->toContain("'admin-data-controls--six-cell-search-'.\$normalizedSearchSpan => \$metricGrid && \$normalizedSearchSpan !== null")
        ->not->toContain('admin-data-controls__filter-trigger')
        ->not->toContain('adminFiltersOpen')
        ->toContain('@if ($hasUtility)')
        ->toContain('class="admin-data-controls__utility"')
        ->not->toContain('$hasCompleteDataToolbar')
        ->not->toContain('$usesMetricGrid');

    expect($css)
        ->toContain('grid-template-columns: repeat(6, minmax(0, 1fr));')
        ->toContain('grid-template-rows: auto;')
        ->toContain('grid-column: 5 / span 2;')
        ->toContain('grid-row: 1;')
        ->toContain('.custom-page-workspace__controls')
        ->toContain('.journal-workspace__entries > .admin-data-controls')
        ->toContain(".admin-data-controls__utility {\n    display: contents;")
        ->toContain('grid-template-columns: 4rem minmax(0, 1fr) 8.5rem;')
        ->toContain('.admin-data-controls--six-cell > .admin-data-controls__utility {')
        ->toContain('grid-template-columns: repeat(4, minmax(0, 1fr));')
        ->toContain('grid-column: 3 / span 2;')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('.admin-selection__trigger-text')
        ->not->toContain(".admin-data-controls__utility .admin-data-control-label,\n    .admin-data-controls--six-cell > .admin-data-controls__utility .admin-action__label")
        ->toContain('width: min(100%, var(--admin-control-height));')
        ->toContain('max-width: 100%;')
        ->toContain('@container admin-workspace (max-width: 66rem)')
        ->toContain('grid-column: 4 / span 3;')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr));')
        ->toContain('grid-column: 2 / span 2;')
        ->toContain('justify-self: end;')
        ->toContain('padding-right: .25rem;')
        ->toContain(".admin-data-controls__utility .admin-action {\n    white-space: nowrap;")
        ->toContain('var(--admin-table-selection-width)')
        ->toContain('justify-self: center;');

    expect($tableCss)
        ->toContain('.admin-task-controls--pages')
        ->toContain('grid-template-columns: repeat(6, minmax(0, 1fr));');

    expect($dashboard)
        ->toContain('admin-dashboard__feed-controls')
        ->toContain('metric-grid')
        ->toContain(':filter-count="1"')
        ->toContain(':search-span="3"')
        ->toContain('admin-table__col-three-quarter-unit admin-dashboard__col-type')
        ->toContain('admin-table__col-three-quarter-unit admin-dashboard__col-date')
        ->toContain('admin-table__col-half-unit admin-dashboard__col-time')
        ->toContain('admin-table__col-two-units-minus-selection admin-dashboard__col-actions')
        ->toContain('>Time</th>');

    expect($dashboardRow)
        ->toContain("$item['time_display']")
        ->not->toContain(" · ");

    expect($dashboardCss)
        ->not->toContain('.admin-dashboard__feed-controls')
        ->not->toContain('col:nth-last-child')
        ->toContain('grid-template-columns: repeat(4, minmax(0, 1fr));')
        ->toContain('/* Dashboard feed responsive table authority.')
        ->toContain('@container admin-table (max-width: 62rem)')
        ->toContain('.admin-dashboard__feed-actions .admin-action')
        ->toContain('col.admin-dashboard__col-actions')
        ->toContain('calc(var(--admin-table-one-unit) - var(--admin-table-selection-width))')
        ->toContain('@container admin-table (max-width: 50rem)')
        ->toContain('@container admin-table (max-width: 30rem)');

    expect($dashboardLayoutCss)
        ->toContain('/* Dashboard layout owner.')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('/* Burger-shell Narrow fallback.')
        ->toContain("@media (max-width: 63.99rem) {\n    @container admin-workspace (max-width: 38rem) {")
        ->toContain('gap: .25rem;')
        ->toContain('padding: .25rem 0;');

    expect($responsive)
        ->toContain('/* Canonical metric leading-edge invariant.')
        ->toContain('.general-metric--public-email')
        ->toContain('.pages-metric--published')
        ->toContain('.storage-metric--used')
        ->not->toContain('.admin-dashboard')
        ->not->toContain('Dashboard responsive authority')
        ->not->toContain('Dashboard stage footer authority')
        ->not->toContain('.admin-dashboard__feed-controls')
        ->not->toContain('.admin-dashboard__feed-table')
        ->not->toContain('.admin-dashboard__col-')
        ->not->toContain('admin-data-controls__filter-trigger')
        ->not->toContain('adminFiltersOpen')
        ->not->toContain("grid-template-columns: 4rem minmax(0, 1fr) 8.5rem;")
        ->not->toContain('Feature-specific focused Visual Stages. No desktop pane is generically')
        ->not->toContain('Viewport fallback for the Filament burger shell. It also owns semantic');

    expect($storage)
        ->toContain('media-workspace__controls')
        ->not->toContain('metric-grid');

    foreach ([$theme, $vite] as $loader) {
        expect($loader)
            ->toContain('resources/css/admin/six-cell-contract.css')
            ->not->toContain('dashboard-extras.css');
    }
});
