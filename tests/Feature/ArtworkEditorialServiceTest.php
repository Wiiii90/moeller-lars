<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

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
    expect(AuditEvent::query()->where('action', 'artwork.published')->count())->toBe(1);
});

it('does not change published_at when publishing again', function () {
    $artwork = editorialArtwork(editorialCategory(), ['published_at' => now()->subDay()]);
    attachEditorialPrimary($artwork, editorialAsset());
    $artwork->forceFill(['state' => 'published'])->save();
    $publishedAt = $artwork->published_at;

    $result = app(ArtworkEditorialService::class)->publish($artwork);

    expect($result->published_at->equalTo($publishedAt))->toBeTrue();
    expect(AuditEvent::query()->where('action', 'artwork.published')->count())->toBe(0);
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
    expect(AuditEvent::query()->where('action', 'artwork.unpublished')->count())->toBe(1);
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
    expect(AuditEvent::query()->where('action', 'media.ingested')->first()->metadata)->toBe(['artwork_id' => $artwork->getKey()])
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_attached')->first()->metadata)->toBe(['media_asset_id' => $asset->getKey()]);
});

it('rejects a second primary before ingesting anything', function () {
    Storage::fake('local');
    $artwork = editorialArtwork(editorialCategory('hidden'));
    attachEditorialPrimary($artwork, editorialAsset());

    expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::query()->count())->toBe(1);
    expect(AuditEvent::query()->count())->toBe(0);
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
        ->and(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('rolls back media attachment and cleanup when an audit insert fails', function () {
    Storage::fake('local');
    $artwork = editorialArtwork(editorialCategory('hidden'));
    AuditEvent::creating(fn (): never => throw new RuntimeException('audit failed'));

    try {
        expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))
            ->toThrow(RuntimeException::class, 'audit failed');
    } finally {
        AuditEvent::flushEventListeners();
    }

    expect(ArtworkMedia::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('denies unauthenticated and non-admin publish and attach mutations', function () {
    $artwork = editorialArtwork(editorialCategory());
    Auth::guard('web')->logout();

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))->toThrow(AuthorizationException::class);
    expect($artwork->fresh()->state)->toBe('draft')->and(MediaAsset::query()->count())->toBe(0)->and(AuditEvent::query()->count())->toBe(0);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, editorialJpegUpload()))->toThrow(AuthorizationException::class);
    expect($artwork->fresh()->state)->toBe('draft')->and(MediaAsset::query()->count())->toBe(0)->and(AuditEvent::query()->count())->toBe(0);
});
