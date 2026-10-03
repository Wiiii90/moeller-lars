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
        ->toContain('Toolbar is always exactly one row')
        ->not->toContain('toolbar is exactly two semantic rows')
        ->not->toContain('Row 2 = Task actions')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr)) !important')
        ->toContain('@container admin-table (max-width: 62rem)')
        ->toContain('@container admin-table (max-width: 50rem)')
        ->toContain('@container admin-table (max-width: 38rem)')
        ->toContain('overflow-x: clip !important')
        ->toContain('.general-appearance-stage__preview')
        ->toContain('.admin-storage__distribution')
        ->toContain('.admin-dashboard__overview-switcher')
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
    $dashboardCss = file_get_contents($root.'/resources/css/admin/dashboard-feed.css');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php');
    $home = file_get_contents($root.'/resources/views/filament/pages/home-presentation.blade.php');

    expect($dashboardCss)
        ->toContain('Dashboard row actions have two states only')
        ->toContain('.admin-dashboard__feed-actions .admin-action__label')
        ->toContain('text-overflow: clip')
        ->toContain('Compact row-action rail');

    expect($dashboard)
        ->toContain('admin-table__selection admin-table__selection--trailing')
        ->toContain('admin-dashboard__col-position')
        ->toContain('admin-dashboard__col-drag')
        ->toContain('admin-dashboard__col-sender')
        ->toContain('admin-dashboard__col-type')
        ->toContain('admin-dashboard__col-date');

    $dashboardPage = file_get_contents($root.'/resources/views/filament/pages/dashboard.blade.php');

    expect($dashboardPage)
        ->toContain('admin-dashboard__metric--{{ $metricRole }}')
        ->toContain("'visits' => 'traffic'")
        ->toContain("'published artworks' => 'artworks'")
        ->toContain("'recent changes' => 'changes'");

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
        ->toContain('.admin-dashboard__feed-controls')
        ->toContain('.admin-dashboard .admin-dashboard__metric--changes')
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr)) !important')
        ->toContain('.media-workspace__table-wrap .media-workspace__usage-cell')
        ->toContain('.home-source-table .home-source-table__candidates')
        ->toContain('.general-social-table .general-social-table__url');
});


it('keeps Dashboard responsive state monotonic across sidebar collapse', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $row = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed-row.blade.php');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');

    expect($responsive)
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('.admin-dashboard__overview-switcher')
        ->toContain('.admin-dashboard__metric--changes')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr)) !important');

    expect($row)
        ->not->toContain('admin-dashboard__action-placeholder');

    expect($layouts)
        ->toContain('.fi-main:has(.admin-workspace)')
        ->toContain('.fi-page:has(.admin-workspace)')
        ->toContain('padding-top: var(--admin-shell-content-inset) !important');
});


it('aligns the Dashboard action heading with the row action rail', function (): void {
    $root = dirname(__DIR__, 3);
    $feedCss = file_get_contents($root.'/resources/css/admin/dashboard-feed.css');
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($feedCss)
        ->not->toContain('.admin-dashboard__feed-table thead .admin-table__actions');

    expect($responsive)
        ->toContain('Dashboard responsive authority')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr)) !important')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr)) !important')
        ->toContain('grid-column: 1 / -1 !important')
        ->toContain('border: 0 !important');
});


it('aligns the wide Dashboard feed and toolbar to the six-cell metric grid', function (): void {
    $root = dirname(__DIR__, 3);
    $feed = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed.blade.php');
    $feedCss = file_get_contents($root.'/resources/css/admin/dashboard-feed.css');
    $tableContract = file_get_contents($root.'/resources/css/admin/table-contract.css');

    expect($feed)
        ->toContain('admin-table__col-half-unit admin-dashboard__col-type')
        ->toContain('admin-table__col-one-unit admin-dashboard__col-date')
        ->toContain('admin-table__col-two-units admin-dashboard__col-title')
        ->toContain('admin-table__col-one-unit admin-dashboard__col-sender')
        ->toContain('admin-table__col-one-unit-minus-selection');

    expect($feedCss)
        ->toContain('minmax(16.666667%, 1fr)')
        ->toContain('max-content')
        ->toContain('justify-content: flex-start !important');

    expect($tableContract)
        ->toContain('.admin-table__col-one-unit-minus-selection');
});


