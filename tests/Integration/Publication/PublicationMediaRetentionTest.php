<?php

use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicationCheckpoint;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
});

it('retains files referenced by a retained publication snapshot after a later Commit', function (): void {
    Storage::fake(config('media.disk'));

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/committed-delete.jpg',
        'original_filename' => 'committed-delete.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'orig'),
        'state' => 'available',
        'alt_text' => 'Committed delete',
    ]);
    $variant = MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/committed-delete.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 7,
        'sha256' => hash('sha256', 'variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    Storage::disk(config('media.disk'))->put($asset->getAttribute('storage_key'), 'orig');
    Storage::disk(config('media.disk'))->put($variant->getAttribute('storage_key'), 'variant');

    expect(app(PublicationService::class)->commit($this->actor))->toBeInstanceOf(PublicationCheckpoint::class);

    app(MediaAssetEditorialService::class)->delete($asset);

    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertExists($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(2)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    expect(app(PublicationService::class)->commit($this->actor))->toBeInstanceOf(PublicationCheckpoint::class);

    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertExists($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(2);
});


it('retains working-only media while a Working reference still needs the file', function (): void {
    Storage::fake(config('media.disk'));

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/working-reference.jpg',
        'original_filename' => 'working-reference.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'orig'),
        'state' => 'available',
        'alt_text' => 'Working reference',
    ]);
    Storage::disk(config('media.disk'))->put($asset->getAttribute('storage_key'), 'orig');

    $settings = PublicContentSetting::general();
    $settings->setAttribute('favicon_media_asset_id', $asset->getKey());
    $settings->save();
    $asset->setAttribute('state', 'deleted');
    $asset->save();

    $cleanup = app(PublicationMediaCleanupService::class);
    $cleanup->queue((int) $asset->getKey(), [(string) $asset->getAttribute('storage_key')]);
    $cleanup->drain();

    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(1);

    $settings->setAttribute('favicon_media_asset_id', null);
    $settings->save();
    $cleanup->drain();

    Storage::disk(config('media.disk'))->assertMissing($asset->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(0);
});
