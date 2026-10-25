<?php

use App\Domain\Media\MediaIptcXmpWriter;
use App\Domain\Media\MediaRightsProjection;
use App\Models\MediaAsset;
use Symfony\Component\Process\Process;

// Real-file tests: no mocked ExifTool, no database or server mutations.
function b2Exif(array $args): string
{
    $process = new Process([(string) config('media.exiftool_binary', 'exiftool'), ...$args]);
    $process->setTimeout(60);
    $process->mustRun();

    return $process->getOutput();
}

function b2Raster(string $path, string $mime): void
{
    $image = imagecreatetruecolor(23, 17);
    for ($y = 0; $y < 17; $y++) {
        for ($x = 0; $x < 23; $x++) {
            imagesetpixel($image, $x, $y, imagecolorallocate($image, $x * 9, $y * 13, ($x + $y) * 6));
        }
    }
    match ($mime) {
        'image/jpeg' => imagejpeg($image, $path, 97),
        'image/png' => imagepng($image, $path, 0),
        'image/webp' => imagewebp($image, $path, 100),
    };
    imagedestroy($image);
}

function b2DecodedPixels(string $path, string $mime): array
{
    $image = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png' => imagecreatefrompng($path),
        'image/webp' => imagecreatefromwebp($path),
    };
    $pixels = [];
    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $pixels[] = imagecolorat($image, $x, $y);
        }
    }
    imagedestroy($image);

    return $pixels;
}

