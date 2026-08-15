<?php

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;

function sliceCategory(string $slug, string $state = 'published'): ArtworkCategory
{
    return ArtworkCategory::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'state' => $state,
        'position' => 0,
    ]);
}

function sliceArtwork(ArtworkCategory $category, array $attributes = []): Artwork
{
    return Artwork::create(array_merge([
        'artwork_category_id' => $category->id,
        'slug' => 'slice-'.uniqid(),
        'title' => 'Slice artwork',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ], $attributes));
}

function sliceAsset(array $attributes = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'slice.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => str_repeat('a', 64),
        'state' => 'available',
        'alt_text' => 'Asset alt',
    ], $attributes));
}

function attachPrimary(Artwork $artwork, MediaAsset $asset, ?string $alt = null): void
{
    ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
        'alt_text_override' => $alt,
    ]);
}

it('filters public artwork and orders by date then position', function () {
    $category = sliceCategory('paintings');
    sliceArtwork($category, ['slug' => 'old', 'work_date' => '2020-01-01', 'position' => 0]);
    sliceArtwork($category, ['slug' => 'same-date-first', 'work_date' => '2025-01-01', 'position' => 1]);
    sliceArtwork($category, ['slug' => 'same-date-second', 'work_date' => '2025-01-01', 'position' => 2]);
    sliceArtwork($category, ['slug' => 'draft', 'state' => 'draft', 'work_date' => '2030-01-01']);
    $hidden = sliceCategory('prints', 'hidden');
    sliceArtwork($hidden, ['slug' => 'hidden-category', 'work_date' => '2030-01-01']);

    expect(app(PublicArtworkQuery::class)->category('paintings')->pluck('slug')->all())
        ->toBe(['same-date-first', 'same-date-second', 'old']);
});

it('serves canonical category, home, direct artwork, and legacy routes', function () {
    $paintings = sliceCategory('paintings');
    sliceArtwork($paintings, ['slug' => 'home-work', 'work_date' => '2026-01-01']);
    foreach (PublicArtworkQuery::CATEGORY_SLUGS as $slug) {
        if ($slug !== 'paintings') {
            sliceCategory($slug);
        }
        $this->get('/'.$slug)->assertSuccessful();
    }

    $this->get('/')->assertSuccessful()->assertSee('home-work');
    $this->get('/artworks/home-work')->assertSuccessful();
    $this->get('/artworks/not-public')->assertNotFound();
    $this->get('/index.php')->assertMovedPermanently()->assertRedirect('/');
    $this->get('/index.php?site=paintings')->assertMovedPermanently()->assertRedirect('/paintings');
    $this->get('/index.php?site=links')->assertNotFound();
    $this->get('/index.php?site=%3Cscript%3E')->assertNotFound();
});

it('resolves public media precedence and controlled delivery', function () {
    Storage::fake('local');
    $category = sliceCategory('drawings');
    $artwork = sliceArtwork($category, ['slug' => 'media-work', 'title' => 'Title alt']);
    $asset = sliceAsset(['storage_key' => 'originals/media.jpg']);
    attachPrimary($artwork, $asset, 'Override alt');
    $variant = MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/media.jpg',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => str_repeat('b', 64),
        'transform_profile' => PublicMedia::PUBLIC_TRANSFORM_PROFILE,
        'state' => 'available',
    ]);
    Storage::disk('local')->put($asset->storage_key, 'orig');
    Storage::disk('local')->put($variant->storage_key, 'thumb');

    $media = app(PublicMedia::class);
    expect($media->altText($artwork->fresh()))->toBe('Override alt')
        ->and($media->thumbnailUrl($artwork->fresh()))->toBe(route('media.variant', $variant));

    $response = $this->get(route('media.variant', $variant));
    $response->assertOk()->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});
