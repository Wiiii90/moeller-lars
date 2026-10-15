<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
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

    /**
     * Fail closed on missing XMP rights, residual private identifiers or
     * unintended ICC changes. Any failure removes the unpublished copy.
     * Deep decoded-pixel equivalence remains part of format-specific tests.
     */
    private function assertStagedMetadata(
        MediaAsset $asset,
        string $binary,
        string $originalPath,
        string $stagedPath,
        bool $mayReserveDataMining,
    ): void {
        $read = new Process([
            $binary, '-j', '-a', '-G1', '-s', '-n',
            '-XMP:all', '-GPS:all',
            '-SerialNumber', '-BodySerialNumber', '-LensSerialNumber',
            $stagedPath,
        ]);
        $read->setTimeout(60);
        $read->mustRun();
        if (trim($read->getErrorOutput()) !== '') {
            throw new RuntimeException('ExifTool reported a staged metadata read warning.');
        }
        $rows = json_decode($read->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $actual = is_array($rows) ? ($rows[0] ?? null) : null;
        if (! is_array($actual)) {
            throw new RuntimeException('ExifTool did not return valid staged metadata.');
        }

        foreach ($this->tagsForAsset($asset, $mayReserveDataMining) as $tag => $value) {
            $expected = $tag === 'XMP-plus:DataMining'
                ? 'DMI-PROHIBITED-EXCEPTSEARCHENGINEINDEXING'
                : $value;
            if (($actual[$tag] ?? null) !== $expected) {
                throw new RuntimeException('Staged IPTC/XMP readback mismatch for '.$tag.'.');
            }
        }

        foreach (array_keys($actual) as $tag) {
            if (is_string($tag) && preg_match('/GPS|SerialNumber/i', $tag)) {
                throw new RuntimeException('Staged image still contains sensitive location or serial metadata.');
            }
        }

        // Do not accidentally republish an old rights claim when the editor
        // explicitly suppresses notices; neither delete unknown grants nor
        // assert ownership on behalf of the original photographer.
        if ($asset->getAttribute('copyright_notice_mode') === MediaAsset::COPYRIGHT_NONE
            && array_key_exists('XMP-dc:Rights', $actual)) {
            throw new RuntimeException('Original XMP rights conflict with explicit no-notice policy.');
        }
        if (! $mayReserveDataMining && array_key_exists('XMP-plus:DataMining', $actual)) {
            throw new RuntimeException('Unapproved embedded data-mining reservation requires rights review.');
        }

        // ExifTool metadata updates must not remove or mutate color profiles.
        $originalICC = $this->readIccProfile($binary, $originalPath);
        $stagedICC = $this->readIccProfile($binary, $stagedPath);
        if (! hash_equals($originalICC, $stagedICC)) {
            throw new RuntimeException('Staged image ICC profile differs from the archived original.');
        }
    }

    private function readIccProfile(string $binary, string $path): string
    {
        $read = new Process([$binary, '-b', '-ICC_Profile', $path]);
        $read->setTimeout(60);
        $read->mustRun();
        if (trim($read->getErrorOutput()) !== '') {
            throw new RuntimeException('ExifTool reported an ICC profile read warning.');
        }

        return $read->getOutput();
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
        return $this->stageSource(
            $asset, $asset, $originalPath, $stagedPath, $mayReserveDataMining,
        );
    }

    /**
     * A single IPTC/XMP writer also stages the existing WebP thumbnail.
     * Its source hash belongs to MediaVariant; its rights belong to MediaAsset.
     *
     * @return array{sha256:string,byte_size:int,mime_type:string}
     */
    public function stageVariantCopy(
        MediaAsset $asset,
        MediaVariant $variant,
        string $variantPath,
        string $stagedPath,
        bool $mayReserveDataMining = false,
    ): array {
        if (! is_numeric($asset->getKey())
            || (int) $variant->getAttribute('media_asset_id') !== (int) $asset->getKey()
            || $variant->getAttribute('variant_kind') !== PublicMedia::THUMBNAIL_KIND
            || $variant->getAttribute('transform_profile') !== PublicMedia::PUBLIC_TRANSFORM_PROFILE
            || $variant->getAttribute('state') !== 'available'
            || $variant->getAttribute('mime_type') !== 'image/webp') {
            throw new RuntimeException('Only a matching available public-v1 WebP thumbnail may be staged.');
        }

        return $this->stageSource(
            $asset, $variant, $variantPath, $stagedPath, $mayReserveDataMining,
        );
    }

    /**
     * Both original and thumbnail pass the same source-integrity,
     * metadata-writing and readback checks.
     *
     * @return array{sha256:string,byte_size:int,mime_type:string}
     */
    private function stageSource(
        MediaAsset $asset,
        MediaAsset|MediaVariant $source,
        string $originalPath,
        string $stagedPath,
        bool $mayReserveDataMining,
    ): array {
        if (! is_file($originalPath) || ! is_readable($originalPath)
            || ! is_dir(dirname($stagedPath)) || ! is_writable(dirname($stagedPath))) {
            throw new RuntimeException('Media staging paths must be readable and writable.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($originalPath);
        if (! is_string($mime) || ! MediaTypePolicy::isImage($mime)
            || $mime !== $source->getAttribute('mime_type')) {
            throw new RuntimeException('Image source type does not match the authoritative media record.');
        }

        $expectedSize = (int) $source->getAttribute('byte_size');
        $expectedHash = $source->getAttribute('sha256');
        $actualSize = @filesize($originalPath);
        $actualHash = @hash_file('sha256', $originalPath);
        if ($expectedSize <= 0 || ! is_int($actualSize) || $expectedSize !== $actualSize
            || ! is_string($expectedHash) || ! is_string($actualHash)
            || ! hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('Immutable source does not match its recorded size and SHA-256.');
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
                $copied = stream_copy_to_stream($input, $output);
                if ($copied === false || $copied !== $expectedSize) {
                    throw new RuntimeException('Unable to copy the complete immutable media file.');
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

            // The staging boundary, not the eventual HTTP controller, owns
            // write/readback validation. A write exit code alone does not
            // prove that ExifTool embedded the requested standard tags.
            $this->assertStagedMetadata(
                $asset,
                $binary,
                $originalPath,
                $stagedPath,
                $mayReserveDataMining,
            );

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
