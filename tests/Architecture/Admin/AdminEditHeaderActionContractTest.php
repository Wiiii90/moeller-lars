<?php

use App\Domain\Content\DividerVariant;
use App\Filament\Support\AdminRowAction;

it('keeps one canonical dialog header action factory keyed by row semantics', function (): void {
    $adapter = file_get_contents(app_path('Filament/Support/Dialogs/AdminDialog.php'));

    expect($adapter)->toContain('function headerAction(')
        ->and($adapter)->toContain('$role->icon()->value')
        ->and($adapter)->toContain('$role->label()')
        ->and($adapter)->toContain('admin-dialog__header-action');
    expect(array_keys(DividerVariant::LABELS))->toBe(DividerVariant::VALUES);
});

it('provides row-ordered contextual actions in the primary admin edit dialogs', function (): void {
    $pages = file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePageEditDialog.php'));
    $components = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php'));
    $general = file_get_contents(app_path('Filament/Pages/General.php'));
    $home = file_get_contents(app_path('Filament/Pages/HomePresentation.php'));
    $dashboard = file_get_contents(app_path('Filament/Pages/Dashboard.php'));
    $forms = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceForms.php'));
    $children = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceListContactActions.php'));
    $childForms = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceSecondaryForms.php'));

    foreach ([$pages, $components, $general, $home] as $source) {
        expect($source)->toContain('->extraModalFooterActions(')
            ->and($source)->toContain('AdminRowAction::MoveUp')
            ->and($source)->toContain('AdminRowAction::MoveDown')
            ->and($source)->toContain('AdminRowAction::Delete');
    }

    expect($pages)->toContain('AdminRowAction::Unpublish')
        ->and($components)->toContain('AdminRowAction::Unpublish')
        ->and($forms)->toContain("Hidden::make('publication_state')")
        ->and($home)->toContain("Select::make('variant')")
        ->and($children)->toContain('listEntryEditHeaderActions')
        ->and($children)->toContain('contactChildEditHeaderActions')
        ->and($forms)->toContain('Hidden::make(\'publication_state\')')
        ->and($childForms)->toContain('Hidden::make(\'publication_state\')')
        ->and($dashboard)->toContain('toggleDialogPin')
        ->and($dashboard)->toContain('return [$pinAction, $readAction, $deleteAction];');
});
