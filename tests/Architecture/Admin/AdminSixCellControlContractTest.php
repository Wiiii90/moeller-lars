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
        ->toContain('grid-template-columns: repeat(24, minmax(0, 1fr));')
        ->toContain('Narrow  >38rem : Search  7 | Type 5 | Clear 4 | Gap 2 | Dashboard 2 | Selection 4')
        ->toContain('@container admin-workspace (max-width: 66rem)')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('grid-column: 13 / span 12;')
        ->toContain('grid-column: 7 / span 5;')
        ->toContain('grid-column: 7 / span 6;')
        ->toContain('grid-template-columns: minmax(0, 1fr) var(--admin-table-selection-width);')
        ->toContain('> :nth-child(2) .admin-action__label')
        ->toContain('> :last-child .admin-selection__trigger-text')
        ->not->toContain('Search 1 | Type 2 | Utility 3');

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
        ->toContain('.admin-dashboard__feed-actions .admin-action__label')
        ->toContain('@container admin-table (max-width: 50rem)')
        ->toContain('@container admin-table (max-width: 44rem)')
        ->toContain('width: calc(100% - 12.5rem) !important;')
        ->toContain('.admin-dashboard__feed-meta');

    expect($dashboardLayoutCss)
        ->toContain('/* Dashboard layout owner.')
        ->toContain('--admin-metric-columns: 6 !important;')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('--admin-metric-columns: 3 !important;')
        ->toContain("@media (max-width: 63.99rem) {\n    @container admin-workspace (max-width: 38rem) {")
        ->toContain('--admin-metric-columns: 2 !important;')
        ->toContain('gap: .25rem;')
        ->toContain('padding: .25rem 0;');

    expect($responsive)
        ->not->toContain('.admin-pager')
        ->toContain('/* Canonical metric leading-edge invariant.')
        ->toContain('.general-metric--public-email')
        ->toContain('.pages-metric--published')
        ->toContain('.storage-metric--used')
        ->toContain('.admin-workspace:not(.admin-dashboard) .admin-metrics:has')
        ->not->toContain('.admin-dashboard .admin-metrics')
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
