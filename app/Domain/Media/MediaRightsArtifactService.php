<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\MediaRightsArtifact;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Single owner for immutable rights-bearing file artifacts.
 *
 * Offline publication preparation only: public requests MUST NOT call this
 * service or ExifTool. A later publication boundary selects registry IDs in
 * Working/Committed media rows; current public URLs remain untouched until
 * that boundary and its revisioned routes are verified.
 *
 * The caller must serialize preparation with PublicationSnapshot::LOCK_KEY.
 */
final class MediaRightsArtifactService
{
    public function __construct(
        private readonly MediaRightsArtifactRevision $revisions,
        private readonly MediaIptcXmpWriter $writer,
        private readonly MediaCapacityService $capacity,
    ) {}

    public function prepareOriginal(MediaAsset $asset, bool $mayReserveDataMining = false): MediaRightsArtifact
    {
        if ($asset->getAttribute('state') !== 'available') {
            throw new RuntimeException('Only available images may be prepared for publication.');
        }

        return $this->prepare($asset, $asset, 'original', $mayReserveDataMining);
    }

    public function prepareThumbnail(
        MediaAsset $asset,
        MediaVariant $variant,
        bool $mayReserveDataMining = false,
    ): MediaRightsArtifact {
        if ($asset->getAttribute('state') !== 'available'
            || $variant->getAttribute('state') !== 'available') {
            throw new RuntimeException('Only an available image and thumbnail may be prepared.');
        }

        return $this->prepare($asset, $variant, 'thumbnail', $mayReserveDataMining);
    }

    private function prepare(
        MediaAsset $asset,
        MediaAsset|MediaVariant $source,
        string $role,
        bool $mayReserveDataMining,
    ): MediaRightsArtifact {
        $revision = $this->revisions->forSource($asset, $source, $mayReserveDataMining);
        $mime = (string) $source->getAttribute('mime_type');
        $extension = MediaTypePolicy::extensionFor($mime);
        if ($extension === null || ! MediaTypePolicy::isImage($mime)) {
            throw new RuntimeException('Only supported images may have file-rights artifacts.');
        }

        $assetId = (int) $asset->getKey();
        $sourceSha = (string) $source->getAttribute('sha256');
        $key = 'variants/rights/'.$revision.'.'.$extension;

        $disk = Storage::disk((string) config('media.disk'));
        $existing = MediaRightsArtifact::query()->where('revision', $revision)->first();
        if ($existing instanceof MediaRightsArtifact) {
            $this->verifyExisting($existing, $assetId, $role, $sourceSha, $key, $mime);

            return $existing;
        }

        $sourceKey = (string) $source->getAttribute('storage_key');
        if (! str_starts_with($sourceKey, $role === 'original' ? 'originals/' : 'variants/')
            || str_contains($sourceKey, '..') || str_contains($sourceKey, '\\')
            || ($role === 'thumbnail' && str_starts_with($sourceKey, 'variants/rights/'))
            || ! $disk->exists($sourceKey)) {
            throw new RuntimeException('Canonical source media is unavailable.');
        }

        // Reserve the source-size floor before writing temporary bytes.
        // The staged copy belongs under generated storage for quota accounting.
        $this->capacity->assertCanStoreOriginal((int) $source->getAttribute('byte_size'));
        $stagingDirectory = 'variants/rights-staging';
        if (! $disk->makeDirectory($stagingDirectory) && ! $disk->exists($stagingDirectory)) {
            throw new RuntimeException('Cannot create private rights staging directory.');
        }
        if (! $disk->makeDirectory('variants/rights') && ! $disk->exists('variants/rights')) {
            throw new RuntimeException('Cannot create immutable rights artifact directory.');
        }

        $stagingKey = $stagingDirectory.'/'.bin2hex(random_bytes(20)).'.'.$extension;
        $stagingPath = $disk->path($stagingKey);
        $sourcePath = $disk->path($sourceKey);
        $destinationPath = $disk->path($key);

        try {
            $metadata = $source instanceof MediaVariant
                ? $this->writer->stageVariantCopy($asset, $source, $sourcePath, $stagingPath, $mayReserveDataMining)
                : $this->writer->stageCopy($asset, $sourcePath, $stagingPath, $mayReserveDataMining);

            // Staging already counts toward the generated-storage quota.
            // A hard link installs the final name without duplicating bytes.
            $this->capacity->assertWithinAllowance();
            if (! is_file($destinationPath)) {
                if (! @link($stagingPath, $destinationPath) && ! is_file($destinationPath)) {
                    throw new RuntimeException('Cannot atomically install immutable rights artifact.');
                }
            }

            if (! is_file($destinationPath)
                || ! hash_equals($metadata['sha256'], (string) @hash_file('sha256', $destinationPath))
                || @filesize($destinationPath) !== $metadata['byte_size']) {
                throw new RuntimeException('Existing immutable rights artifact does not match the staged representation.');
            }

            // A failed database transaction may leave a verified content-keyed
            // file. It is never served without its committed registry binding.
            $artifact = MediaRightsArtifact::query()->create([
                'media_asset_id' => $assetId,
                'role' => $role,
                'revision' => $revision,
                'storage_key' => $key,
                'source_sha256' => $sourceSha,
                'sha256' => $metadata['sha256'],
                'byte_size' => $metadata['byte_size'],
                'mime_type' => $mime,
                'created_at' => now(),
            ]);

            return $artifact;
        } finally {
            // Keep the content-keyed destination: historical revisions may
            // already reference it. Only discard this unpublished temp copy.
            @unlink($stagingPath);
            // Both successful writes and failed/rolled-back file staging can
            // change physical storage. Let the existing capacity owner
            // refresh its UI projection on the next explicit measurement.
            $this->capacity->forgetCachedSnapshot();
        }
    }

    private function verifyExisting(
        MediaRightsArtifact $artifact,
        int $assetId,
        string $role,
        string $sourceSha,
        string $key,
        string $mime,
    ): void {
        if ((int) $artifact->getAttribute('media_asset_id') !== $assetId
            || $artifact->getAttribute('role') !== $role
            || $artifact->getAttribute('source_sha256') !== $sourceSha
            || $artifact->getAttribute('storage_key') !== $key
            || $artifact->getAttribute('mime_type') !== $mime) {
            throw new RuntimeException('Rights artifact identity conflicts with its immutable registry.');
        }

        $path = Storage::disk((string) config('media.disk'))->path($key);
        $size = @filesize($path);
        $hash = @hash_file('sha256', $path);
        if (! is_int($size) || $size <= 0
            || $size !== (int) $artifact->getAttribute('byte_size')
            || ! is_string($hash)
            || ! hash_equals((string) $artifact->getAttribute('sha256'), $hash)) {
            throw new RuntimeException('Previously staged rights artifact is missing or corrupt.');
        }
    }
}
