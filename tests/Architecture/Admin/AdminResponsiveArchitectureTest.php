<?php

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
        ->not->toContain('width: calc(33.333333% - 5rem) !important')
        ->toContain('minmax(4.5rem, 1fr)')
        ->toContain('minmax(5rem, 1fr)');

    expect($social)
        ->toContain('admin-table__col-position')
        ->toContain('admin-table__col-drag')
        ->toContain('general-social-table__actions');

    expect($tableContract)
        ->toContain('.admin-table__col-position { width: 3rem; }')
        ->toContain('.admin-table__col-drag { width: 2rem; }');
});


it('covers Analytics Storage and Activity in the responsive contract', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $analytics = file_get_contents($root.'/resources/views/filament/pages/analytics.blade.php');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-overview.blade.php');
    $activity = file_get_contents($root.'/resources/views/filament/pages/activity.blade.php');

    expect($analytics)
        ->toContain('analytics-status-metrics')
        ->toContain("'nb_visits' => 'visits'")
        ->toContain("'nb_uniq_visitors' => 'unique'")
        ->toContain("'nb_actions' => 'actions'");

    expect($storage)
        ->toContain('storage-status-metrics')
        ->toContain('storage-metric--used')
        ->toContain('storage-metric--remaining')
        ->toContain('admin-storage__capacity-label--short');

    expect($activity)
        ->toContain('activity-status-metrics')
        ->toContain("'Changes' => 'changes'")
        ->toContain("'Pending' => 'pending'")
        ->toContain('AdminIcon::Clear->mini()')
        ->toContain('AdminIcon::Activity->mini()')
        ->toContain('AdminIcon::Commit->mini()');

    expect($responsive)
        ->toContain('Analytics / Storage / Activity responsive authority')
        ->toContain('Analytics/Storage/Activity burger-shell monotonicity')
        ->toContain('.analytics-visual-stage > .analytics-stage-rail')
        ->toContain('.admin-storage__distribution')
        ->toContain('.activity-atlas__view.activity-clock')
        ->toContain('.media-workspace__usage-cell')
        ->toContain('.activity-events-table .activity-col--publication');
});

it('locks Analytics Storage and Activity Minimal metrics to exact halves', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($responsive)
        ->toContain('.analytics-status-metrics > .analytics-metric--visits')
        ->toContain('.analytics-status-metrics > .analytics-metric--unique')
        ->toContain('.storage-status-metrics > .storage-metric--used')
        ->toContain('.storage-status-metrics > .storage-metric--remaining')
        ->toContain('.activity-status-metrics > .activity-metric--changes')
        ->toContain('.activity-status-metrics > .activity-metric--pending')
        ->toContain('grid-column: 1 !important')
        ->toContain('grid-column: 2 !important');
});


it('locks Analytics Storage Activity metric slots and publication-first Activity stage', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($responsive)
        ->toContain('Final metric geometry + Activity publication priority authority')
        ->toContain('.analytics-status-metrics > .analytics-metric--visits')
        ->toContain('.storage-status-metrics > .storage-metric--used')
        ->toContain('.activity-status-metrics > .activity-metric--changes')
        ->toContain('grid-column: 1 !important')
        ->toContain('grid-column: 2 !important')
        ->toContain('grid-column: 3 !important')
        ->toContain('justify-self: stretch !important')
        ->toContain('border-right: 1px solid var(--admin-line) !important')
        ->toContain('Activity priority: once Narrow, Next Publication owns the stage')
        ->toContain('.activity-atlas__visual')
        ->toContain('display: none !important')
        ->toContain('.activity-publication__actions .admin-action__label')
        ->toContain('display: inline !important');
});


it('centralizes responsive metric geometry for every six-metric strip', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($responsive)
        ->toContain('Canonical responsive metric geometry')
        ->toContain('--admin-metric-columns: 3 !important')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr)) !important')
        ->toContain('left: 33.333333%')
        ->toContain('left: 66.666667%')
        ->toContain('--admin-metric-columns: 2 !important')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr)) !important')
        ->toContain('left: 50%')
        ->toContain('grid-column: auto !important')
        ->toContain('gap: 0 !important');
});


it('delays Analytics Storage Activity stage collapse until Minimal', function (): void {
    $root = dirname(__DIR__, 3);
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');

    expect($responsive)
        ->toContain('Canonical stage transition authority for Analytics / Storage / Activity')
        ->toContain('@container admin-workspace (min-width: 38.01rem) and (max-width: 54rem)')
        ->toContain('.analytics-visual-stage > .analytics-stage-rail')
        ->toContain('.admin-storage__distribution')
        ->toContain('.activity-atlas__view.activity-clock')
        ->toContain('grid-template-columns: repeat(3, minmax(0, 1fr)) !important')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('grid-template-columns: minmax(0, 1fr) !important')
        ->toContain('height: var(--admin-visual-stage-height) !important')
        ->toContain('Activity Minimal: Next Publication owns the entire fixed-height stage');
});
