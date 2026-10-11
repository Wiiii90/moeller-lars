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
        ->not->toContain('admin-show-home.svg');
});
