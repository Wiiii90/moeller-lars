<?php

it('separates sibling drag sorting from deliberate nesting across both editors', function (): void {
    $root = dirname(__DIR__, 3);
    $pages = file_get_contents($root.'/resources/views/filament/pages/site-pages.blade.php');
    $row = file_get_contents($root.'/resources/views/filament/pages/partials/site-section-row.blade.php');
    $custom = file_get_contents($root.'/resources/views/filament/pages/partials/custom-page-workspace-sequence.blade.php');
    $styles = file_get_contents($root.'/resources/css/admin/table-contract.css');
    $actions = file_get_contents($root.'/app/Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php');
    $drag = file_get_contents($root.'/resources/js/admin-hierarchy-drag.js');
    $parentField = file_get_contents($root.'/app/Filament/Support/HierarchyParentField.php');
    $forms = file_get_contents($root.'/app/Filament/Pages/Concerns/CustomPageWorkspaceForms.php');

    expect($pages)->toContain('wire:sort:group="site-pages-root"')
        ->toContain('wire:sort:group="site-pages-{{ $section[\'id\'] }}"')
        ->not->toContain('wire:sort:group="site-pages"')
        ->and($pages)->toContain('data-admin-hierarchy="pages"')
        ->toContain('data-hierarchy-drop="root"')
        ->and($row)->toContain('admin-hierarchy__nest-target admin-pages__nest-target')
        ->and($custom)->toContain('wire:sort="sortComponent"')
        ->toContain('wire:sort="sortImageChild"')
        ->toContain('Drop inside image')
        ->toContain('Drop to page level')
        ->toContain('unnestImageChild(')
        ->and($styles)->toContain('.admin-hierarchy__nest-target.is-eligible')
        ->and($drag)->toContain('export function hierarchyMove(')
        ->toContain('installAdminHierarchyDrag(')
        ->and($parentField)->toContain('final class HierarchyParentField')
        ->and($forms)->toContain("'parent_image_index'")
        ->toContain("'Parent', 'Page level'")
        ->and($actions)->toContain('public function nestComponent(')
        ->toContain('public function unnestImageChild(')
        ->toContain('public function sortImageChild(');
});
