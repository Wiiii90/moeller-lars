<?php

it('uses one native dialog architecture across Gallery artwork, Materials and Journal entry editors', function (): void {
    $root = dirname(__DIR__, 3);
    $gallery = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php');
    $fields = file_get_contents($root.'/app/Filament/Support/ArtworkEditDialog.php');
    $galleryCreate = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceFormSupport.php');
    $galleryActions = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php');
    $materials = file_get_contents($root.'/app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php');
    $journal = file_get_contents($root.'/app/Filament/Pages/JournalWorkspace.php');
    $journalFields = file_get_contents($root.'/app/Filament/Support/JournalEntryEditorSchema.php');

    expect($galleryCreate)
        ->toContain('ArtworkEditDialog::class')
        ->toContain('->schema(creating: true)')
        ->not->toContain("TextInput::make('title')");
    expect($fields)
        ->toContain('public function schema(bool $includeGalleryAssignment = false, bool $creating = false)')
        ->toContain("\$slug->unique('artworks', 'slug')")
        ->toContain("AdminForm::section('Basics')")
        ->toContain("AdminForm::section('Dimensions')")
        ->toContain("AdminForm::section('Content')")
        ->toContain("AdminForm::section('Media')")
        ->toContain("AdminForm::section('Presentation')");

    expect($gallery)
        ->toContain("return AdminDialog::create(\$action, 'Create draft', AdminDialogSize::Large)")
        ->toContain("return AdminDialog::edit(\$action, AdminDialogSize::Large)")
        ->toContain('->extraModalFooterActions(fn (array $arguments): array => $this->artworkEditHeaderActions($arguments))')
        ->toContain("AdminRowAction::MoveUp")
        ->toContain("AdminRowAction::MoveDown")
        ->toContain("AdminRowAction::Unpublish")
        ->toContain("AdminRowAction::Publish")
        ->toContain("replaceMountedAction('unpublishArtwork'")
        ->toContain("replaceMountedAction('removeArtwork'");
    expect($galleryActions)
        ->toContain('private function publishArtworkInGallery(int $id): bool')
        ->toContain('->publish($artwork)');

    expect($materials)
        ->toContain("AdminDialog::command(\$action, 'Apply changes', AdminDialogSize::Default)")
        ->toContain('ArtworkMaterialPresetService::class')
        ->not->toContain("->helperText('Existing artwork text is unchanged.')");

    expect($journal)
        ->toContain("return AdminDialog::create(\$action, 'Create draft', AdminDialogSize::Large)")
        ->toContain("return AdminDialog::create(\$action, 'Create exhibition', AdminDialogSize::Large)")
        ->toContain("journalEditHeaderActions(\$arguments, 'post')")
        ->toContain("journalEditHeaderActions(\$arguments, 'exhibition')")
        ->toContain("replaceMountedAction('schedulePost'")
        ->toContain("replaceMountedAction('deletePost'")
        ->toContain("replaceMountedAction('deleteExhibition'");

    expect($journalFields)
        ->toContain("AdminForm::section('Basics')")
        ->toContain("AdminForm::section('Venue')")
        ->toContain("AdminForm::section('Dates')")
        ->toContain("->columns(['md' => 6])")
        ->toContain("->columns(['md' => 2])")
        ->not->toContain('->columns(6)')
        ->not->toContain('->columns(2)');
});
