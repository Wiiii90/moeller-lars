<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaIntegrityService;
use App\Domain\Media\MediaVariantRegenerationService;
use App\Domain\Media\PublicMedia;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Storage\SiteStorageDatabaseUsageService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function lifecycleImageUpload(string $mime, int $width = 120, int $height = 80, string $name = 'upload.jpg'): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 20, 80, 160);
    imagefill($image, 0, 0, $background);

    ob_start();
    match ($mime) {
        'image/jpeg' => imagejpeg($image, null, 100),
        'image/png' => imagepng($image, null, 0),
        'image/webp' => imagewebp($image, null, 100),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

function lifecycleIngestService(): MediaIngestService
{
    Storage::fake(config('media.disk'));

    return app(MediaIngestService::class);
}

function lifecycleAsset(): MediaAsset
{
    // Multiple assets in one isolated test stay distinct without wall-clock IDs.
    $suffix = (string) (((int) MediaAsset::query()->max('id')) + 1);
    $asset = MediaAsset::create([
        'storage_key' => 'originals/test-asset-'.$suffix.'.jpg',
        'original_filename' => 'asset.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'asset-fixture-'.$suffix),
        'state' => 'available',
        'alt_text' => 'Asset ALT',
    ]);

    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/test-asset-'.$suffix.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'variant-fixture-'.$suffix),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $asset;
}

function lifecycleJournal(string $template, string $slug): SiteSection
{
    return SiteSection::query()->create([
        'type' => SiteSectionType::Journal->value,
        'template' => $template,
        'title' => ucfirst($slug),
        'navigation_label' => ucfirst($slug),
        'slug' => $slug,
        'state' => 'hidden',
        'position' => ((int) SiteSection::query()->whereNull('parent_id')->max('position')) + 10,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
}

it('ingests canonical media with durable checksums and a rebuildable public thumbnail', function (): void {
    $asset = lifecycleIngestService()->ingest(lifecycleImageUpload('image/jpeg'));
    $variant = $asset->variants()->sole();
    $disk = Storage::disk(config('media.disk'));
    $originalBytes = $disk->get($asset->storage_key);
    $thumbnailBytes = $disk->get($variant->storage_key);

    expect($asset->state)->toBe('available')
        ->and($asset->mime_type)->toBe('image/jpeg')
        ->and($asset->byte_size)->toBe(strlen($originalBytes))
        ->and($asset->sha256)->toBe(hash('sha256', $originalBytes))
        ->and($variant->transform_profile)->toBe(MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE)
        ->and($variant->state)->toBe('available')
        ->and($variant->byte_size)->toBe(strlen($thumbnailBytes))
        ->and($variant->sha256)->toBe(hash('sha256', $thumbnailBytes));
});

it('ingests an image above the legacy GD cap through bounded libvips and regenerates its exact variant', function (): void {
    $ingest = lifecycleIngestService();
    $placeholder = tempnam(sys_get_temp_dir(), 'large-media-test-');
    expect($placeholder)->not->toBeFalse();
    $path = $placeholder.'.jpg';

    try {
        $process = new Process(['vips', 'black', $path, '6000', '4000', '--bands', '3']);
        $process->setTimeout(120);
        expect($process->run())->toBe(0);

        $upload = new UploadedFile($path, 'high-resolution-artwork.jpg', 'image/jpeg', null, true);
        $asset = $ingest->ingest($upload);
        $variant = $asset->variants()->sole();
        $disk = Storage::disk(config('media.disk'));

        expect($asset->width)->toBe(6000)
            ->and($asset->height)->toBe(4000)
            ->and($asset->sha256)->toBe(hash_file('sha256', $path))
            ->and($variant->variant_kind)->toBe('thumbnail')
            ->and($variant->transform_profile)->toBe(MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE)
            ->and(max((int) $variant->width, (int) $variant->height))->toBeLessThanOrEqual(960)
            ->and(app(PublicMedia::class)->thumbnailVariantForAsset($asset)->getKey())->toBe($variant->getKey())
            ->and(app(\App\Domain\Artwork\ArtworkPublicationService::class)->mediaReady($asset, 'Large artwork preview'))->toBeTrue();

        $bytesBefore = $disk->get($variant->storage_key);
        $disk->delete($variant->storage_key);
        expect(app(MediaVariantRegenerationService::class)->ensureFile($variant))->toBeTrue()
            ->and($disk->get($variant->storage_key))->toBe($bytesBefore);
    } finally {
        @unlink($path);
        @unlink($placeholder);
    }
});

it('generates a versioned immutable WebP poster for a real H.264 video and regenerates exact bytes', function (): void {
    $ingest = lifecycleIngestService();
    $placeholder = tempnam(sys_get_temp_dir(), 'video-media-test-');
    expect($placeholder)->not->toBeFalse();
    $path = $placeholder.'.mp4';

    try {
        $process = new Process([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin',
            '-f', 'lavfi', '-i', 'color=c=blue:s=320x240:d=1',
            '-an', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-y', $path,
        ]);
        $process->setTimeout(120);
        expect($process->run())->toBe(0);

        $asset = $ingest->ingest(new UploadedFile($path, 'loop.mp4', 'video/mp4', null, true));
        $variant = $asset->variants()->sole();
        $disk = Storage::disk(config('media.disk'));
        $posterBefore = $disk->get($variant->storage_key);

        expect($asset->mime_type)->toBe('video/mp4')
            ->and($variant->variant_kind)->toBe(MediaIngestService::VIDEO_POSTER_KIND)
            ->and($variant->transform_profile)->toBe(MediaIngestService::VIDEO_POSTER_PROFILE)
            ->and($variant->sha256)->toBe(hash('sha256', $posterBefore))
            ->and(app(PublicMedia::class)->posterUrlForAsset($asset))->not->toBeNull();

        $disk->delete($variant->storage_key);
        expect(app(MediaVariantRegenerationService::class)->ensureFile($variant))->toBeTrue()
            ->and($disk->get($variant->storage_key))->toBe($posterBefore);
    } finally {
        @unlink($path);
        @unlink($placeholder);
    }
});

it('accepts a valid MP4 with the H.264 track metadata outside both byte windows', function (): void {
    $ingest = lifecycleIngestService();
    $temp = tempnam(sys_get_temp_dir(), 'mp4-moov-middle-');
    expect($temp)->not->toBeFalse();
    $path = $temp.'.mp4';

    try {
        $process = new Process([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin',
            '-f', 'lavfi', '-i', 'color=c=blue:s=320x240:d=1',
            '-an', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-y', $path,
        ]);
        $process->setTimeout(60);
        expect($process->run())->toBe(0);

        // Append structurally valid MP4 free atoms on both sides of moov
        // without changing mdat offsets. The old head/tail sampling would
        // miss the H.264 sample description despite valid video content.
        $bytes = file_get_contents($path);
        expect($bytes)->not->toBeFalse();
        $moovTypeOffset = strrpos($bytes, 'moov');
        expect($moovTypeOffset)->not->toBeFalse();
        $moovOffset = $moovTypeOffset - 4;
        // FFmpeg can include 'avc1' as a compatible file brand even before
        // moov. Replacing only that optional brand keeps the real sample
        // description in moov and makes the incomplete byte-scan regression
        // detectable rather than accidentally passing.
        $brandOffset = strpos($bytes, 'avc1');
        if ($brandOffset !== false && $brandOffset < $moovOffset) {
            $bytes = substr_replace($bytes, 'mp42', $brandOffset, 4);
        }
        $paddingBytes = 1_100_000;
        $free = pack('N', $paddingBytes + 8).'free'.str_repeat("\0", $paddingBytes);
        $bytes = substr($bytes, 0, $moovOffset).$free.substr($bytes, $moovOffset).$free;
        file_put_contents($path, $bytes);
        expect(str_contains(substr($bytes, 0, 1_048_576), 'avc1'))->toBeFalse()
            ->and(str_contains(substr($bytes, -1_048_576), 'avc1'))->toBeFalse();

        $asset = $ingest->ingest(new UploadedFile($path, 'valid-moov-middle.mp4', 'video/mp4', null, true));
        expect($asset->mime_type)->toBe('video/mp4')
            ->and($asset->variants()->sole()->variant_kind)->toBe(MediaIngestService::VIDEO_POSTER_KIND);
    } finally {
        @unlink($path);
        @unlink($temp);
    }
});

it('rejects invalid image bytes before database or storage writes', function (): void {
    Storage::fake(config('media.disk'));

    expect(fn () => app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->createWithContent('invalid.jpg', 'not an image')
    ))->toThrow(ValidationException::class);

    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('rejects uploads over the byte limit before writes', function (): void {
    Storage::fake(config('media.disk'));
    // Laravel's fake file factory interprets create() sizes in kilobytes.
    // Extend a real JPEG by one byte beyond the limit, so MIME validation
    // cannot hide a missing byte-ceiling check.
    $upload = lifecycleImageUpload('image/jpeg');
    $path = $upload->getRealPath();
    $handle = fopen($path, 'c+b');
    expect($handle)->not->toBeFalse();
    try {
        expect(ftruncate($handle, MediaTypePolicy::imageMaxBytes() + 1))->toBeTrue();
    } finally {
        fclose($handle);
    }
    clearstatcache(true, $path);

    // The fake retains sizeToReport from creation; use the actual file size.
    $actualUpload = new UploadedFile($path, 'oversized.jpg', null, null, true);
    expect($actualUpload->getSize())->toBe(MediaTypePolicy::imageMaxBytes() + 1);
    expect(fn () => app(MediaIngestService::class)->ingest($actualUpload))
        ->toThrow(ValidationException::class, 'The uploaded media has an invalid size for this media type.');
    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('rejects images over the pixel limit before writes', function (): void {
    Storage::fake(config('media.disk'));
    $small = lifecycleImageUpload('image/png');
    $bytes = file_get_contents($small->getRealPath());
    $bytes = substr_replace($bytes, pack('N', 15000), 16, 4);
    $bytes = substr_replace($bytes, pack('N', 10000), 20, 4);
    // Update the IHDR CRC so a malformed PNG does not accidentally count
    // as evidence for the independent image-pixel limit.
    $bytes = substr_replace($bytes, pack('N', crc32(substr($bytes, 12, 17))), 29, 4);

    expect(fn () => app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->createWithContent('large.png', $bytes)
    ))->toThrow(ValidationException::class, 'The uploaded image dimensions are not allowed.');

    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('never trusts the client basename for storage keys', function (): void {
    $asset = lifecycleIngestService()->ingest(
        lifecycleImageUpload('image/jpeg', name: '..\\private/secret.jpg')
    );

    expect($asset->original_filename)->toBe('secret.jpg')
        ->and($asset->storage_key)->not->toContain('secret.jpg')
        ->and($asset->storage_key)->toStartWith('originals/');
});

it('fails closed when the operator quota is invalid', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => '5GB']);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot['configuration_valid'])->toBeFalse();
    expect(fn () => app(MediaCapacityService::class)->assertCanStoreOriginal(1))
        ->toThrow(ValidationException::class);
});

it('allows an exact-fit site storage write and blocks the first byte beyond quota', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity']);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 40));

    $database = app(SiteStorageDatabaseUsageService::class)->snapshot();

    expect($database['measurement_available'])->toBeTrue();

    $databaseBytes = (int) $database['logical_bytes'];
    config()->set('media.quota_bytes', $databaseBytes + 100);

    $capacity = app(MediaCapacityService::class);
    $capacity->assertCanStoreOriginal(60);

    expect(fn () => $capacity->assertCanStoreOriginal(61))
        ->toThrow(ValidationException::class, 'site storage allowance is full');
});

it('blocks exhausted-quota ingest before the first write', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 1]);
    $upload = UploadedFile::fake()->image('blocked.jpg', 32, 32);

    expect(fn () => app(MediaIngestService::class)->ingest($upload))
        ->toThrow(ValidationException::class);

    expect(Storage::disk('media-capacity')->allFiles())->toBe([])
        ->and(MediaAsset::query()->count())->toBe(0);
});

