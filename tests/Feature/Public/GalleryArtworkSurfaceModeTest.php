<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Admin/Gallery/GalleryFixtures.php';

it('overrides General surface separation only on Artwork detail pages of the selected Gallery', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $first = galleryFixtureCategory();
    $second = galleryFixtureCategory();

    $artworkA = galleryFixtureArtwork($first, ['state' => 'published', 'position' => 10]);
    $assetA = galleryFixtureAsset();
    galleryFixturePrimary($artworkA, $assetA);
    galleryFixtureThumbnail($assetA);

    $artworkB = galleryFixtureArtwork($second, ['state' => 'published', 'position' => 10]);
    $assetB = galleryFixtureAsset();
    galleryFixturePrimary($artworkB, $assetB);
    galleryFixtureThumbnail($assetB);

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_mode' => 'fine',
    ]);
    $publication = app(PublicationService::class);
    expect($publication->commit($actor, 'Publish Gallery baseline'))->not->toBeNull();

    $artworkAUrl = '/artworks/'.$artworkA->slug;
    $artworkBUrl = '/artworks/'.$artworkB->slug;
    expect($this->get($artworkAUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-fine')
        ->toContain('data-public-layout-divider-mode="fine"');

    app(GalleryEditorialService::class)->update($first, [
        'name' => $first->name,
        'description' => $first->description,
        'artwork_page_surface_mode' => 'separate',
    ]);

    $previewArtwork = $this->get('/preview'.$artworkAUrl)->assertOk()->getContent();
    expect($previewArtwork)
        ->toContain('site-header--divider-separate')
        ->toContain('data-public-layout-divider-mode="separate"')
        ->not->toContain('public-paper-frame--connected');

    expect($this->get('/preview/'.$first->slug)->assertOk()->getContent())
        ->toContain('site-header--divider-fine')
        ->toContain('data-public-layout-divider-mode="fine"');

    expect($this->get('/preview'.$artworkBUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-fine')
        ->toContain('data-public-layout-divider-mode="fine"');

    expect($this->get($artworkAUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-fine');

    expect($publication->commit($actor, 'Publish Artwork page surface override'))->not->toBeNull();
    expect($this->get($artworkAUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-separate');
    expect($this->get($artworkBUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-fine');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_mode' => 'separate',
    ]);
    app(GalleryEditorialService::class)->update($first, [
        'name' => $first->name,
        'description' => $first->description,
        'artwork_page_surface_mode' => 'joined',
    ]);

    expect($this->get('/preview'.$artworkAUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-fine')
        ->toContain('data-public-layout-divider-mode="fine"');
    expect($this->get('/preview'.$artworkBUrl)->assertOk()->getContent())
        ->toContain('site-header--divider-separate');
    expect($this->get('/preview/'.$first->slug)->assertOk()->getContent())
        ->toContain('site-header--divider-separate');
});

it('accepts only the three defined Artwork page surface modes', function (): void {
    $gallery = new \App\Models\ArtworkCategory(['name' => 'Test']);

    expect(\App\Domain\Artwork\GalleryPresentation::artworkPageSurfaceMode($gallery))->toBe('inherit')
        ->and(\App\Domain\Artwork\GalleryPresentation::artworkPageDividerOverride($gallery))->toBeNull();

    $gallery->setAttribute('artwork_page_surface_mode', 'joined');
    expect(\App\Domain\Artwork\GalleryPresentation::artworkPageDividerOverride($gallery))->toBe('fine');

    $gallery->setAttribute('artwork_page_surface_mode', 'separate');
    expect(\App\Domain\Artwork\GalleryPresentation::artworkPageDividerOverride($gallery))->toBe('separate');

    expect(fn () => \App\Domain\Artwork\GalleryPresentation::validateSettings([
        'artwork_page_surface_mode' => 'full',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});
