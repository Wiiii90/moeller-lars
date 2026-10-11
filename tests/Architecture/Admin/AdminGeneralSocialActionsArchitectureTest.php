<?php

it('keeps General social profiles directly editable with a canonical whole-row Edit action', function (): void {
    $root = dirname(__DIR__, 3);
    $general = file_get_contents($root.'/app/Filament/Pages/General.php');
    $view = file_get_contents($root.'/resources/views/filament/schemas/components/general-social-links.blade.php');
    $socialCss = file_get_contents($root.'/resources/css/admin/general.css');
    $sharedTableCss = file_get_contents($root.'/resources/css/admin/table-contract.css');

    expect($general)
        ->toContain('public function editSocialLinkAction(): Action')
        ->toContain('AdminDialog::command(')
        ->toContain("'Save profile'")
        ->toContain("->modalHeading('Edit profile')")
        ->toContain("'position' => \$index === null ? 1 : \$index + 1")
        ->toContain('->numeric()')
        ->toContain('->integer()')
        ->toContain('->maxLength(2048)')
        ->toContain('if ($otherIndex !== $index && ($link[\'platform\'] ?? null) === $platform)')
        ->toContain('array_splice($links, $position - 1, 0, $moved);')
        ->toContain('$this->saveSocialLinks($links);');

    expect($view)
        ->toContain('admin-row-actions--move-edit-delete')
        ->toContain("wire:change=\"updateSocialLink({{ \$index }}, 'platform', \$event.target.value)\"")
        ->toContain("wire:change=\"updateSocialLink({{ \$index }}, 'url', \$event.target.value)\"")
        ->toContain("mountAction('editSocialLink', { index: {{ \$index }} })");

    $edit = strpos($view, 'AdminRowAction::Edit');
    $delete = strpos($view, 'AdminRowAction::Delete');
    expect($edit)->not->toBeFalse();
    expect($delete)->not->toBeFalse();
    expect($edit)->toBeLessThan($delete);

    expect($socialCss)
        ->toContain('padding-inline-end: var(--admin-table-edge-inset) !important;')
        ->toContain('--admin-row-actions-width: var(--admin-row-actions-move-edit-delete-width);')
        ->toContain('@container admin-table (max-width: 60rem)')
        ->toContain('--admin-row-actions-columns: repeat(4, 1.35rem);');

    expect($sharedTableCss)
        ->toContain('--admin-row-actions-move-edit-delete-width: 18.5rem;')
        ->toContain('var(--admin-row-action-slot-delete-width);')
        ->toContain('--admin-row-actions-move-edit-delete-compact-width: 6.75rem;');
});
