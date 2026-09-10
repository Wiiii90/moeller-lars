<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function editorialCategory(string $state = 'published'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill([
        'slug' => 'test-category-'.fake()->unique()->uuid(),
        'name' => 'Test category',
        'show_on_home' => false,
    ]);
    $category->save();
    testGallerySection($category, ['state' => $state === 'published' ? 'published' : 'hidden']);

    return $category;
}

function editorialArtwork(ArtworkCategory $category, array $attributes = []): Artwork
{
    $artwork = new Artwork;
    $artwork->fill(array_merge([
        'artwork_category_id' => $category->getKey(),
        'slug' => fake()->unique()->slug(),
        'title' => 'Test artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ], $attributes));
    $artwork->save();

    return $artwork;
}

function editorialAsset(string $state = 'available'): MediaAsset
{
    $asset = new MediaAsset;
    $asset->fill([
        'storage_key' => 'originals/test-'.fake()->unique()->uuid.'.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 3,
        'sha256' => str_repeat('a', 64),
        'state' => $state,
        'alt_text' => 'Test asset ALT',
        'width' => 2,
        'height' => 2,
    ]);
    $asset->save();

    return $asset;
}

function attachEditorialPrimary(Artwork $artwork, MediaAsset $asset): ArtworkMedia
{
    return ArtworkMedia::create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
}

function editorialVariant(MediaAsset $asset): MediaVariant
{
    return MediaVariant::create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.fake()->unique()->uuid().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'var'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 2,
        'height' => 2,
    ]);
}

it('publishes only an artwork with a published gallery and one available primary asset', function (): void {
    $artwork = editorialArtwork(editorialCategory());
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);
    editorialVariant($asset);

    $published = app(ArtworkEditorialService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull();
});

it('keeps an existing publication timestamp stable', function (): void {
    $artwork = editorialArtwork(editorialCategory(), ['published_at' => now()->subDay()]);
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);
    editorialVariant($asset);
    $artwork->forceFill(['state' => 'published'])->save();
    $publishedAt = $artwork->fresh()->published_at;

    $result = app(ArtworkEditorialService::class)->publish($artwork);

    expect($result->published_at->equalTo($publishedAt))->toBeTrue();
});

it('rejects publication when gallery or primary media is not publishable', function (string $case): void {
    $category = editorialCategory($case === 'hidden' ? 'hidden' : 'published');
    $artwork = editorialArtwork($category);

    if ($case === 'unavailable') {
        attachEditorialPrimary($artwork, editorialAsset('quarantined'));
    }

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))
        ->toThrow(ValidationException::class);
    expect($artwork->fresh()->state)->toBe('draft');
})->with(['hidden', 'missing', 'unavailable']);

it('rejects publication without canonical ALT or a public thumbnail', function (string $case): void {
    $artwork = editorialArtwork(editorialCategory());
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);

    if ($case === 'missing-alt') {
        $asset->update(['alt_text' => null]);
        editorialVariant($asset);
    }

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))
        ->toThrow(ValidationException::class);
    expect($artwork->fresh()->state)->toBe('draft');
})->with(['missing-alt', 'missing-thumbnail']);

it('unpublishes without erasing the historical publication timestamp', function (): void {
    $artwork = editorialArtwork(editorialCategory(), [
        'state' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $publishedAt = $artwork->fresh()->published_at;

    $result = app(ArtworkEditorialService::class)->unpublish($artwork);

    expect($result->state)->toBe('draft')
        ->and($result->published_at->equalTo($publishedAt))->toBeTrue();
});
