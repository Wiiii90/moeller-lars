<?php

use App\Domain\Media\MediaIngestService;
use App\Models\MediaAsset;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

function ingestImageUpload(string $mime, int $width = 120, int $height = 80, string $name = 'upload.jpg', bool $transparent = false): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $background = imagecolorallocatealpha($image, 20, 80, 160, $transparent ? 70 : 0);
    imagefill($image, 0, 0, $background);
    $foreground = imagecolorallocatealpha($image, 220, 40, 80, $transparent ? 20 : 0);
    imagefilledrectangle($image, 0, 0, (int) ($width / 2), (int) ($height / 2), $foreground);

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

function ingestService(): MediaIngestService
{
    Storage::fake(config('media.disk'));

    return app(MediaIngestService::class);
}

it('ingests a JPEG and creates canonical and thumbnail media', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/jpeg'));
    $variant = $asset->variants()->sole();
    $disk = Storage::disk(config('media.disk'));

    expect($asset->state)->toBe('available')
        ->and($asset->mime_type)->toBe('image/jpeg')
        ->and($asset->storage_key)->toMatch('/^originals\/[0-9a-f-]+\.jpg$/')
        ->and($asset->original_filename)->toBe('upload.jpg')
        ->and($asset->width)->toBe(120)
        ->and($asset->height)->toBe(80)
        ->and($variant->variant_kind)->toBe('thumbnail')
        ->and($variant->transform_profile)->toBe('public-v1')
        ->and($variant->mime_type)->toBe('image/webp')
        ->and($variant->state)->toBe('available')
        ->and($disk->exists($asset->storage_key))->toBeTrue()
        ->and($disk->exists($variant->storage_key))->toBeTrue();

    $originalBytes = $disk->get($asset->storage_key);
    $thumbnailBytes = $disk->get($variant->storage_key);

    expect($asset->byte_size)->toBe(strlen($originalBytes))
        ->and($asset->sha256)->toBe(hash('sha256', $originalBytes))
        ->and($variant->byte_size)->toBe(strlen($thumbnailBytes))
        ->and($variant->sha256)->toBe(hash('sha256', $thumbnailBytes));
});

it('ingests PNG and preserves transparency in the canonical original', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/png', name: 'transparent.png', transparent: true));
    $variant = $asset->variants()->sole();
    $image = imagecreatefromstring(Storage::disk(config('media.disk'))->get($asset->storage_key));
    $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
    imagedestroy($image);

    expect($asset->mime_type)->toBe('image/png')
        ->and($alpha)->toBeGreaterThan(0)
        ->and($variant->mime_type)->toBe('image/webp');
});

it('ingests WebP media', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/webp', name: 'upload.webp'));

    expect($asset->mime_type)->toBe('image/webp')
        ->and($asset->storage_key)->toEndWith('.webp')
        ->and($asset->variants()->sole()->mime_type)->toBe('image/webp');
});

it('creates a correctly bounded landscape thumbnail', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/jpeg', 2400, 1200));
    $variant = $asset->variants()->sole();

    expect($variant->width)->toBe(960)
        ->and($variant->height)->toBe(480);
});

it('does not upscale a small image thumbnail', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/jpeg', 320, 200));
    $variant = $asset->variants()->sole();

    expect($variant->width)->toBe(320)
        ->and($variant->height)->toBe(200);
});

it('rejects invalid bytes regardless of the client filename', function () {
    Storage::fake(config('media.disk'));

    expect(fn () => app(MediaIngestService::class)->ingest(UploadedFile::fake()->createWithContent('invalid.jpg', 'not an image')))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('rejects a disallowed detected MIME type', function () {
    Storage::fake(config('media.disk'));

    expect(fn () => app(MediaIngestService::class)->ingest(UploadedFile::fake()->createWithContent('payload.jpg', '<html>not an image</html>')))
        ->toThrow(ValidationException::class);
});

it('rejects an upload over the byte limit before storage or database writes', function () {
    Storage::fake(config('media.disk'));
    $upload = UploadedFile::fake()->create('large.jpg', MediaIngestService::MAX_BYTES + 1, 'image/jpeg');

    expect(fn () => app(MediaIngestService::class)->ingest($upload))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('rejects an image over the pixel limit before decoding or writes', function () {
    Storage::fake(config('media.disk'));
    $small = ingestImageUpload('image/png');
    $bytes = file_get_contents($small->getRealPath());
    $bytes = substr_replace($bytes, pack('N', 10000), 16, 4);
    $bytes = substr_replace($bytes, pack('N', 5000), 20, 4);

    expect(fn () => app(MediaIngestService::class)->ingest(UploadedFile::fake()->createWithContent('large.png', $bytes)))
        ->toThrow(ValidationException::class);
    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('sanitizes the client basename and never uses it in storage keys', function () {
    $asset = ingestService()->ingest(ingestImageUpload('image/jpeg', name: '..\\private/secret.jpg'));

    expect($asset->original_filename)->toBe('secret.jpg')
        ->and($asset->storage_key)->not->toContain('secret.jpg')
        ->and($asset->storage_key)->toMatch('/^originals\/[0-9a-f-]+\.jpg$/');
});

it('stores a newly encoded canonical original', function () {
    $upload = ingestImageUpload('image/jpeg');
    $uploadBytes = file_get_contents($upload->getRealPath());
    $asset = ingestService()->ingest($upload);

    expect(Storage::disk(config('media.disk'))->get($asset->storage_key))->not->toBe($uploadBytes);
});

it('cleans both files when the database transaction fails', function () {
    $service = ingestService();
    MediaAsset::creating(fn () => throw new RuntimeException('database failure'));

    try {
        expect(fn () => $service->ingest(ingestImageUpload('image/jpeg')))
            ->toThrow(RuntimeException::class, 'database failure');
    } finally {
        MediaAsset::flushEventListeners();
    }

    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk(config('media.disk'))->allFiles())->toBeEmpty();
});

it('cleans the first file when the thumbnail write fails', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->times(6)->andReturn(false, false, true, false, true, false);
    $disk->shouldReceive('put')->twice()->andReturn(true, false);
    $disk->shouldReceive('delete')->twice()->andReturn(true);
    Storage::shouldReceive('disk')->once()->andReturn($disk);

    expect(fn () => app(MediaIngestService::class)->ingest(ingestImageUpload('image/jpeg')))
        ->toThrow(RuntimeException::class, 'thumbnail');
    expect(MediaAsset::count())->toBe(0);
});

it('does not overwrite a pre-existing generated key', function () {
    $uuid = '11111111-1111-4111-8111-111111111111';
    Str::createUuidsUsing(fn () => Uuid::fromString($uuid));

    try {
        $disk = Storage::fake(config('media.disk'));
        $disk->put('originals/'.$uuid.'.jpg', 'existing');

        expect(fn () => app(MediaIngestService::class)->ingest(ingestImageUpload('image/jpeg')))
            ->toThrow(RuntimeException::class, 'already exists');
        expect($disk->get('originals/'.$uuid.'.jpg'))->toBe('existing')
            ->and(MediaAsset::count())->toBe(0);
    } finally {
        Str::createUuidsNormally();
    }
});
