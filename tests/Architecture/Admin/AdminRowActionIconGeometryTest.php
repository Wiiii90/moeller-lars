<?php

it('fixes every semantic row-action icon at Filament Small regardless of state', function (): void {
    $view = file_get_contents(resource_path('views/components/admin/row-action.blade.php'));
    $css = file_get_contents(resource_path('css/admin/table-contract.css'));
    $rowActions = file_get_contents(app_path('Filament/Support/AdminRowAction.php'));
    $pageRow = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));

    expect(substr_count($view, ':size="\\Filament\\Support\\Enums\\IconSize::Small"'))->toBe(2);

    expect($css)
        ->toContain('.admin-action__icon {')
        ->toContain('width: 1rem;')
        ->toContain('height: 1rem;')
        ->toContain('flex: 0 0 1rem;');

    expect($rowActions)
        ->toContain('self::SkipHome => AdminIcon::SkipHome')
        ->toContain('self::ShowHome => AdminIcon::ShowHome');

    expect($pageRow)
        ->toContain('AdminRowAction::ShowHome')
        ->toContain('AdminRowAction::SkipHome')
        ->toContain('class="admin-pages__redirect-target"')
        ->not->toContain('admin-pages__redirect-target admin-selection__anchor')
        ->not->toContain('admin-show-home.svg');

    // The ordinary toolbar selection anchor has a 2.5rem min-height. With
    // row padding that exceeds the 3.4rem hierarchy row and changes Home's
    // height whenever Skip Home is enabled.
    $taskCss = file_get_contents(resource_path('css/admin/task-surfaces.css'));
    expect($taskCss)
        ->toContain('--admin-control-height')
        ->toContain('min-height: var(--admin-control-height);')
        ->toContain('min-height: 3.4rem;');
});
