<?php

use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\PublicMedia;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('keeps Livewire temporary upload admission aligned to the canonical media policy', function (): void {
    expect(config('livewire.temporary_file_upload.rules'))
        ->toBe('file|max:'.(int) ceil(\App\Domain\Media\MediaTypePolicy::maxUploadBytes() / 1024))
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBe(10);
});

it('ingests a real image through Add artwork and publishes its primary ALT description', function (): void {
    Storage::fake(config('media.disk'));
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('addArtwork')
        ->fillForm([
            'title' => 'Uploaded Artwork',
            'slug' => 'uploaded-artwork',
            'work_date' => null,
            'dimension_unit' => 'cm',
            'primary_upload' => UploadedFile::fake()->image('new-artwork.jpg', 48, 32),
            'primary_alt_text' => 'A painting with red and blue geometric shapes',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('slug', 'uploaded-artwork')->firstOrFail();
    $usage = $artwork->artworkMedia()->where('role', 'primary')->sole();
    $asset = $usage->mediaAsset;
    $thumbnail = $asset->variants()->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)->sole();

    expect($artwork->state)->toBe('draft')
        ->and($usage->alt_text_override)->toBe('A painting with red and blue geometric shapes')
        ->and($asset->state)->toBe('available')
        ->and($asset->alt_text)->toBeNull()
        ->and($thumbnail->state)->toBe('available');

    Storage::disk(config('media.disk'))->assertExists([$asset->storage_key, $thumbnail->storage_key]);

    expect(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published')
        ->and(app(PublicMedia::class)->altText($artwork->fresh(['artworkMedia.mediaAsset.variants'])))
        ->toBe('A painting with red and blue geometric shapes');

    $this->get(route('preview.site.section', ['section' => $gallery->slug]))
        ->assertOk()
        ->assertSee('Uploaded Artwork');
});


it('replaces primary media through a committed Gallery command instead of Edit autosave', function (): void {
    Storage::fake(config('media.disk'));
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $oldAsset = galleryFixtureAsset('image/jpeg', 'Previous file description');
    galleryFixtureThumbnail($oldAsset);
    $previousPrimary = galleryFixturePrimary($artwork, $oldAsset);
    $previousPrimary->forceFill(['alt_text_override' => 'Previous artwork description'])->save();
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('uploadArtworkPrimaryMedia', ['artwork' => $artwork->getKey()])
        ->fillForm([
            'primary_upload' => UploadedFile::fake()->image('replacement-artwork.jpg', 40, 48),
            'primary_alt_text' => 'A new painting with a yellow circular motif',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $usage = $artwork->artworkMedia()->where('role', 'primary')->sole();
    $newAsset = $usage->mediaAsset;
    $thumbnail = $newAsset->variants()->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)->sole();

    expect($usage->media_asset_id)->not->toBe($oldAsset->getKey())
        ->and($usage->alt_text_override)->toBe('A new painting with a yellow circular motif')
        ->and($oldAsset->fresh()->state)->toBe('available')
        ->and($newAsset->state)->toBe('available')
        ->and($thumbnail->state)->toBe('available')
        ->and(AdminNotification::query()->where('title', 'Primary media updated')->count())->toBe(1);

    Storage::disk(config('media.disk'))->assertExists([$newAsset->storage_key, $thumbnail->storage_key]);

    expect(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});


it('turns a real direct Gallery upload into an editable and publishable artwork draft', function (): void {
    Storage::fake(config('media.disk'));
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();

    $component = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('directPrimaryMedia', [UploadedFile::fake()->image('direct-artwork.jpg', 56, 40)])
        ->call('processDirectPrimaryMedia')
        ->assertHasNoErrors();

    $pendingAssetId = $component->get('pendingPrimaryMediaAssetId');
    expect($pendingAssetId)->toBeInt()->toBeGreaterThan(0);

    $component->fillForm([
        'title' => 'Direct Upload Artwork',
        'slug' => 'direct-upload-artwork',
        'dimension_unit' => 'cm',
        'work_date' => null,
        'primary_media_asset_id' => $pendingAssetId,
        'primary_alt_text' => 'A mixed-media artwork with a bright turquoise border',
    ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('slug', 'direct-upload-artwork')->firstOrFail();
    $usage = $artwork->artworkMedia()->where('role', 'primary')->sole();
    expect((int) $usage->media_asset_id)->toBe($pendingAssetId)
        ->and($usage->alt_text_override)->toBe('A mixed-media artwork with a bright turquoise border')
        ->and(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});
