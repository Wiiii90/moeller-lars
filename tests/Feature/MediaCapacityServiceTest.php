<?php

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaStorageUnits;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('measures authoritative originals separately from rebuildable variants', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);

    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 90));
    Storage::disk('media-capacity')->put('variants/one-thumbnail.webp', str_repeat('b', 250));

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot)
        ->configured->toBeTrue()
        ->configuration_valid->toBeTrue()
        ->measurement_available->toBeTrue()
        ->status->toBe('near_capacity')
        ->quota_bytes->toBe(100)
        ->authoritative_bytes->toBe(90)
        ->generated_bytes->toBe(250)
        ->managed_bytes->toBe(340)
        ->remaining_bytes->toBe(10)
        ->original_files->toBe(1)
        ->generated_files->toBe(1)
        ->authoritative_file_bytes->toBe(['originals/one.jpg' => 90]);
});

it('accepts the operator quota as positive integer bytes including the five GB policy value', function (): void {
    Storage::fake('media-capacity');
    config([
        'media.disk' => 'media-capacity',
        'media.quota_bytes' => '5000000000',
    ]);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot)
        ->configured->toBeTrue()
        ->configuration_valid->toBeTrue()
        ->quota_bytes->toBe(5_000_000_000)
        ->status->toBe('healthy');
});

it('uses decimal storage units consistently', function (): void {
    expect(MediaStorageUnits::DECIMAL_GIGABYTE_BYTES)->toBe(1_000_000_000)
        ->and(MediaStorageUnits::formatBytes(5_000_000_000))->toBe('5 GB')
        ->and(MediaStorageUnits::formatBytes(1_073_741_824))->toBe('1.07 GB')
        ->and(MediaStorageUnits::formatBytes(999_000_000))->toBe('999 MB');
});

it('uses the 85 percent warning boundary and marks exhausted allowance full', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    $disk = Storage::disk('media-capacity');

    $disk->put('originals/one.jpg', str_repeat('a', 84));
    expect(app(MediaCapacityService::class)->snapshot()['status'])->toBe('healthy');

    $disk->put('originals/one.jpg', str_repeat('a', 85));
    expect(app(MediaCapacityService::class)->snapshot()['status'])->toBe('near_capacity');

    $disk->put('originals/one.jpg', str_repeat('a', 100));
    expect(app(MediaCapacityService::class)->snapshot()['status'])->toBe('full');
});

it('fails closed when a non-empty operator quota is invalid', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => '5GB']);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot)
        ->configured->toBeTrue()
        ->configuration_valid->toBeFalse()
        ->measurement_available->toBeFalse()
        ->status->toBe('unavailable')
        ->quota_bytes->toBeNull();

    expect(fn () => app(MediaCapacityService::class)->assertCanStoreOriginal(1))
        ->toThrow(ValidationException::class, 'Storage allowance configuration could not be verified.');
});

it('caches presentation measurements without weakening fresh upload admission', function (): void {
    Storage::fake('media-capacity');
    Cache::flush();
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 20));

    $capacity = app(MediaCapacityService::class);
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);

    Storage::disk('media-capacity')->put('originals/two.jpg', str_repeat('b', 70));
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);
    expect(fn () => $capacity->assertCanStoreOriginal(20))
        ->toThrow(ValidationException::class, 'The media storage allowance is full.');

    $capacity->forgetCachedSnapshot();
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(90);
});

it('allows an exact-fit original and blocks the first byte beyond the allowance', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 40));

    app(MediaCapacityService::class)->assertCanStoreOriginal(60);

    expect(fn () => app(MediaCapacityService::class)->assertCanStoreOriginal(61))
        ->toThrow(ValidationException::class, 'The media storage allowance is full.');
});

it('blocks new original media before writing when the configured allowance is exhausted', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 1]);

    $upload = UploadedFile::fake()->image('blocked.jpg', 32, 32);

    expect(fn () => app(MediaIngestService::class)->ingest($upload))
        ->toThrow(ValidationException::class, 'The media storage allowance is full.');

    expect(Storage::disk('media-capacity')->allFiles())->toBe([]);
    expect(MediaAsset::query()->count())->toBe(0);
});

it('does not treat rebuildable derivative bytes as authoritative allowance usage', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);

    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 40));
    Storage::disk('media-capacity')->put('variants/one-thumbnail.webp', str_repeat('b', 10_000));

    app(MediaCapacityService::class)->assertCanStoreOriginal(60);

    expect(app(MediaCapacityService::class)->snapshot()['remaining_bytes'])->toBe(60);
});

it('keeps existing public originals readable when the allowance is exhausted', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 1]);

    $category = ArtworkCategory::query()->create([
        'slug' => 'quota-existing-read',
        'name' => 'Quota existing read',
        'state' => 'published',
        'position' => 0,
    ]);
    testGallerySection($category, ['state' => 'published']);

    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'quota-existing-read',
        'title' => 'Quota existing read',
        'state' => 'published',
        'published_at' => now(),
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $contents = 'existing authoritative media';
    $storageKey = 'originals/existing.jpg';
    Storage::disk('media-capacity')->put($storageKey, $contents);
    $asset = MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => 'existing.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'state' => 'available',
        'alt_text' => 'Existing media',
        'width' => 10,
        'height' => 10,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    expect(app(MediaCapacityService::class)->snapshot()['status'])->toBe('full');
    $this->get(route('media.original', $asset))->assertSuccessful();
});
