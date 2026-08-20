<?php

namespace App\Domain\Migration;

use App\Domain\Media\MediaIngestService;
use App\Models\PublicContentSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

final class LegacyPublicProfileImporter
{
    public function __construct(private readonly MediaIngestService $mediaIngestService) {}

    public function import(string $manifestPath, string $mediaRoot): void
    {
        [$snapshotBatch, $profileMedia, $profileValues, $sourcePath] = $this->preflight($manifestPath, $mediaRoot);

        $cvEntry = DB::table('cv_entries')
            ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
            ->where('legacy_id', $profileMedia['cv_legacy_id'])
            ->first();
        if (is_object($cvEntry) === false) {
            throw new RuntimeException('Verified legacy biography entry is missing; import CV content before the public profile portrait.');
        }
        if (($cvEntry->image_media_asset_id ?? null) !== null) {
            throw new RuntimeException('Verified legacy biography entry already has media attached.');
        }

        $settings = PublicContentSetting::query()->sole();
        $storageKeys = [];

        try {
            DB::transaction(function () use ($snapshotBatch, $profileMedia, $profileValues, $sourcePath, $cvEntry, $settings, &$storageKeys): void {
                $upload = new UploadedFile(
                    $sourcePath,
                    basename($sourcePath),
                    null,
                    UPLOAD_ERR_OK,
                    true,
                );
                $asset = $this->mediaIngestService->ingest($upload);
                $storageKeys[] = (string) $asset->getAttribute('storage_key');
                foreach ($asset->variants()->pluck('storage_key')->all() as $key) {
                    $storageKeys[] = (string) $key;
                }

                $now = now();
                $asset->fill([
                    'alt_text' => $profileMedia['alt_text'],
                    'metadata' => ['legacy_snapshot_batch' => $snapshotBatch],
                    'legacy_source' => $profileMedia['legacy_source'],
                    'legacy_path' => $profileMedia['media_path'],
                    'legacy_filename' => basename(str_replace('\\', '/', $profileMedia['media_path'])),
                    'legacy_byte_size' => $profileMedia['media_byte_size'],
                    'migration_batch_id' => LegacyPublicCvImporter::BATCH,
                    'migrated_at' => $now,
                ]);
                $asset->save();

                if ($asset->getAttribute('sha256') !== $profileMedia['media_sha256']
                    || (int) $asset->getAttribute('byte_size') !== $profileMedia['media_byte_size']) {
                    throw new RuntimeException('Imported legacy portrait differs from the preflighted source bytes.');
                }

                $updatedCv = DB::table('cv_entries')
                    ->where('id', $cvEntry->id)
                    ->whereNull('image_media_asset_id')
                    ->update([
                        'image_media_asset_id' => $asset->getKey(),
                        'updated_at' => $now,
                    ]);
                if ($updatedCv !== 1) {
                    throw new RuntimeException('Verified legacy biography portrait could not be attached.');
                }

                $settings->fill($profileValues);
                $settings->save();
            });
        } catch (Throwable $exception) {
            $this->cleanupStorage($storageKeys, $exception);
        }
    }

