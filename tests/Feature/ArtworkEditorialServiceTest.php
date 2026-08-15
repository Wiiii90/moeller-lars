<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function editorialCategory(string $state = 'published'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill(['slug' => fake()->unique()->slug(), 'name' => 'Test category', 'state' => $state, 'position' => 0]);
    $category->save();

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
        'width' => 2,
        'height' => 2,
    ]);
    $asset->save();

    return $asset;
}

function attachEditorialPrimary(Artwork $artwork, MediaAsset $asset): ArtworkMedia
{
    $media = new ArtworkMedia;
    $media->fill([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
    $media->save();

    return $media;
}

function editorialJpegUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'artwork-');
    $image = imagecreatetruecolor(8, 6);
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent('artwork.jpg', file_get_contents($path));
}

it('publishes only an artwork with a published category and one available primary asset', function () {
    $artwork = editorialArtwork(editorialCategory());
    attachEditorialPrimary($artwork, editorialAsset());

    $published = app(ArtworkEditorialService::class)->publish($artwork);

    expect($published->state)->toBe('published')->and($published->published_at)->not->toBeNull();
});

it('does not change published_at when publishing again', function () {
    $artwork = editorialArtwork(editorialCategory(), ['published_at' => now()->subDay()]);
    attachEditorialPrimary($artwork, editorialAsset());
    $artwork->forceFill(['state' => 'published'])->save();
    $publishedAt = $artwork->published_at;

    $result = app(ArtworkEditorialService::class)->publish($artwork);

    expect($result->published_at->equalTo($publishedAt))->toBeTrue();
});

it('rejects hidden categories, missing primaries, and unavailable assets', function (string $case) {
    $category = editorialCategory($case === 'hidden' ? 'hidden' : 'published');
    $artwork = editorialArtwork($category);
    if ($case === 'unavailable') {
        attachEditorialPrimary($artwork, editorialAsset('quarantined'));
    }

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))
        ->toThrow(ValidationException::class);
    expect($artwork->fresh()->state)->toBe('draft');
})->with(['hidden', 'missing', 'unavailable']);

it('unpublishes without clearing published_at', function () {
    $artwork = editorialArtwork(editorialCategory(), ['state' => 'published', 'published_at' => now()->subDay()]);
    $publishedAt = $artwork->published_at;

    $result = app(ArtworkEditorialService::class)->unpublish($artwork);

    expect($result->state)->toBe('draft')->and($result->published_at->equalTo($publishedAt))->toBeTrue();
});

it('ingests and attaches one primary media record', function () {
    Storage::fake('local');
    $artwork = editorialArtwork(editorialCategory('hidden'));

    $result = app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload());

    $asset = MediaAsset::query()->first();
    expect($result->artworkMedia)->toHaveCount(1)
        ->and($result->artworkMedia->first()->role)->toBe('primary')
        ->and($result->artworkMedia->first()->position)->toBe(0)
        ->and($asset)->not->toBeNull()
        ->and($asset->variants)->toHaveCount(1)
        ->and($asset->variants->first()->transform_profile)->toBe('public-v1');
});

it('rejects a second primary before ingesting anything', function () {
    Storage::fake('local');
    $artwork = editorialArtwork(editorialCategory('hidden'));
    attachEditorialPrimary($artwork, editorialAsset());

    expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::query()->count())->toBe(1);
});

it('cleans the newly ingested media if the artwork media insert fails', function () {
    Storage::fake('local');
    $artwork = editorialArtwork(editorialCategory('hidden'));
    ArtworkMedia::creating(fn (): never => throw new RuntimeException('insert failed'));

    try {
        expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))
            ->toThrow(RuntimeException::class, 'insert failed');
    } finally {
        ArtworkMedia::flushEventListeners();
    }

    expect(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});
