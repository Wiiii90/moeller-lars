<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Creates an unpublished rights-bearing image copy from an immutable original.
 *
 * The publication owner must supply a new, private staging path and later
 * promote/version it atomically. This service never rewrites an archive, an
 * existing media variant, or a public URL.
 */
final class MediaIptcXmpWriter
{
    public const MIN_EXIFTOOL_VERSION = '12.67';

    public const DATA_MINING_RESTRICTION = 'Prohibited except for search engine indexing';

    public function __construct(private readonly MediaRightsProjection $rights) {}

    /**
     * Build standard ExifTool XMP tags without inventing photographer/owner data.
     * The caller must independently establish authority to reserve data mining.
     *
     * @return array<string, string>
     */
    public function tagsForAsset(MediaAsset $asset, bool $mayReserveDataMining = false): array
    {
        $facts = $this->rights->forAsset($asset);
        $tags = [];
        $mapping = [
            'creator' => 'XMP-dc:Creator',
            'rights_holder' => 'XMP-plus:CopyrightOwnerName',
            'credit' => 'XMP-photoshop:Credit',
            'copyright_notice' => 'XMP-dc:Rights',
            'usage_terms' => 'XMP-xmpRights:UsageTerms',
            'license_inquiry_url' => 'XMP-plus:LicensorURL',
        ];

        foreach ($mapping as $field => $tag) {
            $value = $facts[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            // A default notice for the *artwork* must not overwrite the
            // copyright identity of a separately attributed photograph.
            if ($field === 'copyright_notice'
                && $facts['rights_holder'] !== null
                && $asset->getAttribute('copyright_notice_mode') !== MediaAsset::COPYRIGHT_OVERRIDE) {
                continue;
            }

            $tags[$tag] = trim($value);
        }

        // A license inquiry URL is not necessarily a legal Web Statement.
        // Only the public-page owner can provide a verified WebStatement URL.
        if ($mayReserveDataMining) {
            $tags['XMP-plus:DataMining'] = self::DATA_MINING_RESTRICTION;
        }

        return $tags;
    }

    public static function assertExifToolVersion(string $version): void
    {
        $version = trim($version);
        if (! preg_match('/^\d+(?:\.\d+)+$/D', $version)
            || version_compare($version, self::MIN_EXIFTOOL_VERSION, '<')) {
            throw new RuntimeException(
                'IPTC Data Mining requires ExifTool '.self::MIN_EXIFTOOL_VERSION.' or newer.',
            );
        }
    }

    /**
     * Write to an exclusive new staging file, never the original.
     *
     * @return array{sha256:string,byte_size:int,mime_type:string}
     */
    public function stageCopy(
        MediaAsset $asset,
        string $originalPath,
        string $stagedPath,
        bool $mayReserveDataMining = false,
    ): array {
        if (! is_file($originalPath) || ! is_readable($originalPath)
            || ! is_dir(dirname($stagedPath)) || ! is_writable(dirname($stagedPath))) {
            throw new RuntimeException('Media staging paths must be readable and writable.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($originalPath);
        if (! is_string($mime) || ! MediaTypePolicy::isImage($mime)) {
            throw new RuntimeException('IPTC/XMP image staging supports JPEG, PNG and WebP only.');
        }

        // The released Debian Bookworm package (12.57) is too old for this
        // property's controlled vocabulary. Never silently omit the opt-out.
        $binary = (string) config('media.exiftool_binary', 'exiftool');
        $version = new Process([$binary, '-ver']);
        $version->setTimeout(10);
        $version->mustRun();
        self::assertExifToolVersion($version->getOutput());

        $output = @fopen($stagedPath, 'xb');
        if (! is_resource($output)) {
            throw new RuntimeException('Refusing to overwrite an existing staged media file.');
        }

        try {
            $input = @fopen($originalPath, 'rb');
            if (! is_resource($input)) {
                throw new RuntimeException('Unable to read immutable media original.');
            }
            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Unable to copy media into the staging file.');
                }
            } finally {
                fclose($input);
            }
            fclose($output);
            $output = null;

            $args = [$binary, '-overwrite_original', '-P'];
            // Strip common geolocation and equipment identifiers on the
            // *public copy* while retaining unrelated embedded license data.
            foreach ([
                'GPS:all', 'XMP-exif:GPSLatitude', 'XMP-exif:GPSLongitude',
                'XMP-exif:GPSAltitude', 'EXIF:SerialNumber',
                'EXIF:BodySerialNumber', 'EXIF:LensSerialNumber',
                'XMP-aux:SerialNumber',
            ] as $privateTag) {
                $args[] = '-'.$privateTag.'=';
            }
            foreach ($this->tagsForAsset($asset, $mayReserveDataMining) as $tag => $value) {
                $args[] = '-'.$tag.'='.$value;
            }
            $args[] = $stagedPath;

            $process = new Process($args);
            $process->setTimeout(60);
            $process->mustRun();

            $originalSize = @getimagesize($originalPath);
            $stagedSize = @getimagesize($stagedPath);
            if (! is_array($originalSize) || ! is_array($stagedSize)
                || $originalSize[0] !== $stagedSize[0]
                || $originalSize[1] !== $stagedSize[1]) {
                throw new RuntimeException('The staged image dimensions do not match the immutable original.');
            }

            $hash = @hash_file('sha256', $stagedPath);
            $size = @filesize($stagedPath);
            if (! is_string($hash) || ! is_int($size) || $size <= 0) {
                throw new RuntimeException('Unable to verify the staged image.');
            }

            return ['sha256' => $hash, 'byte_size' => $size, 'mime_type' => $mime];
        } catch (Throwable $exception) {
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($stagedPath);
            throw $exception;
        }
    }
}
