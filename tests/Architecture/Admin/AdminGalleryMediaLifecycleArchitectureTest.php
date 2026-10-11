<?php

it('keeps media deletion and Gallery membership cleanup in one canonical service', function (): void {
    $media = file_get_contents(app_path('Domain/Media/MediaAssetEditorialService.php'));
    $gallery = file_get_contents(app_path('Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php'));
    $query = file_get_contents(app_path('Domain/Artwork/PublicArtworkQuery.php'));

    expect($media)
        ->toContain('$this->galleryAssignments->detach($artwork);')
        ->toContain("if ($removedPrimary && (string) $artwork->getAttribute('state') === 'published')")
        ->toContain("['state' => 'draft']");

    expect($gallery)
        ->not->toContain('detachGalleryArtworksAfterPrimaryMediaDelete')
        ->toContain('MediaAssetEditorialService::class')->toContain('->delete($asset)');

    expect($query)
        ->toContain("->whereHas('artworkMedia'")
        ->toContain("NULLIF(BTRIM(alt_text_override), '')")
        ->toContain("->where('state', 'available')")
        ->toContain('MediaIngestService::THUMBNAIL_KIND');
});

it('offers primary ALT descriptions in all Gallery editors and honors overrides in readiness and publication', function (): void {
    $create = file_get_contents(app_path('Filament/Pages/Concerns/GalleryWorkspaceFormSupport.php'));
    $createSave = file_get_contents(app_path('Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php'));
    $edit = file_get_contents(app_path('Filament/Support/ArtworkEditDialog.php'));
    $batch = file_get_contents(app_path('Filament/Pages/Concerns/GalleryWorkspaceDirectUpload.php'));
    $publication = file_get_contents(app_path('Domain/Artwork/ArtworkPublicationService.php'));
    $readiness = file_get_contents(app_path('Filament/Pages/Concerns/GalleryWorkspaceReadinessSupport.php'));

    foreach ([$create, $edit, $batch] as $source) {
        expect($source)->toContain("'primary_alt_text'");
    }
    expect($createSave)->toContain('updatePrimaryAltOverride(');
    expect($edit)->toContain('updatePrimaryAltOverride(');
    expect($batch)->toContain('updatePrimaryAltOverride(');
    expect($publication)
        ->toContain('mediaReady(MediaAsset $asset, ?string $altOverride = null)')
        ->toContain('$primary->getAttribute(\\'alt_text_override\\')');
    expect($readiness)->toContain('$primary?->getAttribute(\\'alt_text_override\\')');
});
