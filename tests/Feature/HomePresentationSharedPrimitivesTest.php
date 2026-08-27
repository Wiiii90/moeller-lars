<?php

it('uses reconciled shared primitives for Home data and editorial task surfaces', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/home-presentation.blade.php'));
    $css = (string) file_get_contents(resource_path('css/admin/home.css'));

    expect($view)
        ->toContain('admin-data-controls')
        ->toContain('admin-selection')
        ->toContain('admin-drag-handle')
        ->toContain('admin-position')
        ->toContain('admin-bottom-add')
        ->toContain('admin-pager')
        ->not->toContain('journal-workspace__')
        ->not->toContain('custom-page-row__drag')
        ->not->toContain('custom-page-component-add-row')
        ->not->toContain('admin-position-badge')
        ->not->toContain('admin-action-slot')
        ->and($css)->not->toContain('custom-page-')
        ->and($css)->not->toContain('.admin-position {')
        ->and($css)->not->toContain('.admin-pager {')
        ->and($css)->not->toContain('.admin-action.is-danger');
});
