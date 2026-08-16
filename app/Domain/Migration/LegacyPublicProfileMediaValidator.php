<?php

namespace App\Domain\Migration;

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaIntegrityService;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class LegacyPublicProfileMediaValidator
{
    private readonly MediaIntegrityService $mediaIntegrityService;

    public function __construct(MediaIntegrityService $mediaIntegrityService)
    {
        $this->mediaIntegrityService = $mediaIntegrityService;
    }

    /** @return array{source:int,target:int,errors:list<string>} */
    public function validate(string $manifestPath): array
    {
        $manifest = $this->manifest($manifestPath);
        $snapshotBatch = $this->requiredString($manifest, 'batch');
        $profileMedia = $this->requiredObject($manifest, 'profile_media');
        $legacySource = $this->requiredString($profileMedia, 'legacy_source');
        $mediaPath = $this->requiredString($profileMedia, 'media_path');
        $expectedBytes = $this->requiredInteger($profileMedia, 'media_byte_size');
        $expectedSha = strtolower($this->requiredString($profileMedia, 'media_sha256'));
        $expectedAlt = $this->requiredString($profileMedia, 'alt_text');

        if ($legacySource !== LegacyPublicCvImporter::SOURCE) {
            throw new RuntimeException('Legacy portrait source does not match the verified public Vita source.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $expectedSha) !== 1) {
            throw new RuntimeException('Legacy portrait manifest SHA-256 is invalid.');
        }

        $errors = [];
        $cvEntry = DB::table('cv_entries')
            ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
            ->where('legacy_id', 1)
            ->first();
        if (is_object($cvEntry) === false) {
            $errors[] = 'Verified legacy biography entry is missing.';

            return ['source' => 1, 'target' => 0, 'errors' => $errors];
        }

        $assetId = $cvEntry->image_media_asset_id ?? null;
        if (is_int($assetId) === false) {
            $errors[] = 'Verified legacy biography portrait is not attached.';

            return ['source' => 1, 'target' => 0, 'errors' => $errors];
        }

        $profileAssets = MediaAsset::query()
            ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
            ->where('migration_batch_id', LegacyPublicCvImporter::BATCH)
            ->get();
        if ($profileAssets->count() !== 1) {
            $errors[] = 'Exactly one migrated legacy Vita media asset is required.';
        }

        $asset = $profileAssets->firstWhere('id', $assetId);
        if (($asset instanceof MediaAsset) === false) {
            $errors[] = 'Attached legacy biography portrait does not resolve to the canonical migrated Vita media asset.';

            return ['source' => 1, 'target' => $profileAssets->count(), 'errors' => $errors];
        }

        $checks = [
            'state' => 'available',
            'sha256' => $expectedSha,
            'byte_size' => $expectedBytes,
            'alt_text' => $expectedAlt,
            'legacy_source' => $legacySource,
            'legacy_path' => $mediaPath,
            'legacy_filename' => basename(str_replace('\\', '/', $mediaPath)),
            'legacy_byte_size' => $expectedBytes,
            'migration_batch_id' => LegacyPublicCvImporter::BATCH,
        ];
        foreach ($checks as $field => $expected) {
            if ($asset->getAttribute($field) !== $expected) {
                $errors[] = "Legacy portrait field {$field} does not match the reviewed manifest.";
            }
        }

        $metadata = $asset->getAttribute('metadata');
        if (is_array($metadata) === false || ($metadata['legacy_snapshot_batch'] ?? null) !== $snapshotBatch) {
            $errors[] = 'Legacy portrait snapshot provenance does not match the reviewed manifest batch.';
        }

        foreach ($this->mediaIntegrityService->issues($asset) as $issue) {
            $errors[] = "Legacy portrait media integrity failure: {$issue}.";
        }

        $publicThumbnailCount = $asset->variants()
            ->where('state', 'available')
            ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
            ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
            ->count();
        if ($publicThumbnailCount !== 1) {
            $errors[] = 'Legacy portrait does not have exactly one available public-v1 thumbnail.';
        }

        return [
            'source' => 1,
            'target' => $profileAssets->count(),
            'errors' => $errors,
        ];
    }

    /** @return array<string, mixed> */
    private function manifest(string $path): array
    {
        $file = realpath($path);
        if ($file === false || is_file($file) === false) {
            throw new RuntimeException('Legacy manifest does not exist.');
        }

        $json = file_get_contents($file);
        if (is_string($json) === false) {
            throw new RuntimeException('Legacy manifest could not be read.');
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy manifest is not valid JSON.', 0, $exception);
        }
        if (is_array($data) === false) {
            throw new RuntimeException('Legacy manifest root must be an object.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function requiredObject(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (is_array($value) === false || array_is_list($value)) {
            throw new RuntimeException("Legacy manifest field {$key} must be an object.");
        }

        return $value;
    }

    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (is_string($value) === false || trim($value) === '') {
            throw new RuntimeException("Legacy manifest field {$key} must be a non-empty string.");
        }

        return $value;
    }

    private function requiredInteger(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value) === false) {
            throw new RuntimeException("Legacy manifest field {$key} must be an integer.");
        }

        return $value;
    }
}
