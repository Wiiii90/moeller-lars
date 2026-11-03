<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
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

it('publishes only an artwork with a published gallery and one available primary asset', function (): void {
    $artwork = editorialArtwork(editorialCategory());
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);
    testStoredImageThumbnail($asset);

    $published = app(ArtworkEditorialService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull();
});

it('retains public Gallery visibility when the asset has a single canonical stored thumbnail', function (): void {
    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);
    testStoredImageThumbnail($asset);

    app(ArtworkEditorialService::class)->publish($artwork);
    $public = app(PublicArtworkQuery::class)->publishedBySlug((string) $artwork->getAttribute('slug'));

    expect($public)->not->toBeNull()
        ->and($public?->getKey())->toBe($artwork->getKey());
});

it('keeps an existing publication timestamp stable', function (): void {
    $artwork = editorialArtwork(editorialCategory(), ['published_at' => now()->subDay()]);
    $asset = editorialAsset();
    attachEditorialPrimary($artwork, $asset);
    testStoredImageThumbnail($asset);
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
        testStoredImageThumbnail($asset);
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
