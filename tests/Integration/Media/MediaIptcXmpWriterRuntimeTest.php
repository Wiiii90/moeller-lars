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
            imagesetpixel($image, $x, $y, imagecolorallocate($image, $x * 9, $y * 13, ($x + $y) * 7));
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
        $options = ['-overwrite_original', '-ICC_Profile<='.$iccFile, '-XMP-dc:Creator=Old creator'];
        if ($mime === 'image/jpeg') {
            array_push($options, '-GPSLatitude=51.5', '-GPSLatitudeRef=N', '-GPSLongitude=9.5', '-GPSLongitudeRef=E');
        }
        b2Exif([...$options, $source]);
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
        expect(array_filter(array_keys($tags), static fn (string $key): bool => str_contains($key, 'GPSLatitude') || str_contains($key, 'GPSLongitude')))->toBeEmpty();
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
