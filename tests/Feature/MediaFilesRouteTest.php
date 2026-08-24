<?php

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

beforeEach(function (): void {
    Storage::fake('media-download-test');
    config(['media.disk' => 'media-download-test']);
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function mediaFilesRouteDownloadAsset(
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

it('uses the artist-facing media-files resource route', function (): void {
    expect(parse_url(MediaAssetResource::getUrl('index'), PHP_URL_PATH))->toBe('/admin/media-files');

    $this->get('/admin/media-files')
        ->assertOk()
        ->assertSee('Media Files');
});

it('redirects legacy media-assets index and record URLs to media-files', function (): void {
    $asset = mediaFilesRouteDownloadAsset('route-test.jpg');

    $this->get('/admin/media-assets')
        ->assertRedirect(MediaAssetResource::getUrl('index'));
    $this->get('/admin/media-assets/'.$asset->getKey())
        ->assertRedirect(MediaAssetResource::getUrl('view', ['record' => $asset]));
    $this->get('/admin/media-assets/'.$asset->getKey().'/edit')
        ->assertRedirect(MediaAssetResource::getUrl('edit', ['record' => $asset]));
});

it('downloads the authoritative original as an attachment', function (): void {
    $asset = mediaFilesRouteDownloadAsset(
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
    $asset = mediaFilesRouteDownloadAsset('referenced-download.jpg', 'referenced-original');
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
    $asset = mediaFilesRouteDownloadAsset('single-selected.jpg', 'single-original');

    $response = $this->get(route('admin.media.download-selected', ['ids' => [$asset->id]]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($asset->storage_key));

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('archives selected authoritative originals without creating MediaAssets and removes the temporary archive', function (): void {
    $first = mediaFilesRouteDownloadAsset('duplicate.jpg', 'first-original', 'originals/first/duplicate.jpg');
    $second = mediaFilesRouteDownloadAsset('duplicate.jpg', 'second-original', 'originals/second/duplicate.jpg');
    $mediaAssetCount = MediaAsset::query()->count();

    $response = $this->get(route('admin.media.download-selected', ['ids' => [$second->id, $first->id]]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    expect(MediaAsset::query()->count())->toBe($mediaAssetCount);

    $binaryResponse = $response->baseResponse;
    $temporaryPath = $binaryResponse->getFile()->getPathname();
    expect(is_file($temporaryPath))->toBeTrue();

    $zip = new ZipArchive();
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
    $available = mediaFilesRouteDownloadAsset('available.jpg', 'available-original');
    $unavailable = mediaFilesRouteDownloadAsset('quarantined.jpg', 'quarantined-original', null, 'quarantined');

    $this->get(route('admin.media.download-selected', ['ids' => [$available->id, $unavailable->id]]))
        ->assertStatus(409)
        ->assertSee('One or more selected files are no longer available for download.');
});
