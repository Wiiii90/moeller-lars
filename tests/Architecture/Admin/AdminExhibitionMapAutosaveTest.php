<?php

it('saves Filament switches using the existing dialog change event rather than a parallel action executor', function (): void {
    $root = dirname(__DIR__, 3);
    $controls = file_get_contents($root.'/app/Filament/Support/Controls/AdminControl.php');
    $dialog = file_get_contents($root.'/app/Filament/Support/Dialogs/AdminDialog.php');

    expect($controls)
        ->toContain('Toggle::configureUsing(fn (Toggle $field) => self::decorateToggle($field))')
        ->toContain("'x-on:click.capture'")
        ->toContain("if (\$el.closest('.admin-dialog--edit'))")
        ->toContain('new Event(\'change\', { bubbles: true })')
        ->toContain('$nextTick(')
        ->not->toContain('setTimeout(')
        ->not->toContain('callMountedAction(')
        ->and($dialog)
        ->toContain("'wire:change' => 'callMountedAction'")
        ->toContain('$action->halt();');
});

it('uses one address eligibility policy and refreshes only committed map coordinates', function (): void {
    $root = dirname(__DIR__, 3);
    $schema = file_get_contents($root.'/app/Filament/Support/JournalEntryEditorSchema.php');
    $service = file_get_contents($root.'/app/Domain/Content/ExhibitionEditorialService.php');
    $journal = file_get_contents($root.'/app/Filament/Pages/JournalWorkspace.php');

    expect($schema)
        ->toContain("Toggle::make('map_enabled')")
        ->toContain('ExhibitionMapAddress::isSufficient(')
        ->toContain("! (bool) \$get('map_enabled')")
        ->not->toContain("->afterStateUpdatedJs(")
        ->toContain("Hidden::make('latitude')->dehydrated(false)")
        ->not->toContain('journal-location-stale')
        ->not->toContain('x-on:journal-location-stale')
        ->not->toContain('stale = true')
        ->and($service)
        ->toContain('ExhibitionMapAddress::isSufficient($values)')
        ->toContain("if (\$mapEnabled && (\$locationChanged || ! \$fresh->hasCoordinates()))")
        ->and($journal)
        ->toContain('Schema $schema, array $data, array $arguments')
        ->toContain('$schema->partialRawState([')
        ->toContain("'latitude' => \$updated->getAttribute('latitude')")
        ->toContain("'longitude' => \$updated->getAttribute('longitude')")
        ->toContain('return AdminDialog::edit($action, AdminDialogSize::Large);');
});
