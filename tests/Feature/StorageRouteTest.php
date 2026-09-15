<?php

use App\Domain\Media\MediaCapacityService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('media-download-test');
    config(['media.disk' => 'media-download-test']);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function storageRouteDownloadAsset(
    string $filename,
    string $contents = 'data',
    ?string $storageKey = null,
    string $state = 'available',
): MediaAsset {
    $storageKey ??= 'originals/'.$filename;
    Storage::disk(config('media.disk'))->put($storageKey, $contents);

    return MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'state' => $state,
        'alt_text' => 'Route test',
    ]);
}

it('uses storage as the artist-facing media workspace without measuring on normal navigation', function (): void {
    Storage::disk(config('media.disk'))->put('originals/not-measured.jpg', 'not-measured');

    expect(parse_url(MediaAssetResource::getUrl('index'), PHP_URL_PATH))->toBe('/admin/storage');

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Storage')
        ->assertSee('Measurement needed')
        ->assertSee('Refresh');
});

it('uses a warmed capacity snapshot without measuring during storage navigation', function (): void {
    config(['media.quota_bytes' => 5_000_000_000]);
    Storage::disk(config('media.disk'))->put('originals/warmed.jpg', 'warmed-storage');

    $this->artisan('media:measure-capacity')->assertExitCode(0);

    $snapshot = app(MediaCapacityService::class)->cachedSnapshotIfAvailable();
    expect($snapshot)->toBeArray()
        ->and($snapshot['measurement_available'])->toBeTrue()
        ->and($snapshot['authoritative_bytes'])->toBe(strlen('warmed-storage'));

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Storage healthy')
        ->assertSee('5 GB')
        ->assertDontSee('Measurement needed');
});

it('redirects legacy media workspace URLs to storage', function (): void {
    $asset = storageRouteDownloadAsset('route-test.jpg');

    foreach (['media-files', 'media-assets'] as $legacyPath) {
        $this->get('/admin/'.$legacyPath)
            ->assertRedirect(MediaAssetResource::getUrl('index'));
        $this->get('/admin/'.$legacyPath.'/'.$asset->getKey())
            ->assertRedirect(MediaAssetResource::getUrl('view', ['record' => $asset]));
        $this->get('/admin/'.$legacyPath.'/'.$asset->getKey().'/edit')
            ->assertRedirect(MediaAssetResource::getUrl('edit', ['record' => $asset]));
    }
});

it('downloads the authoritative original as an attachment', function (): void {
    $asset = storageRouteDownloadAsset(
        'original artwork.jpg',
        'authoritative-original',
        'originals/canonical/original-artwork.jpg',
    );

    $response = $this->get(route('admin.media.download', ['mediaAsset' => $asset->id]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($asset->storage_key));

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('original artwork.jpg');
});

it('allows referenced assets to be downloaded', function (): void {
    $asset = storageRouteDownloadAsset('referenced-download.jpg', 'referenced-original');
    $category = ArtworkCategory::query()->create([
        'slug' => 'media-download-reference',
        'name' => 'Media download reference',
    ]);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->id,
        'slug' => 'download-reference',
        'title' => 'Download reference',
        'state' => 'draft',
        'position' => 0,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    $this->get(route('admin.media.download', ['mediaAsset' => $asset->id]))
        ->assertOk()
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($asset->storage_key));
});

it('returns one selected original directly as an attachment', function (): void {
    $asset = storageRouteDownloadAsset('single-selected.jpg', 'single-original');

    $response = $this->get(route('admin.media.download-selected', ['ids' => [$asset->id]]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($asset->storage_key));

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('archives selected authoritative originals without creating MediaAssets and removes the temporary archive', function (): void {
    $first = storageRouteDownloadAsset('duplicate.jpg', 'first-original', 'originals/first/duplicate.jpg');
    $second = storageRouteDownloadAsset('duplicate.jpg', 'second-original', 'originals/second/duplicate.jpg');
    $mediaAssetCount = MediaAsset::query()->count();

    $response = $this->get(route('admin.media.download-selected', ['ids' => [$second->id, $first->id]]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    expect(MediaAsset::query()->count())->toBe($mediaAssetCount);

    $binaryResponse = $response->baseResponse;
    $temporaryPath = $binaryResponse->getFile()->getPathname();
    expect(is_file($temporaryPath))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($temporaryPath))->toBeTrue()
        ->and($zip->numFiles)->toBe(2)
        ->and($zip->getFromName('duplicate.jpg'))->toBe('first-original')
        ->and($zip->getFromName('duplicate (2).jpg'))->toBe('second-original');
    $zip->close();

    ob_start();
    $binaryResponse->sendContent();
    ob_end_clean();

    expect(is_file($temporaryPath))->toBeFalse();
});

it('aborts a selected download when an asset is no longer available', function (): void {
    $available = storageRouteDownloadAsset('available.jpg', 'available-original');
    $unavailable = storageRouteDownloadAsset('quarantined.jpg', 'quarantined-original', null, 'quarantined');

    $this->get(route('admin.media.download-selected', ['ids' => [$available->id, $unavailable->id]]))
        ->assertStatus(409);
});
