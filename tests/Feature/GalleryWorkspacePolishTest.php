<?php

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\PublicMedia;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMaterialPreset;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function polishGallery(string $state = 'published'): ArtworkCategory
{
    $gallery = ArtworkCategory::query()->create([
        'slug' => 'gallery-'.fake()->unique()->uuid(),
        'name' => 'Gallery '.fake()->unique()->word(),
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, ['state' => $state]);

    return $gallery;
}

function polishArtwork(?ArtworkCategory $gallery, array $overrides = []): Artwork
{
    return Artwork::query()->create(array_merge([
        'artwork_category_id' => $gallery?->getKey(),
        'slug' => fake()->unique()->slug(),
        'title' => 'Artwork '.fake()->unique()->word(),
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ], $overrides));
}

function polishAsset(string $mime = 'image/jpeg', string $alt = 'Artwork media'): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.fake()->unique()->uuid().($mime === 'video/mp4' ? '.mp4' : '.jpg'),
        'original_filename' => $mime === 'video/mp4' ? 'artwork.mp4' : 'artwork.jpg',
        'mime_type' => $mime,
        'byte_size' => 16,
        'sha256' => hash('sha256', fake()->unique()->uuid()),
        'state' => 'available',
        'alt_text' => $alt,
        'width' => $mime === 'video/mp4' ? null : 20,
        'height' => $mime === 'video/mp4' ? null : 10,
    ]);
}

function polishPrimary(Artwork $artwork, MediaAsset $asset): ArtworkMedia
{
    return ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
}

function polishThumbnail(MediaAsset $asset): MediaVariant
{
    return MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.fake()->unique()->uuid().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 8,
        'sha256' => hash('sha256', fake()->unique()->uuid()),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 10,
        'height' => 5,
    ]);
}

