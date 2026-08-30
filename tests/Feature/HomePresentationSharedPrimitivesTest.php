<?php

it('uses reconciled shared primitives for Home data and editorial task surfaces', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/home-presentation.blade.php'));
    $css = (string) file_get_contents(resource_path('css/admin/home.css'));
    $sharedCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));

    expect($view)
        ->toContain('admin-visual-stage admin-visual-stage--stackable')
        ->toContain('admin-visual-stage__pane')
        ->toContain('<x-admin.controls')
        ->toContain('admin-selection')
        ->toContain('<x-admin.table class="admin-data-table">')
        ->toContain('admin-table__actions')
        ->toContain('admin-row-actions admin-toolbar')
        ->toContain('admin-action admin-action--state')
        ->toContain('admin-drag-handle')
        ->toContain('admin-position')
        ->toContain('<x-admin.add-row')
        ->toContain('admin-pager')
        ->not->toContain('admin-bottom-add')
        ->not->toContain('journal-workspace__')
        ->not->toContain('custom-page-row__drag')
        ->not->toContain('custom-page-component-add-row')
        ->not->toContain('admin-position-badge')
        ->not->toContain('admin-action-slot')
        ->and(substr_count($view, '<x-admin.add-row'))->toBe(2)
        ->and($css)->not->toContain('height: 30rem')
        ->and($css)->not->toContain('height: 24rem')
        ->and($css)->not->toContain('margin-bottom: 1.25rem')
        ->and($css)->not->toContain('custom-page-')
        ->and($css)->not->toContain('.admin-position {')
        ->and($css)->not->toContain('.admin-pager {')
        ->and($css)->not->toContain('.admin-action.is-danger')
        ->and($css)->not->toContain('.admin-bottom-add')
        ->and($sharedCss)->toContain('.admin-visual-stage {')
        ->and($sharedCss)->toContain('height: var(--admin-visual-stage-height);')
        ->and($sharedCss)->toContain('.admin-visual-stage + .admin-data-controls {')
        ->and($sharedCss)->toContain('border-top: 1px solid var(--admin-line-strong);')
        ->and($sharedCss)->not->toContain('border-bottom: 1px solid var(--admin-line-strong);');
});