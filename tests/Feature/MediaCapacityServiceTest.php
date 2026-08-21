<?php

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIngestService;
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

it('caches presentation measurements without weakening fresh upload admission', function (): void {
    Storage::fake('media-capacity');
    Cache::flush();
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 20));

    $capacity = app(MediaCapacityService::class);
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);

    Storage::disk('media-capacity')->put('originals/two.jpg', str_repeat('b', 70));
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);
    expect(fn () => $capacity->assertCanStoreOriginal(20))->toThrow(ValidationException::class, 'The media storage allowance is full.');

    $capacity->forgetCachedSnapshot();
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(90);
});

it('can read a presentation snapshot without measuring storage on a cache miss', function (): void {
    Storage::fake('media-capacity');
    Cache::flush();
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 20));

    $capacity = app(MediaCapacityService::class);

    expect($capacity->cachedSnapshotIfAvailable())->toBeNull();
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);
    expect($capacity->cachedSnapshotIfAvailable()['authoritative_bytes'])->toBe(20);
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
