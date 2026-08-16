<?php

use App\Domain\Media\MediaIntegrityService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function integrityAsset(string $state = 'available'): MediaAsset
{
    return MediaAsset::create(['storage_key' => 'originals/integrity-'.uniqid().'.txt', 'original_filename' => 'integrity.txt', 'mime_type' => 'text/plain', 'byte_size' => 4, 'sha256' => hash('sha256', 'orig'), 'state' => $state, 'width' => 2, 'height' => 2]);
}

it('reports a clean asset and variant', function () {
    Storage::fake(config('media.disk'));
    $asset = integrityAsset();
    $variant = MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'other', 'storage_key' => 'variants/integrity.txt', 'mime_type' => 'text/plain', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'other', 'state' => 'available', 'width' => 2, 'height' => 2]);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'var');
    expect(app(MediaIntegrityService::class)->issues($asset))->toBe([]);
});

it('detects original and derivative integrity mismatches', function () {
    Storage::fake(config('media.disk'));
    $asset = integrityAsset();
    $variant = MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/integrity.webp', 'mime_type' => 'image/webp', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'public-v1', 'state' => 'available', 'width' => 2, 'height' => 2]);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'bad');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'bad');
    $issues = app(MediaIntegrityService::class)->issues($asset);
    expect($issues)->toContain('original_checksum_mismatch', 'variant:'.$variant->id.':checksum_mismatch');
});

it('handles missing, stale, and deleted files with stable issue codes', function () {
    Storage::fake(config('media.disk'));
    $asset = integrityAsset('deleted');
    $deletedVariant = MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/deleted.webp', 'mime_type' => 'image/webp', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'public-v1', 'state' => 'deleted', 'width' => 2, 'height' => 2]);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orphan');
    Storage::disk(config('media.disk'))->put($deletedVariant->storage_key, 'orphan');
    expect(app(MediaIntegrityService::class)->issues($asset))->toContain('deleted_original_present', 'variant:'.$deletedVariant->id.':deleted_file_present');

    $available = integrityAsset();
    $stale = MediaVariant::create(['media_asset_id' => $available->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/stale.webp', 'mime_type' => 'image/webp', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'public-v1', 'state' => 'stale', 'width' => 2, 'height' => 2]);
    expect(app(MediaIntegrityService::class)->issues($available))->toContain('original_missing')->not->toContain('variant:'.$stale->id.':missing');
});

it('checks public thumbnail MIME and dimensions', function () {
    Storage::fake(config('media.disk'));
    $asset = integrityAsset();
    $variant = MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/public.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'public-v1', 'state' => 'available', 'width' => 961, 'height' => 2]);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'var');
    expect(app(MediaIntegrityService::class)->issues($asset))->toContain('variant:'.$variant->id.':public_thumbnail_mime_invalid', 'variant:'.$variant->id.':public_thumbnail_dimensions_invalid');
});
