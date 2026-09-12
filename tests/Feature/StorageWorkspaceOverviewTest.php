<?php

use App\Domain\Media\MediaCapacityService;
use App\Filament\Support\StorageWorkspaceOverview;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('storage-overview-test');
    config([
        'media.disk' => 'storage-overview-test',
        'media.quota_bytes' => 10_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
});

it('keeps authoritative originals without a MediaAsset visible as uncatalogued storage', function (): void {
    Storage::disk(config('media.disk'))->put('originals/catalogued.jpg', 'catalogued');
    Storage::disk(config('media.disk'))->put('originals/orphan.jpg', 'orphan-bytes');

    MediaAsset::query()->create([
        'storage_key' => 'originals/catalogued.jpg',
        'original_filename' => 'catalogued.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen('catalogued'),
        'sha256' => hash('sha256', 'catalogued'),
        'state' => 'available',
        'alt_text' => 'Catalogued image',
    ]);

    $overview = app(StorageWorkspaceOverview::class)->snapshot(measure: true);
    $uncatalogued = collect($overview['breakdown'])->firstWhere('key', 'uncatalogued');

    expect($overview['capacity']['measurement_available'])->toBeTrue()
        ->and($uncatalogued)->toBeArray()
        ->and($uncatalogued['files'])->toBe(1)
        ->and($uncatalogued['bytes'])->toBe(strlen('orphan-bytes'))
        ->and($overview['attention']['uncatalogued_files'])->toBe(1)
        ->and($overview['attention']['uncatalogued_display_bytes'])->not->toBe('0 B');
});