    /**
     * @return array{
     *     string,
     *     array{legacy_source:string,cv_legacy_id:int,media_path:string,media_byte_size:int,media_sha256:string,alt_text:string},
     *     array{public_email:string,instagram_handle:string,legal_disclaimer:string},
     *     string
     * }
     */
    private function preflight(string $manifestPath, string $mediaRoot): array
    {
        $manifestFile = realpath($manifestPath);
        if ($manifestFile === false || is_file($manifestFile) === false) {
            throw new RuntimeException('Legacy manifest does not exist.');
        }

        $root = realpath($mediaRoot);
        if ($root === false || is_dir($root) === false) {
            throw new RuntimeException('Legacy media root does not exist.');
        }

        $json = file_get_contents($manifestFile);
        if (is_string($json) === false) {
            throw new RuntimeException('Legacy manifest could not be read.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy manifest is not valid JSON.', 0, $exception);
        }
        if (is_array($manifest) === false) {
            throw new RuntimeException('Legacy manifest root must be an object.');
        }

        $snapshotBatch = $this->requiredString($manifest, 'batch');
        $profileMedia = $this->requiredObject($manifest, 'profile_media');
        $profileValues = $this->requiredObject($manifest, 'public_profile');

        $legacySource = $this->requiredString($profileMedia, 'legacy_source');
        if ($legacySource !== LegacyPublicCvImporter::SOURCE) {
            throw new RuntimeException('Legacy portrait source does not match the verified public Vita source.');
        }

        $cvLegacyId = $this->requiredInteger($profileMedia, 'cv_legacy_id');
        if ($cvLegacyId < 1) {
            throw new RuntimeException('Legacy portrait CV reference must be a positive legacy id.');
        }

        $mediaPath = $this->requiredString($profileMedia, 'media_path');
        $sourcePath = $this->sourceMediaPath($root, $mediaPath);
        $expectedBytes = $this->requiredInteger($profileMedia, 'media_byte_size');
        $actualBytes = filesize($sourcePath);
        if (is_int($actualBytes) === false || $actualBytes !== $expectedBytes) {
            throw new RuntimeException('Legacy portrait byte size does not match the manifest.');
        }

        $expectedSha = strtolower($this->requiredString($profileMedia, 'media_sha256'));
        if (preg_match('/^[a-f0-9]{64}$/', $expectedSha) !== 1 || hash_file('sha256', $sourcePath) !== $expectedSha) {
            throw new RuntimeException('Legacy portrait checksum does not match the manifest.');
        }

        $altText = $this->requiredString($profileMedia, 'alt_text');
        $publicEmail = $this->requiredString($profileValues, 'public_email');
        if (filter_var($publicEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Legacy public profile email is invalid.');
        }
        $instagramHandle = $this->requiredString($profileValues, 'instagram_handle');
        if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagramHandle) !== 1) {
            throw new RuntimeException('Legacy public profile Instagram handle is invalid.');
        }
        $legalDisclaimer = $this->requiredString($profileValues, 'legal_disclaimer');

        return [
            $snapshotBatch,
            [
                'legacy_source' => $legacySource,
                'cv_legacy_id' => $cvLegacyId,
                'media_path' => $mediaPath,
                'media_byte_size' => $expectedBytes,
                'media_sha256' => $expectedSha,
                'alt_text' => $altText,
            ],
            [
                'public_email' => $publicEmail,
                'instagram_handle' => $instagramHandle,
                'legal_disclaimer' => $legalDisclaimer,
            ],
            $sourcePath,
        ];
    }

    private function sourceMediaPath(string $mediaRoot, string $relativePath): string
    {
        if ($relativePath === ''
            || str_contains(str_replace('\\', '/', $relativePath), '../')
            || str_starts_with($relativePath, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $relativePath) === 1) {
            throw new RuntimeException('Legacy portrait path must be a safe relative path.');
        }

        $candidate = realpath($mediaRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if ($candidate === false || is_file($candidate) === false) {
            throw new RuntimeException("Legacy portrait file is missing: {$relativePath}");
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $mediaRoot), '/').'/';
        $normalizedCandidate = str_replace('\\', '/', $candidate);
        if (str_starts_with(strtolower($normalizedCandidate), strtolower($normalizedRoot)) === false) {
            throw new RuntimeException('Legacy portrait path escapes the supplied media root.');
        }

        return $candidate;
    }

    /** @param list<string> $keys */
    private function cleanupStorage(array $keys, Throwable $original): never
    {
        $disk = Storage::disk(config('media.disk'));
        $failed = [];
        foreach (array_values(array_unique($keys)) as $key) {
            try {
                if ($disk->exists($key) && ! $disk->delete($key)) {
                    $failed[] = $key;
                }
            } catch (Throwable) {
                $failed[] = $key;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException('Legacy profile import failed and storage cleanup failed for: '.implode(', ', $failed).'. Original failure: '.$original->getMessage(), 0, $original);
        }

        throw $original;
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
