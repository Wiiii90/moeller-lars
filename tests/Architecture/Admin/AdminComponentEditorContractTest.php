<?php

it('uses one List repeater and explicit committed edit actions on Custom and Home component workspaces', function (): void {
    $shared = file_get_contents(app_path('Filament/Support/ComponentListEditor.php'));
    $customSchema = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceForms.php'));
    $customActions = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php'));
    $home = file_get_contents(app_path('Filament/Pages/HomePresentation.php'));
    $customRows = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $homeRows = file_get_contents(resource_path('views/filament/pages/partials/home-component-sequence.blade.php'));
    $customChildren = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceChildProjection.php'));
    $homeChildren = file_get_contents(app_path('Filament/Pages/Concerns/HomePresentationChildren.php'));

    expect($shared)->toContain("Repeater::make('items')")
        ->toContain("->addActionLabel('Add list item')")
        ->toContain('->reorderableWithButtons()')
        ->and($customSchema)->toContain('ComponentListEditor::make()')
        ->and($home)->toContain('ComponentListEditor::make()')
        ->toContain("homeComponentEditingAction('editListComponent', committed: true)")
        ->toContain("AdminDialog::command(\$action, 'Apply changes'")
        ->and($customActions)->toContain("componentEditingAction('editListComponent', committed: true)")
        ->toContain("AdminDialog::command(\$action, 'Apply changes'")
        ->and($customRows)->toContain("'editListComponent' : 'editComponent'")
        ->not->toContain("mountAction('addListEntry'")
        ->and($homeRows)->toContain("'editListComponent' : 'editComponent'")
        ->not->toContain("mountAction('addHomeChild', { index: {{ \$homeComponent['index'] }}, type: 'list' })")
        ->and($customChildren)->not->toContain("if (\$type === 'list')")
        ->and($homeChildren)->toContain("if (\$type !== 'contact')");
});

it('exposes one canonical component selector in Edit without an empty Legal Disclaimer modal', function (): void {
    $customSchema = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceForms.php'));
    $customProjection = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceProjectionState.php'));
    $home = file_get_contents(app_path('Filament/Pages/HomePresentation.php'));
    $customRows = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $homeRows = file_get_contents(resource_path('views/filament/pages/partials/home-component-sequence.blade.php'));

    expect($customSchema)->toContain("Select::make('type')")
        ->toContain("->label('Component')")
        ->and($home)->toContain("Select::make('editor_kind')")
        ->toContain("'targetKind' => \$targetKind")
        ->and($customProjection)->toContain("'editable' => \$type !== 'legal_disclaimer'")
        ->and($customRows)->toContain(':disabled="! \$pageComponent[\'editable\']"')
        ->and($homeRows)->toContain(':disabled="! \$homeComponent[\'editable\']"');
});

it('cannot invoke Filament attachment uploads from the canonical Rich Text toolbar', function (): void {
    $editor = file_get_contents(app_path('Filament/Support/AdminRichText.php'));
    $trigger = file_get_contents(resource_path('views/filament/support/rich-text-image-trigger.blade.php'));
    $css = file_get_contents(resource_path('css/admin/forms.css'));

    expect($editor)->toContain('->fileAttachments(false)')
        ->toContain("View::make('filament.support.rich-text-image-trigger')")
        ->not->toContain("['attachFiles']")
        ->not->toContain('new MutationObserver(')
        ->not->toContain('setTimeout(')
        ->and($trigger)->toContain('Insert image')
        ->and($css)->toContain('.admin-rich-text {')
        ->toContain('min-height: 17rem;');
});
