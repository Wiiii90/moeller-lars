<?php

it('keeps Custom Page Contact in one component editor and List details free of image insertion', function (): void {
    $root = dirname(__DIR__, 3);
    $workspace = file_get_contents($root.'/app/Filament/Pages/CustomPageWorkspace.php');
    $sequence = file_get_contents($root.'/resources/views/filament/pages/partials/custom-page-workspace-sequence.blade.php');
    $forms = file_get_contents($root.'/app/Filament/Pages/Concerns/CustomPageWorkspaceForms.php');
    $secondary = file_get_contents($root.'/app/Filament/Pages/Concerns/CustomPageWorkspaceSecondaryForms.php');
    $list = file_get_contents($root.'/app/Filament/Support/ComponentListEditor.php');
    $home = file_get_contents($root.'/app/Filament/Pages/HomePresentation.php');
    $model = file_get_contents($root.'/app/Models/CustomPageSetting.php');
    $public = file_get_contents($root.'/resources/views/pages/partials/component-sequence.blade.php');
    $contact = file_get_contents($root.'/resources/views/components/contact.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/custom-page.css');

    expect($workspace)
        ->not->toContain('CustomPageWorkspaceListContactActions')
        ->not->toContain('CustomPageWorkspaceChildOrdering')
        ->not->toContain('selectedChildTargets');
    expect($sequence)
        ->not->toContain('custom-page-child-row')
        ->not->toContain('addContactChild')
        ->not->toContain('editContactChild')
        ->toContain('custom-page-component__content--two-lines');

    expect($forms)
        ->toContain("Toggle::make('contact_email_enabled')")
        ->toContain("Toggle::make('contact_social_enabled')")
        ->toContain("Toggle::make('contact_form_enabled')")
        ->toContain("TextInput::make('title')->label('Heading')->placeholder('Contact')");
    expect($secondary)
        ->toContain("'title' => \$data['title'] ?? null")
        ->toContain('contact_social_platforms')
        ->not->toContain('contactChildEditorSchema');

    expect($list)
        ->toContain("TextInput::make('context')->label('Context')")
        ->toContain('allowEmbeddedMedia: false')
        ->not->toContain("TextInput::make('date')")
        ->not->toContain("TextInput::make('meta')")
        ->not->toContain("TextInput::make('location')")
        ->not->toContain("TextInput::make('url')");
    expect($home)
        ->toContain('CustomPageSetting::listItemContext($item)')
        ->toContain('CustomPageSetting::listItemDetails($item)');
    expect($model)
        ->toContain('public static function listItemContext(')
        ->toContain('public static function listItemDetails(');
    expect($public)
        ->toContain('custom-page__context')
        ->toContain(':title="$block[\'title\'] ?? null"');
    expect($contact)->toContain('filled($title)');
    expect($css)->toContain('.custom-page-component__content--two-lines > :is(strong, span)');
});