it('logically deletes unreferenced media and removes its files', function (): void {
    Storage::fake(config('media.disk'));
    $asset = lifecycleAsset();
    $variant = $asset->variants()->sole();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'variant');

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();
    expect($asset->fresh()->state)->toBe('deleted')
        ->and($variant->fresh()->state)->toBe('deleted');
    Storage::disk(config('media.disk'))->assertMissing($asset->storage_key);
    Storage::disk(config('media.disk'))->assertMissing($variant->storage_key);
});

it('removes current content references before logically deleting media', function (string $type): void {
    $asset = lifecycleAsset();
    $reference = null;

    if ($type === 'artwork') {
        $category = ArtworkCategory::create([
            'slug' => 'works',
            'name' => 'Works',
        ]);
        $artwork = Artwork::create([
            'artwork_category_id' => $category->id,
            'slug' => 'work',
            'title' => 'Work',
            'state' => 'draft',
            'position' => 0,
        ]);
        $reference = ArtworkMedia::create([
            'artwork_id' => $artwork->id,
            'media_asset_id' => $asset->id,
            'role' => 'primary',
            'position' => 0,
        ]);
    } elseif ($type === 'exhibition') {
        $journal = lifecycleJournal(JournalTemplate::Exhibitions->value, 'media-exhibitions');
        $exhibition = Exhibition::create([
            'site_section_id' => $journal->id,
            'slug' => 'show',
            'title' => 'Show',
            'state' => 'draft',
            'position' => 0,
        ]);
        $reference = JournalEntryMedia::create([
            'exhibition_id' => $exhibition->id,
            'media_asset_id' => $asset->id,
            'role' => JournalEntryMedia::ROLE_GALLERY,
            'position' => 1,
        ]);
    } else {
        $journal = lifecycleJournal(JournalTemplate::Blog->value, 'media-blog');
        $reference = BlogPost::create([
            'site_section_id' => $journal->id,
            'slug' => 'post',
            'title' => 'Post',
            'state' => 'draft',
            'position' => 0,
            'cover_media_asset_id' => $asset->id,
        ]);
    }

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue()
        ->and($asset->fresh()->state)->toBe('deleted');

    if ($type === 'blog') {
        expect($reference->fresh()->cover_media_asset_id)->toBeNull();
    } else {
        expect($reference->fresh())->toBeNull();
    }
})->with(['artwork', 'exhibition', 'blog']);

it('detects checksum corruption in authoritative and generated media', function (): void {
    Storage::fake(config('media.disk'));
    $asset = lifecycleAsset();
    $variant = $asset->variants()->sole();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'bad');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'bad');

    expect(app(MediaIntegrityService::class)->issues($asset))->not->toBeEmpty();
});
