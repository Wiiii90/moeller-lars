<?php

it('keeps selected dropdown values readable without breaking responsive toolbars', function (): void {
    $layouts = file_get_contents(resource_path('css/admin/layouts.css'));
    $inline = file_get_contents(resource_path('css/admin/task-surfaces.css'));
    $tables = file_get_contents(resource_path('css/admin/table-contract.css'));
    $toolbar = file_get_contents(resource_path('css/admin/six-cell-contract.css'));
    $pages = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $activity = file_get_contents(resource_path('views/filament/pages/activity.blade.php'));

    expect($layouts)
        ->toContain('appearance: base-select;')
        ->toContain('padding-inline-end: 1.45rem !important;')
        ->toContain('text-overflow: ellipsis;')
        ->toContain('white-space: nowrap;');

    expect($inline)
        ->toContain('.admin-inline-select {')
        ->toContain('text-overflow: ellipsis;');

    expect($tables)
        ->toContain('minmax(12.5rem, 13rem)')
        ->toContain('var(--admin-row-actions-move-state-delete-width) + var(--admin-table-selection-width)');

    expect($toolbar)
        ->toContain('minmax(0, 1fr) 7rem 11rem 8.25rem 7rem max-content')
        ->toContain('@container admin-workspace (max-width: 76rem)')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('.admin-data-controls--dashboard-responsive.admin-data-controls--filters-4 > .admin-filter-overflow')
        ->toContain('.admin-data-controls--dashboard-responsive.admin-data-controls--filters-3 > .admin-filter-overflow')
        ->toContain('.admin-filter-overflow[data-open="true"] > .admin-data-controls__filters')
        ->not->toContain('5.75rem 5.75rem 7.5rem 6.5rem');

    expect($pages)
        ->toContain('aria-label="Home template"')
        ->toContain('wire:change="changeHomeTemplate');

    expect($activity)
        ->toContain('wire:model.live="familyFilter"')
        ->toContain(':filter-count="4"');
});
