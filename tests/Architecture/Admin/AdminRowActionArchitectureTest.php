<?php

it('renders editorial row actions through one semantic admin primitive', function (): void {
    $root = dirname(__DIR__, 3);
    $catalog = file_get_contents($root.'/app/Filament/Support/AdminRowAction.php');
    $component = file_get_contents($root.'/resources/views/components/admin/row-action.blade.php');
    $journal = file_get_contents($root.'/resources/views/filament/pages/journal-workspace.blade.php');
    $customPage = file_get_contents($root.'/resources/views/filament/pages/partials/custom-page-workspace-sequence.blade.php');
    $customPageCss = file_get_contents($root.'/resources/css/admin/custom-page.css');
    $pages = file_get_contents($root.'/resources/views/filament/pages/partials/site-section-row.blade.php');
    $dashboard = file_get_contents($root.'/resources/views/filament/pages/partials/dashboard-feed-row.blade.php');

    expect($catalog)
        ->toContain('case MoveUp')
        ->toContain('case MoveDown')
        ->toContain('case Open')
        ->toContain('case Edit')
        ->toContain('case Publish')
        ->toContain('case Unpublish')
        ->toContain('case Delete')
        ->toContain('case SkipHome')
        ->toContain('case Pin')
        ->toContain('case MarkRead')
        ->toContain('AdminIcon::MoveUp')
        ->toContain('AdminIcon::Delete');

    expect($component)
        ->toContain("'disabled' => false")
        ->toContain('@disabled($disabled)')
        ->toContain('$rowAction->icon()->mini()')
        ->toContain('$rowAction->label()')
        ->toContain("'admin-order-action' => \$rowAction->isOrderAction()")
        ->toContain("'admin-action--state' => \$rowAction->isStateAction()")
        ->toContain("'is-danger' => \$rowAction->isDangerAction()");

    foreach ([$journal, $customPage, $dashboard] as $view) {
        expect($view)
            ->toContain('<x-admin.row-action')
            ->not->toContain('<x-filament::icon')
            ->not->toContain('admin-action--with-icon');
    }

    expect($journal)
        ->toContain(':disabled="! $entry[\'can_move_up\']"')
        ->toContain(':disabled="! $entry[\'can_move_down\']"')
        ->toContain(':disabled="! $entry[\'can_delete\']"')
        ->not->toContain('@disabled(! $entry[\'can_move_up\'])')
        ->not->toContain('@disabled(! $entry[\'can_move_down\'])')
        ->not->toContain('@disabled(! $entry[\'can_delete\'])');

    expect($customPage)
        ->toContain('@foreach ($components as $pageComponent)')
        ->toContain('AdminRowAction::Delete')
        ->toContain('admin-hierarchy__selection admin-hierarchy__selection--trailing')
        ->toContain('wire:model.live="selectedComponentTargets"')
        ->toContain('wire:model.live="selectedChildTargets"')
        ->toContain(':disabled="! $reorderEnabled || ! $pageComponent[\'can_move_up\']"')
        ->toContain(':disabled="! $reorderEnabled || ! $pageComponent[\'can_move_down\']"')
        ->toContain(':disabled="! $child[\'can_move_up\']"')
        ->toContain(':disabled="! $child[\'can_move_down\']"')
        ->not->toContain('@disabled(! $reorderEnabled || ! $pageComponent[\'can_move_up\'])')
        ->not->toContain('@disabled(! $reorderEnabled || ! $pageComponent[\'can_move_down\'])')
        ->not->toContain('@disabled(! $child[\'can_move_up\'])')
        ->not->toContain('@disabled(! $child[\'can_move_down\'])')
        ->not->toContain('@foreach ($components as $component)')
        ->not->toContain('$componentStateIcon')
        ->not->toContain('is_cv_list')
        ->not->toContain('moveCvEntry')
        ->not->toContain('editCvEntry')
        ->not->toContain('transitionCvEntry')
        ->not->toContain('deleteCvEntry');

    expect($customPageCss)
        ->toContain(".custom-page-component-sequence {\n    width: 100%;\n    min-width: 0;\n    max-width: 100%;")
        ->toContain(".custom-page-component-sequence__header,\n.custom-page-component-sequence__rows {\n    width: 100%;\n    min-width: 76rem;")
        ->toContain(".custom-page-row-actions .admin-action__label {\n    white-space: nowrap;")
        ->toContain('overflow: visible;')
        ->not->toContain(".custom-page-row-actions .admin-action__label {\n    min-width: 0;\n    overflow: hidden;")
        ->not->toContain(".custom-page-row-actions .admin-action__label {\n    min-width: 0;\n    text-overflow: ellipsis;");

    expect($pages)
        ->toContain('<x-admin.row-action')
        ->toContain('AdminRowAction::MoveUp')
        ->toContain('AdminRowAction::SkipHome')
        ->toContain(':disabled="! $reorderEnabled || ! $section[\'can_move_up\']"')
        ->toContain(':disabled="! $reorderEnabled || ! $section[\'can_move_down\']"')
        ->not->toContain('@disabled(! $reorderEnabled')
        ->not->toContain('admin-action--with-icon');
});
