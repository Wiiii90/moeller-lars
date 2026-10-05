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

it('keeps ordinary table pressure centralized and selection terminal', function (): void {
    $root = dirname(__DIR__, 3);
    $tableContract = file_get_contents($root.'/resources/css/admin/table-contract.css');
    $responsive = file_get_contents($root.'/resources/css/admin/responsive.css');
    $storage = file_get_contents($root.'/resources/views/filament/resources/media-assets/partials/storage-library.blade.php');
    $journal = file_get_contents($root.'/resources/views/filament/pages/journal-workspace.blade.php');
    $activity = file_get_contents($root.'/resources/views/filament/pages/activity.blade.php');

    expect($tableContract)
        ->toContain('.admin-table__yield--compact')
        ->toContain('.admin-table__yield--narrow')
        ->toContain('.admin-table__yield--minimal')
        ->toContain('td.admin-table__actions')
        ->toContain('padding-inline: 0 !important');

    expect($storage)
        ->toContain('media-workspace__col-usage admin-table__yield--compact')
        ->toContain('media-workspace__col-size admin-table__yield--narrow')
        ->toContain('media-workspace__col-status admin-table__yield--narrow')
        ->toContain('media-workspace__col-type admin-table__yield--minimal')
        ->toContain('media-workspace__actions admin-table__actions');

    expect($journal)
        ->toContain('<th scope="col" class="journal-visual journal-col--visual">Image</th>')
        ->toContain('journal-col--status admin-table__yield--minimal')
        ->toContain('journal-col--timing admin-table__yield--minimal')
        ->toContain('journal-col--publication admin-table__yield--compact')
        ->toContain('journal-col--schedule admin-table__yield--compact')
        ->not->toContain('journal-col--visual admin-table__yield--compact');

    expect($activity)
        ->toContain('activity-col--who admin-table__yield--compact')
        ->toContain('activity-col--publication admin-table__yield--compact')
        ->toContain('activity-col--area admin-table__yield--minimal')
        ->toContain('activity-col--type admin-table__yield--minimal');

    expect($responsive)
        ->not->toContain('media-workspace__usage-head')
        ->not->toContain('journal-table--blog .journal-col--visual')
        ->not->toContain('activity-events-table .activity-col--who,');
});

it('gates focused and single-visual stages behind the burger minimal state', function (): void {
    $root = dirname(__DIR__, 3);
    $stage = file_get_contents($root.'/resources/css/admin/stage.css');

    expect($stage)
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('.admin-focus-stage')
        ->toContain('.admin-dashboard__overview')
        ->toContain('.admin-visual-stage--single-minimal')
        ->toContain('.admin-stage-tabs');
});

it('owns filter overflow centrally by filter count and workspace pressure', function (): void {
    $root = dirname(__DIR__, 3);
    $controls = file_get_contents($root.'/resources/views/components/admin/controls.blade.php');
    $sixCell = file_get_contents($root.'/resources/css/admin/six-cell-contract.css');
    $icons = file_get_contents($root.'/app/Filament/Support/AdminIcon.php');

    expect($controls)
        ->toContain('admin-filter-overflow')
        ->toContain('admin-filter-overflow__trigger')
        ->toContain('admin-filter-overflow__reset')
        ->toContain('AdminIcon::Filter')
        ->toContain('positionPanel()')
        ->toContain('x-bind:style="panelStyle"')
        ->toContain('x-on:resize.window="if (open) $nextTick(() => positionPanel())"');

    expect($sixCell)
        ->toContain('Shared filter overflow')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('.admin-data-controls--filters-3')
        ->toContain('.admin-data-controls--filters-4')
        ->toContain('@container admin-workspace (max-width: 38rem)')
        ->toContain('.admin-data-controls--filters-2')
        ->toContain('minmax(10rem, 1fr)')
        ->toContain('minmax(8rem, 1fr)')
        ->toContain('.admin-filter-overflow__reset')
        ->toContain('position: fixed')
        ->toContain('max-width: calc(100vw - 1rem)')
        ->toContain('overflow-y: auto')
        ->toContain('display: none !important');

    expect($icons)
        ->toContain("case Filter = 'heroicon-o-funnel';");
});
