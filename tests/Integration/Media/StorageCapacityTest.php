<?php

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIngestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('storage-capacity-mutation-test');
    config([
        'media.disk' => 'storage-capacity-mutation-test',
        'media.quota_bytes' => 5_000_000_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
});

it('refreshes the warmed capacity snapshot after a successful ingest', function (): void {
    $capacity = app(MediaCapacityService::class);
    $before = $capacity->refreshCachedSnapshot();

    expect($before['authoritative_bytes'])->toBe(0)
        ->and($before['generated_bytes'])->toBe(0);

    $asset = app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->image('fresh-storage.jpg', 32, 32),
    );

    $after = $capacity->cachedSnapshotIfAvailable();

    expect($after)->toBeArray()
        ->and($after['measurement_available'])->toBeTrue()
        ->and($after['authoritative_bytes'])->toBe((int) $asset->getAttribute('byte_size'))
        ->and($after['generated_bytes'])->toBeGreaterThan(0)
        ->and($after['authoritative_file_bytes'])->toHaveKey((string) $asset->getAttribute('storage_key'));
});