it('keeps all three Dashboard stage panes through Narrow and switches only at Minimal', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($responsive)
        ->toContain('Narrow:        three metrics + three simultaneous overview panes.')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('.admin-dashboard__overview-column.is-compact-active');
});


it('preserves Dashboard stage captions and Type Date through Minimal', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $dashboardCss = file_get_contents($root.'/resources/css/admin/dashboard.css');
    $stage = file_get_contents($root.'/resources/css/admin/stage.css');

    expect($dashboardCss)
        ->toContain('flex-wrap: nowrap')
        ->toContain('admin-dashboard__fact-label--short')
        ->toContain('white-space: nowrap');

    expect($stage)
        ->not->toContain('--admin-dashboard-stage-height')
        ->toContain('height: var(--admin-visual-stage-height)')
        ->not->toContain('admin-dashboard__overview-column + .admin-dashboard__overview-column {\n    padding-left: 1rem;\n    border-left');

    expect($responsive)
        ->toContain('Dashboard feed column priority authority')
        ->toContain('display: table-cell !important')
        ->toContain('col.admin-dashboard__col-type')
        ->toContain('col.admin-dashboard__col-date');
});


it('keeps the shared Visual Stage height fixed across responsive widths', function (): void {
    $root = dirname(__DIR__, 3);
    $admin = file_get_contents($root.'/resources/css/admin.css');
    $stage = file_get_contents($root.'/resources/css/admin/stage.css');
    $dashboard = file_get_contents($root.'/resources/css/admin/dashboard.css');

    expect($admin)
        ->toContain('--admin-visual-stage-height: 23.5rem;')
        ->not->toContain('--admin-visual-stage-height: min(');

    expect($stage)
        ->toContain('height: var(--admin-visual-stage-height)')
        ->not->toContain('--admin-dashboard-stage-height');

    expect($dashboard)
        ->toContain('.admin-dashboard__storage-stage > .admin-dashboard__facts')
        ->toContain('width: min(100%, var(--admin-stage-orbit-size))')
        ->toContain('height: var(--admin-stage-orbit-caption-reserve)')
        ->toContain('justify-content: center')
        ->toContain('text-align: center');
});


it('uses the shared orbit size for Dashboard Storage and Activity', function (): void {
    $root = dirname(__DIR__, 3);
    $stage = file_get_contents($root.'/resources/css/admin/stage.css');
    $dashboard = file_get_contents($root.'/resources/views/filament/pages/dashboard.blade.php');

    expect($stage)
        ->toContain('--admin-stage-orbit-size: clamp(12rem, 20vw, 17.5rem)')
        ->not->toContain('--admin-stage-orbit-size: clamp(10rem, 20cqw, 17.5rem)')
        ->toContain('.admin-dashboard__storage-stage')
        ->toContain('grid-template-rows: minmax(0, 1fr) var(--admin-stage-orbit-caption-reserve)');

    expect($dashboard)
        ->toContain('class="admin-dashboard__storage-stage"');
});


it('keeps General responsive state monotonic and its social table aligned', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $generalCss = file_get_contents($root.'/resources/css/admin/general.css');
    $metrics = file_get_contents($root.'/resources/views/filament/schemas/components/general-status-metrics.blade.php');
    $social = file_get_contents($root.'/resources/views/filament/schemas/components/general-social-links.blade.php');
    $tableContract = file_get_contents($root.'/resources/css/admin/table-contract.css');

    expect($metrics)
        ->toContain('general-metric--public-email')
        ->toContain('general-metric--contact-delivery')
        ->toContain('general-metric--legal');

    expect($responsive)
        ->toContain('General responsive authority')
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('.general-appearance-stage__preview')
        ->toContain('display: none !important')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr)) !important')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr)) !important');

    expect($generalCss)
        ->toContain('min-height: 5.9rem')
        ->toContain('height: 4.5rem !important')
        ->toContain('width: calc(33.333333% - 5rem) !important');

    expect($social)
        ->toContain('admin-table__col-position')
        ->toContain('admin-table__col-drag')
        ->toContain('general-social-table__actions');

    expect($tableContract)
        ->toContain('.admin-table__col-position { width: 3rem; }')
        ->toContain('.admin-table__col-drag { width: 2rem; }');
});
