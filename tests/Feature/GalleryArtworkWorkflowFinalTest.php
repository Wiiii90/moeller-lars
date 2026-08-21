<?php

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function assignmentGallery(string $slug, string $state = 'hidden'): ArtworkCategory
{
    $gallery = ArtworkCategory::create([
        'name' => str($slug)->replace('-', ' ')->title()->toString(),
        'slug' => $slug,
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, ['state' => $state, 'show_in_navigation' => false]);

    return $gallery;
}

function assignmentMedia(): MediaAsset
{
    return MediaAsset::create([
        'storage_key' => 'originals/'.uniqid('assignment-', true).'.jpg',
        'original_filename' => 'assignment.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('assignment-', true)),
        'state' => 'available',
        'alt_text' => 'Shared artwork image',
    ]);
}

it('reassigns artwork without losing shared media or changing unrelated Gallery order', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $source = assignmentGallery('move-source');
    $destination = assignmentGallery('move-destination');
    $untouched = assignmentGallery('move-untouched');

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

    $sharedAsset = assignmentMedia();
    ArtworkMedia::create(['artwork_id' => $moving->id, 'media_asset_id' => $sharedAsset->id, 'role' => 'primary', 'position' => 0]);
    ArtworkMedia::create(['artwork_id' => $untouchedArtwork->id, 'media_asset_id' => $sharedAsset->id, 'role' => 'primary', 'position' => 0]);

    app(ArtworkGalleryAssignmentService::class)->reassign($moving, $destination);

    expect((int) $moving->fresh()->artwork_category_id)->toBe((int) $destination->id)
        ->and((int) $sourceSibling->fresh()->position)->toBe(0)
        ->and((int) $destinationExisting->fresh()->position)->toBe(0)
        ->and((int) $moving->fresh()->position)->toBe(1)
        ->and((int) $untouchedArtwork->fresh()->position)->toBe(77)
        ->and($sharedAsset->fresh()->state)->toBe('available')
        ->and(ArtworkMedia::query()->where('media_asset_id', $sharedAsset->id)->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->where('entity_id', $moving->id)->exists())->toBeTrue();
});

it('rejects moving a published artwork into a hidden Gallery without mutation', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $source = assignmentGallery('published-source', 'published');
    $destination = assignmentGallery('hidden-destination', 'hidden');
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
        ->and((int) $artwork->fresh()->position)->toBe(3);
});
