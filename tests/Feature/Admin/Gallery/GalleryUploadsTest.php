<?php

use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\Artwork;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('keeps Livewire temporary upload admission aligned to the canonical media policy', function (): void {
    expect(config('livewire.temporary_file_upload.rules'))
        ->toBe('file|max:'.(int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBe(10);
});

it('creates a draft from a completed Gallery upload and publishes its primary ALT description', function (): void {
    Storage::fake(config('media.disk'));
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();

    $upload = UploadedFile::fake()->image('new-artwork.jpg', 48, 32);
    $asset = app(MediaIngestService::class)->ingest($upload);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->call('beginDirectPrimaryMediaBatch')
        ->call('queueResumablePrimaryMediaAsset', $asset->getKey())
        ->call('queueResumablePrimaryMediaAsset', $asset->getKey())
        ->call('finishDirectPrimaryMediaBatch')
        ->assertSet('mountedActions.0.name', 'addArtwork')
        ->fillForm([
            'title' => 'Uploaded Artwork',
            'slug' => 'uploaded-artwork',
            'artwork_date' => null,
            'dimension_unit' => 'cm',
            'primary_alt_text' => 'A painting with red and blue geometric shapes',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('slug', 'uploaded-artwork')->firstOrFail();
    $usage = $artwork->artworkMedia()->where('role', 'primary')->sole();
    expect($usage->mediaAsset->getKey())->toBe($asset->getKey());
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
