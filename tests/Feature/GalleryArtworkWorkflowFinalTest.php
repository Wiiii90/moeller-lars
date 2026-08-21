<?php

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\Artworks\Pages\CreateArtwork;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function finalWorkflowGallery(string $slug, string $state = 'hidden'): ArtworkCategory
{
    $gallery = ArtworkCategory::create([
        'name' => str($slug)->replace('-', ' ')->title()->toString(),
        'slug' => $slug,
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, [
        'state' => $state,
        'show_in_navigation' => false,
    ]);

    return $gallery;
}

function finalWorkflowMedia(string $label = 'Shared artwork image'): MediaAsset
{
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.uniqid('gallery-final-', true).'.jpg',
        'original_filename' => 'gallery-final.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('gallery-final-', true)),
        'state' => 'available',
        'alt_text' => $label,
    ]);
    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.uniqid('gallery-final-', true).'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('gallery-final-variant-', true)),
        'transform_profile' => MediaIngestService::TRANSFORM_PROFILE,
        'state' => 'available',
    ]);

    return $asset;
}

it('reassigns artwork between Galleries while preserving shared media and unrelated Gallery positions', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $source = finalWorkflowGallery('move-source');
    $destination = finalWorkflowGallery('move-destination');
    $untouched = finalWorkflowGallery('move-untouched');

    $moving = Artwork::create([
        'artwork_category_id' => $source->id,
        'slug' => 'moving-artwork',
        'title' => 'Moving artwork',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);
    $sourceSibling = Artwork::create([
        'artwork_category_id' => $source->id,
        'slug' => 'source-sibling',
        'title' => 'Source sibling',
        'state' => 'draft',
        'position' => 20,
        'date_precision' => 'unknown',
    ]);
    $destinationExisting = Artwork::create([
        'artwork_category_id' => $destination->id,
        'slug' => 'destination-existing',
        'title' => 'Destination existing',
        'state' => 'draft',
        'position' => 50,
        'date_precision' => 'unknown',
    ]);
    $untouchedArtwork = Artwork::create([
        'artwork_category_id' => $untouched->id,
        'slug' => 'untouched-artwork',
        'title' => 'Untouched artwork',
        'state' => 'draft',
        'position' => 77,
        'date_precision' => 'unknown',
    ]);

    $sharedAsset = finalWorkflowMedia();
    ArtworkMedia::create([
        'artwork_id' => $moving->id,
        'media_asset_id' => $sharedAsset->id,
        'role' => 'primary',
        'position' => 0,
    ]);
    ArtworkMedia::create([
        'artwork_id' => $untouchedArtwork->id,
        'media_asset_id' => $sharedAsset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    app(ArtworkGalleryAssignmentService::class)->reassign($moving, $destination);

    expect((int) $moving->fresh()->artwork_category_id)->toBe((int) $destination->id)
        ->and((int) $sourceSibling->fresh()->position)->toBe(0)
        ->and((int) $destinationExisting->fresh()->position)->toBe(0)
        ->and((int) $moving->fresh()->position)->toBe(1)
        ->and((int) $untouchedArtwork->fresh()->position)->toBe(77)
        ->and($sharedAsset->fresh()->state)->toBe('available')
        ->and(ArtworkMedia::query()->where('media_asset_id', $sharedAsset->id)->count())->toBe(2)
        ->and(ArtworkMedia::query()->where('artwork_id', $moving->id)->value('media_asset_id'))->toBe($sharedAsset->id)
        ->and(AuditEvent::query()
            ->where('action', 'artwork.updated')
            ->where('entity_id', $moving->id)
            ->where('admin_user_id', $admin->id)
            ->exists())->toBeTrue();
});

it('keeps a published artwork in place when the destination Gallery is not published', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $source = finalWorkflowGallery('published-source', 'published');
    $destination = finalWorkflowGallery('hidden-destination', 'hidden');
    $artwork = Artwork::create([
        'artwork_category_id' => $source->id,
        'slug' => 'published-move',
        'title' => 'Published move',
        'state' => 'published',
        'position' => 3,
        'date_precision' => 'unknown',
    ]);

    expect(fn () => app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination))
        ->toThrow(ValidationException::class);

    expect((int) $artwork->fresh()->artwork_category_id)->toBe((int) $source->id)
        ->and((int) $artwork->fresh()->position)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->exists())->toBeFalse();
});

it('shows publication readiness from eager-loaded primary thumbnail data and keeps edit links in Gallery context', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $gallery = finalWorkflowGallery('ready-gallery', 'published');
    finalWorkflowGallery('ready-target');
    $artwork = Artwork::create([
        'artwork_category_id' => $gallery->id,
        'slug' => 'ready-artwork',
        'title' => 'Ready artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    $asset = finalWorkflowMedia('Ready artwork ALT');
    ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    $editUrl = ArtworkResource::getUrl('edit', [
        'record' => $artwork->id,
        'gallery' => $gallery->id,
    ]);

    $this->get(ArtworkResource::getUrl('gallery', ['gallery' => $gallery->id]))
        ->assertSuccessful()
        ->assertSee('Ready to publish')
        ->assertSee($editUrl, false)
        ->assertSee('Move to Gallery');
});

it('returns create and Gallery-originated edit saves to the owning Gallery workspace', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $gallery = finalWorkflowGallery('context-gallery', 'published');
    $galleryUrl = ArtworkResource::getUrl('gallery', ['gallery' => $gallery->id]);

    Livewire::withQueryParams(['gallery' => $gallery->id])
        ->test(CreateArtwork::class)
        ->fillForm([
            'title' => 'Context artwork',
            'slug' => 'context-artwork',
            'artwork_category_id' => $gallery->id,
            'work_date' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect($galleryUrl);

    $artwork = Artwork::query()->where('slug', 'context-artwork')->firstOrFail();

    Livewire::withQueryParams(['gallery' => $gallery->id])
        ->test(EditArtwork::class, ['record' => $artwork->id])
        ->fillForm(['title' => 'Context artwork edited'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect($galleryUrl);
});
