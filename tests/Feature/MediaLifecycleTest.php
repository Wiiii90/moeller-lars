<?php

use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaIntegrityService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'asset.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'state' => 'available',
        'alt_text' => 'Asset ALT',
    ]);

    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.uniqid().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $asset;
}

function lifecycleJournal(string $template, string $slug): SiteSection
{
    return SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => $template,
        'title' => ucfirst($slug),
        'navigation_label' => ucfirst($slug),
        'slug' => $slug,
        'state' => 'hidden',
        'position' => random_int(600, 800),
        'show_in_navigation' => false,
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
        ->and($variant->transform_profile)->toBe('public-v1')
        ->and($variant->state)->toBe('available')
        ->and($variant->byte_size)->toBe(strlen($thumbnailBytes))
        ->and($variant->sha256)->toBe(hash('sha256', $thumbnailBytes));
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
    $upload = UploadedFile::fake()->create('large.jpg', MediaIngestService::MAX_BYTES + 1, 'image/jpeg');

    expect(fn () => app(MediaIngestService::class)->ingest($upload))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('rejects images over the pixel limit before writes', function (): void {
    Storage::fake(config('media.disk'));
    $small = lifecycleImageUpload('image/png');
    $bytes = file_get_contents($small->getRealPath());
    $bytes = substr_replace($bytes, pack('N', 10000), 16, 4);
    $bytes = substr_replace($bytes, pack('N', 5000), 20, 4);

    expect(fn () => app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->createWithContent('large.png', $bytes)
    ))->toThrow(ValidationException::class);

    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('never trusts the client basename for storage keys', function (): void {
    $asset = lifecycleIngestService()->ingest(
        lifecycleImageUpload('image/jpeg', name: '..\\private/secret.jpg')
    );

    expect($asset->original_filename)->toBe('secret.jpg')
        ->and($asset->storage_key)->not->toContain('secret.jpg')
        ->and($asset->storage_key)->toMatch('/^originals\/[0-9a-f-]+\.jpg$/');
});

it('fails closed when the operator quota is invalid', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => '5GB']);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot['configuration_valid'])->toBeFalse();
    expect(fn () => app(MediaCapacityService::class)->assertCanStoreOriginal(1))
        ->toThrow(ValidationException::class);
});

it('allows an exact-fit original and blocks the first byte beyond quota', function (): void {
    Storage::fake('media-capacity');
    config(['media.disk' => 'media-capacity', 'media.quota_bytes' => 100]);
    Storage::disk('media-capacity')->put('originals/one.jpg', str_repeat('a', 40));

    app(MediaCapacityService::class)->assertCanStoreOriginal(60);

    expect(fn () => app(MediaCapacityService::class)->assertCanStoreOriginal(61))
        ->toThrow(ValidationException::class);
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

it('refuses deletion while media is referenced by current content', function (string $type): void {
    $asset = lifecycleAsset();

    if ($type === 'artwork') {
        $category = ArtworkCategory::create([
            'slug' => 'works',
            'name' => 'Works',
            'state' => 'published',
            'position' => 0,
        ]);
        $artwork = Artwork::create([
            'artwork_category_id' => $category->id,
            'slug' => 'work',
            'title' => 'Work',
            'state' => 'draft',
            'position' => 0,
        ]);
        ArtworkMedia::create([
            'artwork_id' => $artwork->id,
            'media_asset_id' => $asset->id,
            'role' => 'primary',
            'position' => 0,
        ]);
    } elseif ($type === 'exhibition') {
        $journal = lifecycleJournal(SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS, 'media-exhibitions');
        $exhibition = Exhibition::create([
            'site_section_id' => $journal->id,
            'slug' => 'show',
            'title' => 'Show',
            'state' => 'draft',
            'position' => 0,
        ]);
        ExhibitionMedia::create([
            'exhibition_id' => $exhibition->id,
            'media_asset_id' => $asset->id,
            'role' => 'additional',
            'position' => 0,
        ]);
    } else {
        $journal = lifecycleJournal(SiteSection::JOURNAL_TEMPLATE_BLOG, 'media-blog');
        BlogPost::create([
            'site_section_id' => $journal->id,
            'slug' => 'post',
            'title' => 'Post',
            'state' => 'draft',
            'position' => 0,
            'cover_media_asset_id' => $asset->id,
        ]);
    }

    expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))
        ->toThrow(ValidationException::class);
    expect($asset->fresh()->state)->toBe('available');
})->with(['artwork', 'exhibition', 'blog']);

it('detects checksum corruption in authoritative and generated media', function (): void {
    Storage::fake(config('media.disk'));
    $asset = lifecycleAsset();
    $variant = $asset->variants()->sole();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'bad');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'bad');

    expect(app(MediaIntegrityService::class)->issues($asset))->not->toBeEmpty();
});
