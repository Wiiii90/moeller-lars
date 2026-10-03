<?php

it('keeps admin responsiveness container-driven and table overflow-free', function (): void {
    $root = dirname(__DIR__, 3);
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $flow = file_get_contents($root.'/resources/css/admin/table-flow.css');
    $controls = file_get_contents($root.'/resources/views/components/admin/controls.blade.php');

    expect($theme)
        ->toContain('resources/css/admin/responsive.css')
        ->and(strpos($theme, 'resources/css/admin/responsive.css'))
        ->toBeGreaterThan(strpos($theme, 'resources/css/admin/table-flow.css'));

    expect($responsive)
        ->toContain('container-name: admin-workspace')
        ->toContain('container-name: admin-table')
        ->toContain('@container admin-workspace (min-width: 54.01rem) and (max-width: 68rem)')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('@container admin-workspace (max-width: 30rem)')
        ->toContain('@container admin-table (max-width: 62rem)')
        ->toContain('@container admin-table (max-width: 50rem)')
        ->toContain('@container admin-table (max-width: 38rem)')
        ->toContain('overflow-x: clip !important')
        ->toContain('.general-appearance-stage__preview')
        ->toContain('.admin-storage__distribution')
        ->toContain('.admin-dashboard__overview-column:nth-child(n + 2)')
        ->toContain('.custom-page-component-sequence .admin-responsive-meta')
        ->not->toContain('admin-visual-stage--stackable');

    expect($flow)
        ->toContain('Ordinary admin tables do not become horizontal scrollports')
        ->toContain('overflow-x: clip !important')
        ->not->toContain('overflow-x: auto');

    expect($controls)
        ->toContain('admin-data-controls__utility--has-reset')
        ->toContain('admin-data-controls__utility--has-actions')
        ->toContain('admin-data-controls__utility--has-selection')
        ->toContain('admin-data-controls__filter-trigger')
        ->toContain('admin-data-controls__filters');
});

it('keeps selection terminal while responsive tables remove supportive data first', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $dashboard = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed.blade.php');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php');
    $home = file_get_contents($root.'/resources/views/filament/pages/home-presentation.blade.php');

    expect($dashboard)
        ->toContain('admin-table__selection admin-table__selection--trailing')
        ->toContain('admin-dashboard__col-sender')
        ->toContain('admin-dashboard__col-type')
        ->toContain('admin-dashboard__col-date');

    expect($storage)
        ->toContain('media-workspace__selection-head--trailing')
        ->toContain('media-workspace__selection-cell--trailing')
        ->toContain('media-workspace__usage-cell')
        ->toContain('admin-responsive-meta');

    expect($home)
        ->toContain('home-source-table__candidates')
        ->toContain('home-components-table__type')
        ->toContain('admin-responsive-meta');

    expect($responsive)
        ->toContain('.admin-dashboard__feed-table .admin-dashboard__col-sender')
        ->toContain('.media-workspace__table-wrap .media-workspace__usage-cell')
        ->toContain('.home-source-table .home-source-table__candidates')
        ->toContain('.general-social-table .general-social-table__url');
});
