<?php

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\ArtworkCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Admin/Gallery/GalleryFixtures.php';

/** @return list<string> */
function galleryArtworkAndDividerSequence(string $html): array
{
    preg_match_all(
        '/<article class="artwork-card">|<div class="custom-page__divider custom-page__divider--(?:thin|subtle|strong|dotted) artwork-list__divider"/',
        $html,
        $matches,
    );

    return array_map(
        static fn (string $match): string => str_contains($match, 'artwork-list__divider') ? 'divider' : 'artwork',
        $matches[0],
    );
}

it('renders the canonical Gallery divider only between visible Artworks after publication', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    // Keep this route fixture independent of the default Home gate.
    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $gallery = galleryFixtureCategory();
    foreach ([1, 2, 3] as $position) {
        $artwork = galleryFixtureArtwork($gallery, [
            'state' => 'published', 'position' => $position * 10,
        ]);
        $asset = galleryFixtureAsset('image/jpeg', 'Artwork '.$position);
        galleryFixturePrimary($artwork, $asset);
        galleryFixtureThumbnail($asset);
    }

    // This archived artwork must not generate an extra separator.
    $archived = galleryFixtureArtwork($gallery, ['state' => 'archived', 'position' => 40]);
    $archivedAsset = galleryFixtureAsset();
    galleryFixturePrimary($archived, $archivedAsset);
    galleryFixtureThumbnail($archivedAsset);

    $publication = app(PublicationService::class);
    expect($publication->commit($actor, 'Publish Gallery without dividers'))->not->toBeNull();

    $url = '/'.$gallery->slug;
    $publicBefore = $this->get($url)->assertOk();
    expect(galleryArtworkAndDividerSequence($publicBefore->getContent()))
        ->toBe(['artwork', 'artwork', 'artwork']);

    app(GalleryEditorialService::class)->update($gallery, [
        'name' => $gallery->name, 'description' => $gallery->description,
        'artwork_divider' => 'dotted',
    ]);

    // Preview is Working, but public still reads the last Committed state.
    $preview = $this->get('/preview'.$url)->assertOk();
    expect(galleryArtworkAndDividerSequence($preview->getContent()))
        ->toBe(['artwork', 'divider', 'artwork', 'divider', 'artwork']);
    expect($preview->getContent())->toContain('custom-page__divider--dotted');

    $publicStaged = $this->get($url)->assertOk();
    expect(galleryArtworkAndDividerSequence($publicStaged->getContent()))
        ->toBe(['artwork', 'artwork', 'artwork']);

    expect($publication->commit($actor, 'Publish dotted Gallery dividers'))->not->toBeNull();
    Auth::guard('web')->logout();

    $published = $this->get($url)->assertOk();
    expect(galleryArtworkAndDividerSequence($published->getContent()))
        ->toBe(['artwork', 'divider', 'artwork', 'divider', 'artwork'])
        ->and(substr_count($published->getContent(), 'artwork-list__divider'))->toBe(2);

    // Changing variant updates the same two positions without duplicating strokes.
    $this->actingAs($actor, 'web');
    app(GalleryEditorialService::class)->update($gallery, [
        'name' => $gallery->name, 'description' => $gallery->description,
        'artwork_divider' => 'strong',
    ]);
    $strong = $this->get('/preview'.$url)->assertOk();
    expect(galleryArtworkAndDividerSequence($strong->getContent()))
        ->toBe(['artwork', 'divider', 'artwork', 'divider', 'artwork'])
        ->and($strong->getContent())->toContain('custom-page__divider--strong')
        ->not->toContain('custom-page__divider--dotted artwork-list__divider');
});

it('renders no inter-Artwork separators for empty or single-item Galleries', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $emptyGallery = galleryFixtureCategory();
    $oneGallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($oneGallery, ['state' => 'published', 'position' => 10]);
    $asset = galleryFixtureAsset();
    galleryFixturePrimary($artwork, $asset);
    galleryFixtureThumbnail($asset);

    foreach ([$emptyGallery, $oneGallery] as $gallery) {
        app(GalleryEditorialService::class)->update($gallery, [
            'name' => $gallery->name, 'description' => $gallery->description,
            'artwork_divider' => 'thin',
        ]);
    }
    expect(app(PublicationService::class)->commit($actor, 'Publish single and empty Gallery'))->not->toBeNull();

    expect(galleryArtworkAndDividerSequence($this->get('/'.$emptyGallery->slug)->assertOk()->getContent()))
        ->toBe([])
        ->and(galleryArtworkAndDividerSequence($this->get('/'.$oneGallery->slug)->assertOk()->getContent()))
        ->toBe(['artwork']);
});
