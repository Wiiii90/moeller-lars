<?php

it('keeps the no-gutter scrollbar fix scoped to tables that flow into an add row', function (): void {
    $root = dirname(__DIR__, 3);
    $flow = file_get_contents($root.'/resources/css/admin/table-flow.css');
    $workspace = file_get_contents($root.'/resources/css/admin/data-workspace.css');
    $contract = file_get_contents($root.'/resources/css/admin/table-contract.css');
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');

    expect($theme)->toContain('resources/css/admin/table-flow.css');

    expect($flow)
        ->toContain('.admin-table {')
        ->not->toContain('scrollbar-width: none')
        ->not->toContain('.admin-table::-webkit-scrollbar');

    expect($workspace)
        ->toContain(':is(.media-workspace__table-wrap, .admin-table--data):has(+ .admin-add-row)')
        ->toContain('scrollbar-gutter: auto;')
        ->toContain('scrollbar-width: none;')
        ->toContain(':is(.media-workspace__table-wrap, .admin-table--data):has(+ .admin-add-row)::-webkit-scrollbar')
        ->toContain('height: 0;');

    expect($contract)
        ->toContain('.admin-data-controls:has(+ :is(.admin-table, .admin-hierarchy))')
        ->toContain('border-bottom: 0 !important;')
        ->not->toContain('.admin-task-controls:has(+ .admin-table)');
});