it('writes real JPEG, PNG and WebP rights metadata without changing original bytes, decoded pixels or ICC', function (string $mime, string $ext): void {
    MediaIptcXmpWriter::assertExifToolVersion(b2Exif(['-ver']));
    $dir = sys_get_temp_dir().'/b2-photo-'.bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $source = "$dir/source.$ext";
    $staged = "$dir/staged.$ext";
    $iccFile = "$dir/srgb.icc";

    try {
        b2Raster($source, $mime);
        // Valid sRGB ICC profile bytes generated from LittleCMS, not a mock tag.
        $icc = base64_decode(
            'AAACTGxjbXMEQAAAbW50clJHQiBYWVogB+oACgAPABEAIwAbYWNzcEFQUEwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAPbWAAEAAAAA0y1sY21zAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALZGVzYwAAAQgAAAA2Y3BydAAAAUAAAABMd3RwdAAAAYwAAAAUY2hhZAAAAaAAAAAsclhZWgAAAcwAAAAUYlhZWgAAAeAAAAAUZ1hZWgAAAfQAAAAUclRSQwAAAggAAAAgZ1RSQwAAAggAAAAgYlRSQwAAAggAAAAgY2hybQAAAigAAAAkbWx1YwAAAAAAAAABAAAADGVuVVMAAAAaAAAAHABzAFIARwBCACAAYgB1AGkAbAB0AC0AaQBuAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAADAAAAAcAE4AbwAgAGMAbwBwAHkAcgBpAGcAaAB0ACwAIAB1AHMAZQAgAGYAcgBlAGUAbAB5WFlaIAAAAAAAAPbWAAEAAAAA0y1zZjMyAAAAAAABDEIAAAXe///zJQAAB5MAAP2Q///7of///aIAAAPcAADAblhZWiAAAAAAAABvoAAAOPUAAAOQWFlaIAAAAAAAACSfAAAPhAAAtsNYWVogAAAAAAAAYpcAALeHAAAY2XBhcmEAAAAAAAMAAAACZmYAAPKnAAANWQAAE9AAAApbY2hybQAAAAAAAwAAAACj1wAAVHsAAEzNAACZmgAAJmYAAA9c',
            true,
        );
        expect($icc)->toBeString();
        file_put_contents($iccFile, $icc);
        $options = ['-overwrite_original', '-ICC_Profile<='.$iccFile, '-XMP-dc:Creator=Old creator', '-XMP-aux:SerialNumber=B2-TEST-CAMERA-SERIAL'];
        if ($mime === 'image/jpeg') {
            array_push($options, '-GPSLatitude=51.5', '-GPSLatitudeRef=N', '-GPSLongitude=9.5', '-GPSLongitudeRef=E');
        }
        b2Exif([...$options, $source]);
        // Confirm the fixture actually contains sensitive source metadata,
        // otherwise a passing scrub assertion would be a false positive.
        $sourceTags = json_decode(b2Exif([
            '-j', '-s', '-G1', '-XMP:all', '-GPS:all', $source,
        ]), true, 512, JSON_THROW_ON_ERROR)[0];
        expect($sourceTags['XMP-dc:Creator'] ?? null)->toBe('Old creator')
            ->and($sourceTags['XMP-aux:SerialNumber'] ?? null)->toBe('B2-TEST-CAMERA-SERIAL');
        if ($mime === 'image/jpeg') {
            expect(array_filter(
                array_keys($sourceTags),
                static fn (string $key): bool => str_ends_with($key, ':GPSLatitude'),
            ))->not->toBeEmpty();
        }

        $originalHash = hash_file('sha256', $source);
        $originalSize = filesize($source);
        $pixels = b2DecodedPixels($source, $mime);
        $originalIcc = b2Exif(['-b', '-ICC_Profile', $source]);
        expect($originalIcc)->toBe($icc);

        $asset = new MediaAsset;
        $asset->setRawAttributes([
            'mime_type' => $mime,
            'byte_size' => $originalSize,
            'sha256' => $originalHash,
            'file_creator' => 'Photographer',
            'file_rights_holder' => 'Photo Rights Holder',
            'credit' => 'Photo agency',
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
            'copyright_notice' => 'Copyright Photo Rights Holder',
            'rights_usage_terms' => 'Permission required',
            'license_inquiry_url' => 'https://example.org/licensing',
        ], true);
        $result = (new MediaIptcXmpWriter(new MediaRightsProjection))->stageCopy($asset, $source, $staged, true);
        expect(hash_file('sha256', $source))->toBe($originalHash)
            ->and(filesize($source))->toBe($originalSize)
            ->and($result['sha256'])->toBe(hash_file('sha256', $staged))
            ->and($result['byte_size'])->toBe(filesize($staged))
            ->and(getimagesize($source)[0])->toBe(getimagesize($staged)[0])
            ->and(getimagesize($source)[1])->toBe(getimagesize($staged)[1])
            ->and(b2DecodedPixels($staged, $mime))->toBe($pixels)
            ->and(b2Exif(['-b', '-ICC_Profile', $staged]))->toBe($originalIcc);

        $tags = json_decode(b2Exif(['-j', '-s', '-G1', '-n', '-XMP:all', '-GPS:all', $staged]), true, 512, JSON_THROW_ON_ERROR)[0];
        expect($tags['XMP-dc:Creator'] ?? null)->toBe('Photographer')
            ->and($tags['XMP-plus:CopyrightOwnerName'] ?? null)->toBe('Photo Rights Holder')
            ->and($tags['XMP-photoshop:Credit'] ?? null)->toBe('Photo agency')
            ->and($tags['XMP-dc:Rights'] ?? null)->toBe('Copyright Photo Rights Holder')
            ->and($tags['XMP-xmpRights:UsageTerms'] ?? null)->toBe('Permission required')
            ->and($tags['XMP-plus:LicensorURL'] ?? null)->toBe('https://example.org/licensing')
            ->and($tags['XMP-plus:DataMining'] ?? null)->toBe('DMI-PROHIBITED-EXCEPTSEARCHENGINEINDEXING');
        expect(array_filter(array_keys($tags), static fn (string $key): bool => str_contains($key, 'GPSLatitude') || str_contains($key, 'GPSLongitude') || str_contains($key, 'SerialNumber')))->toBeEmpty();
    } finally {
        foreach (glob("$dir/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
})->with([
    'JPEG' => ['image/jpeg', 'jpg'],
    'PNG' => ['image/png', 'png'],
    'WebP' => ['image/webp', 'webp'],
]);

it('stages actual WebP thumbnails with their own hashes and the original asset rights', function (): void {
    $dir = sys_get_temp_dir().'/b2-thumb-'.bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $source = "$dir/thumbnail.webp";
    $staged = "$dir/staged.webp";

    try {
        b2Raster($source, 'image/webp');
        $sourceHash = hash_file('sha256', $source);
        $asset = new MediaAsset;
        $asset->setRawAttributes([
            'id' => 142,
            'mime_type' => 'image/jpeg',
            'file_creator' => 'Authorized Photographer',
            'file_rights_holder' => 'Photo Studio',
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
            'copyright_notice' => 'Copyright Photo Studio',
        ], true);
        $variant = new \App\Models\MediaVariant;
        $variant->setRawAttributes([
            'id' => 247,
            'media_asset_id' => 142,
            'variant_kind' => \App\Domain\Media\PublicMedia::THUMBNAIL_KIND,
            'transform_profile' => \App\Domain\Media\PublicMedia::PUBLIC_TRANSFORM_PROFILE,
            'state' => 'available',
            'mime_type' => 'image/webp',
            'byte_size' => filesize($source),
            'sha256' => $sourceHash,
        ], true);

        $writer = new MediaIptcXmpWriter(new MediaRightsProjection);
        $metadata = $writer->stageVariantCopy($asset, $variant, $source, $staged);
        $tags = json_decode(b2Exif(['-j', '-s', '-G1', '-XMP:all', $staged]), true, 512, JSON_THROW_ON_ERROR)[0];
        expect($metadata['sha256'])->toBe(hash_file('sha256', $staged))
            ->and($metadata['mime_type'])->toBe('image/webp')
            ->and(hash_file('sha256', $source))->toBe($sourceHash)
            ->and(b2DecodedPixels($staged, 'image/webp'))->toBe(b2DecodedPixels($source, 'image/webp'))
            ->and($tags['XMP-dc:Creator'] ?? null)->toBe('Authorized Photographer')
            ->and($tags['XMP-plus:CopyrightOwnerName'] ?? null)->toBe('Photo Studio');

        $variant->setAttribute('media_asset_id', 143);
        expect(fn () => $writer->stageVariantCopy($asset, $variant, $source, "$dir/rejected.webp"))
            ->toThrow(RuntimeException::class)
            ->and(file_exists("$dir/rejected.webp"))->toBeFalse();
    } finally {
        foreach (glob("$dir/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
});

it('rejects inherited contradictory rights without rewriting archive claims', function (): void {
    $dir = sys_get_temp_dir().'/b2-rights-conflict-'.bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $source = "$dir/source.jpg";
    $staged = "$dir/staged.jpg";

    try {
        b2Raster($source, 'image/jpeg');
        b2Exif(['-overwrite_original', '-XMP-dc:Rights=Existing photographer claim', $source]);
        $sourceHash = hash_file('sha256', $source);

        $asset = new MediaAsset;
        $asset->setRawAttributes([
            'mime_type' => 'image/jpeg',
            'byte_size' => filesize($source),
            'sha256' => $sourceHash,
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE,
        ], true);
        expect(fn () => (new MediaIptcXmpWriter(new MediaRightsProjection))
            ->stageCopy($asset, $source, $staged))->toThrow(
                RuntimeException::class,
                'Original XMP rights conflict',
            );
        expect(hash_file('sha256', $source))->toBe($sourceHash)
            ->and(file_exists($staged))->toBeFalse();
    } finally {
        foreach (glob("$dir/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
});

it('refuses corrupt source checksums without creating a staged copy', function (): void {
    $dir = sys_get_temp_dir().'/b2-integrity-'.bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    try {
        $source = "$dir/source.png";
        $staged = "$dir/staged.png";
        b2Raster($source, 'image/png');
        $asset = new MediaAsset;
        $asset->setRawAttributes([
            'mime_type' => 'image/png', 'byte_size' => filesize($source),
            'sha256' => str_repeat('0', 64), 'copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE,
        ], true);
        expect(fn () => (new MediaIptcXmpWriter(new MediaRightsProjection))
            ->stageCopy($asset, $source, $staged))->toThrow(RuntimeException::class);
        expect(file_exists($staged))->toBeFalse();
    } finally {
        foreach (glob("$dir/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
});
