<?php

use App\Domain\Media\MediaRightsArtifactRevision;
use App\Domain\Publication\MediaRightsPublicationSelections;
use App\Domain\Publication\PublicationService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaRightsArtifact;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

it('renders selected rights thumbnail URLs for public and preview artwork detail pages', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', true);

    Storage::fake('artwork-detail-test');
    config(['media.disk' => 'artwork-detail-test']);
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $category = ArtworkCategory::query()->create([
        'slug' => 'thumbnail-rights-gallery',
        'name' => 'Thumbnail rights gallery',
        'show_on_home' => false,
    ]);
    testGallerySection($category);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'thumbnail-rights-artwork',
        'title' => 'Thumbnail rights artwork',
        'state' => 'published',
        'position' => 10,
        'work_year' => 2024,
        'date_precision' => 'year',
    ]);

    $disk = Storage::disk(config('media.disk'));
    $originalBytes = 'original-bytes';
    $thumbnailBytes = 'thumbnail-bytes';
    $rightsBytes = 'rights-thumbnail-bytes';
    $originalKey = 'originals/thumbnail-rights.jpg';
    $thumbnailKey = 'variants/thumbnail-rights.webp';
    $disk->put($originalKey, $originalBytes);
    $disk->put($thumbnailKey, $thumbnailBytes);

    $asset = MediaAsset::query()->create([
        'storage_key' => $originalKey,
        'original_filename' => 'thumbnail-rights.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($originalBytes),
        'sha256' => hash('sha256', $originalBytes),
        'state' => 'available',
        'alt_text' => 'Art image',
        'width' => 600,
        'height' => 900,
    ]);
    $variant = MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'storage_key' => $thumbnailKey,
        'mime_type' => 'image/webp',
        'byte_size' => strlen($thumbnailBytes),
        'sha256' => hash('sha256', $thumbnailBytes),
        'width' => 480,
        'height' => 720,
    ]);
    // The selected immutable identity must match the real source and rights
    // projection; a fabricated revision would be rejected as stale.
    $revision = app(MediaRightsArtifactRevision::class)->forSource($asset, $variant);
    $rightsKey = 'variants/rights/'.$revision.'.webp';
    $disk->put($rightsKey, $rightsBytes);

    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
    $artifact = MediaRightsArtifact::query()->create([
        'media_asset_id' => $asset->getKey(),
        'role' => 'thumbnail',
        'revision' => $revision,
        'storage_key' => $rightsKey,
        'source_sha256' => $variant->getAttribute('sha256'),
        'sha256' => hash('sha256', $rightsBytes),
        'byte_size' => strlen($rightsBytes),
        'mime_type' => 'image/webp',
        'created_at' => now(),
    ]);
    app(MediaRightsPublicationSelections::class)->selectWorking($artifact, 'thumbnail', (int) $variant->getKey());
    expect(app(PublicationService::class)->commit($actor, 'Artwork rights URL test'))->not->toBeNull();

    $publicUrl = route('media.variant', ['mediaVariant' => $variant, 'revision' => $revision]);
    $previewUrl = route('preview.media.variant', ['mediaVariant' => $variant, 'revision' => $revision]);
    $bareUrl = route('media.variant', ['mediaVariant' => $variant]);

    $this->get(route('preview.artworks.show', ['slug' => $artwork->slug]))
        ->assertOk()
        ->assertSee('src="'.$previewUrl.'"', false);
    $this->get($previewUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');

    Auth::guard('web')->logout();
    $this->get(route('artworks.show', ['slug' => $artwork->slug]))
        ->assertOk()
        ->assertSee('src="'.$publicUrl.'"', false)
        ->assertDontSee('src="'.$bareUrl.'"', false);
    $this->get($bareUrl)->assertNotFound();
    $this->get($publicUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');
});