it('detaches a draft from its Gallery without deleting artwork media or shared MediaAssets', function (): void {
    $gallery = polishGallery();
    $asset = polishAsset();
    $detached = polishArtwork($gallery, ['position' => 0]);
    $remaining = polishArtwork($gallery, ['position' => 1]);
    polishPrimary($detached, $asset);
    polishPrimary($remaining, $asset);

    app(ArtworkGalleryAssignmentService::class)->detach($detached);

    expect($detached->fresh()->artwork_category_id)->toBeNull()
        ->and(Artwork::query()->whereKey($detached->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $detached->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and((int) $remaining->fresh()->position)->toBe(0);
});

it('deletes only the draft and its usages while preserving a shared MediaAsset', function (): void {
    $gallery = polishGallery();
    $asset = polishAsset();
    $deleted = polishArtwork($gallery, ['position' => 0]);
    $other = polishArtwork($gallery, ['position' => 1]);
    polishPrimary($deleted, $asset);
    polishPrimary($other, $asset);

    app(ArtworkDraftService::class)->delete($deleted);

    expect(Artwork::query()->whereKey($deleted->getKey())->exists())->toBeFalse()
        ->and(ArtworkMedia::query()->where('artwork_id', $deleted->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $other->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue();
});

it('preserves replaced MediaAssets in the library even when they become unreferenced', function (): void {
    $artwork = polishArtwork(polishGallery());
    $oldAsset = polishAsset();
    $newAsset = polishAsset();
    polishPrimary($artwork, $oldAsset);

    app(ArtworkPrimaryMediaService::class)->replaceAsset($artwork, $newAsset);

    expect(MediaAsset::query()->whereKey($oldAsset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($newAsset->getKey())->exists())->toBeTrue()
        ->and((int) ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->value('media_asset_id'))
        ->toBe((int) $newAsset->getKey());
});

it('publishes browser-supported video primary media without requiring an image thumbnail', function (): void {
    $artwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);

    $published = app(ArtworkPublicationService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and(app(PublicMedia::class)->kind($published))->toBe('video');
});

it('still requires the canonical public thumbnail for image publication', function (): void {
    $artwork = polishArtwork(polishGallery());
    $image = polishAsset();
    polishPrimary($artwork, $image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image))->toBeFalse();

    polishThumbnail($image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image->fresh('variants')))->toBeTrue();
});

it('stores reusable Material presets without rewriting historical Artwork material text', function (): void {
    $artwork = polishArtwork(polishGallery(), ['medium' => 'Oil on linen']);

    app(ArtworkMaterialPresetService::class)->sync(['Oil on linen', 'Graphite']);
    expect(ArtworkMaterialPreset::query()->orderBy('name')->pluck('name')->all())
        ->toBe(['Graphite', 'Oil on linen']);

    app(ArtworkMaterialPresetService::class)->sync(['Graphite']);

    expect(ArtworkMaterialPreset::query()->pluck('name')->all())->toBe(['Graphite'])
        ->and($artwork->fresh()->medium)->toBe('Oil on linen');
});

it('renders the six Gallery metrics and per-artwork analytics from one canonical reporting call', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery, [
        'state' => 'published',
        'position' => 0,
    ]);
    $image = polishAsset();
    polishPrimary($artwork, $image);
    polishThumbnail($image);

    $reporting = new class((string) $artwork->getAttribute('analytics_key'))
    {
        public int $calls = 0;

        /** @var list<string> */
        public array $keys = [];

        public ?string $path = null;

        public ?string $range = null;

        public function __construct(private readonly string $analyticsKey) {}

        /** @param list<string> $artworkAnalyticsKeys */
        public function gallery(string $publicPath, array $artworkAnalyticsKeys, string $range = '30d'): array
        {
            $this->calls++;
            $this->path = $publicPath;
            $this->keys = $artworkAnalyticsKeys;
            $this->range = $range;

            return [
                'status' => 'available',
                'page' => [
                    'visits' => ['state' => 'available', 'value' => 12.0],
                    'views' => ['state' => 'available', 'value' => 18.0],
                ],
                'artworks' => [
                    'state' => 'available',
                    'rows' => [[
                        'analytics_key' => $this->analyticsKey,
                        'detail_views' => 4,
                        'viewer_opens' => 3,
                        'zooms' => 2,
                        'navigation' => 1,
                        'attention_seconds' => 195.0,
                        'attention_label' => '3m 15s',
                    ]],
                ],
            ];
        }
    };
    app()->instance(ArtistReportingService::class, $reporting);

    $response = $this->get(ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]));

    $response->assertOk()
        ->assertSeeText('Artworks')
        ->assertSeeText('Published')
        ->assertSeeText('Visits')
        ->assertSeeText('Views')
        ->assertSeeText('Artwork opens')
        ->assertSeeText('Attention')
        ->assertSeeText('30d · 4 views · 3 opens · 2 zooms · 3m 15s attention');

    expect($reporting->calls)->toBe(1)
        ->and($reporting->range)->toBe('30d')
        ->and($reporting->path)->toBe('/'.(string) $gallery->getAttribute('slug'))
        ->and($reporting->keys)->toBe([(string) $artwork->getAttribute('analytics_key')]);
});

it('keeps Gallery upload and Edit integration on the canonical media and Filament modal paths', function (): void {
    $projectionSource = file_get_contents(app_path('Filament/Resources/Artworks/Pages/Concerns/GalleryWorkspaceDataProjection.php'));
    $uploadSource = file_get_contents(app_path('Filament/Resources/Artworks/Pages/Concerns/GalleryWorkspaceUploadSettings.php'));
    $modalSource = file_get_contents(app_path('Filament/Resources/Artworks/Pages/Concerns/GalleryWorkspaceArtworkModals.php'));
    $viewSource = file_get_contents(resource_path('views/filament/resources/artworks/pages/manage-gallery-artworks.blade.php'));

    expect($projectionSource)->not->toBeFalse()
        ->and($uploadSource)->not->toBeFalse()
        ->and($modalSource)->not->toBeFalse()
        ->and($viewSource)->not->toBeFalse();

    /** @var string $projectionSource */
    /** @var string $uploadSource */
    /** @var string $modalSource */
    /** @var string $viewSource */
    expect(substr_count($projectionSource, 'app(ArtistReportingService::class)->gallery('))->toBe(1)
        ->and($uploadSource)->toContain('app(MediaIngestService::class)->ingest($upload)')
        ->and($uploadSource)->toContain("Notification::make()->title('Media upload failed')")
        ->and($modalSource)->toContain("Action::make('editArtwork')")
        ->and($modalSource)->toContain('ArtworkPrimaryMediaService::class')
        ->and($viewSource)->toContain('accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"')
        ->and($viewSource)->not->toContain('audio/')
        ->and($viewSource)->toContain("mountAction('editArtwork'")
        ->and($viewSource)->not->toContain('Batch actions')
        ->and($viewSource)->not->toContain('Move selected artworks</');
});

it('accepts existing image and video MediaAssets as primary and rejects audio', function (): void {
    $service = app(ArtworkPrimaryMediaService::class);

    $imageArtwork = polishArtwork(polishGallery());
    $image = polishAsset('image/jpeg');
    $service->attachAsset($imageArtwork, $image);

    $videoArtwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    $service->attachAsset($videoArtwork, $video);

    $audioArtwork = polishArtwork(polishGallery());
    $audio = polishAsset('audio/mpeg');

    expect((int) $imageArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $image->getKey())
        ->and((int) $videoArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $video->getKey())
        ->and(fn () => $service->attachAsset($audioArtwork, $audio))->toThrow(ValidationException::class);
});

it('moves an artwork between Galleries without mutating its MediaAsset', function (): void {
    $source = polishGallery();
    $destination = polishGallery();
    $asset = polishAsset();
    $moved = polishArtwork($source, ['position' => 0]);
    $remaining = polishArtwork($source, ['position' => 1]);
    polishPrimary($moved, $asset);

    app(ArtworkGalleryAssignmentService::class)->reassign($moved, $destination);

    expect((int) $moved->fresh()->artwork_category_id)->toBe((int) $destination->getKey())
        ->and((int) $remaining->fresh()->position)->toBe(0)
        ->and((int) ArtworkMedia::query()->where('artwork_id', $moved->getKey())->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});

it('requires unpublish before detach and preserves the historical publication timestamp when unpublishing', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);

    $publication = app(ArtworkPublicationService::class);
    $published = $publication->publish($artwork);
    $publishedAt = $published->published_at?->toISOString();

    expect(fn () => app(ArtworkGalleryAssignmentService::class)->detach($published))->toThrow(ValidationException::class);

    $draft = $publication->unpublish($published);

    expect($draft->state)->toBe('draft')
        ->and($draft->published_at?->toISOString())->toBe($publishedAt);

    app(ArtworkGalleryAssignmentService::class)->detach($draft);
    expect($draft->fresh()->artwork_category_id)->toBeNull();
});

it('rejects audio publication even if an invalid primary usage was inserted outside the canonical service', function (): void {
    $artwork = polishArtwork(polishGallery());
    $audio = polishAsset('audio/mpeg');
    polishPrimary($artwork, $audio);

    $publication = app(ArtworkPublicationService::class);

    expect($publication->mediaReady($audio))->toBeFalse()
        ->and(fn () => $publication->publish($artwork))->toThrow(ValidationException::class);
});

it('renders a published video artwork through the public viewer with native controls and no autoplay', function (): void {
    $artwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);
    $published = app(ArtworkPublicationService::class)->publish($artwork);

    $response = $this->get(route('artworks.show', ['slug' => $published->getAttribute('slug')]));

    $response->assertOk()
        ->assertSee('data-viewer-kind="video"', false)
        ->assertSee('<video', false)
        ->assertSee('controls', false)
        ->assertDontSee('autoplay', false);
});
