<?php

namespace App\Domain\Media;

use App\Domain\Storage\SiteStorageDatabaseUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MediaCapacityService
{
    private const WARNING_RATIO = 0.85;

    public function __construct(
        private readonly SiteStorageDatabaseUsageService $databaseUsage,
    ) {}

    /**
     * @return array{
     *   configured:bool,
     *   configuration_valid:bool,
     *   measurement_available:bool,
     *   status:'unconfigured'|'healthy'|'near_capacity'|'full'|'unavailable',
     *   quota_bytes:int|null,
     *   authoritative_bytes:int|null,
     *   generated_bytes:int|null,
     *   managed_bytes:int|null,
     *   database_bytes:int|null,
     *   site_used_bytes:int|null,
     *   reclaimable_bytes:int|null,
     *   remaining_bytes:int|null,
     *   site_used_ratio:float|null,
     *   authoritative_ratio:float|null,
     *   original_files:int|null,
     *   generated_files:int|null,
     *   authoritative_file_bytes:array<string,int>|null,
     *   database:array<string,mixed>|null
     * }
     */
    public function snapshot(): array
    {
        $quotaConfiguration = $this->quotaConfiguration();
        if (! $quotaConfiguration['valid']) {
            return $this->unavailableSnapshot(true, false, null);
        }

        $quota = $quotaConfiguration['bytes'];

        try {
            [$authoritativeBytes, $originalFiles, $authoritativeFiles] = $this->measurePrefix('originals', true);
            [$generatedBytes, $generatedFiles] = $this->measurePrefix('variants');
        } catch (Throwable) {
            return $this->unavailableSnapshot($quotaConfiguration['configured'], true, $quota);
        }

        $database = $this->databaseUsage->snapshot();
        if (($database['measurement_available'] ?? false) !== true) {
            return $this->unavailableSnapshot($quotaConfiguration['configured'], true, $quota);
        }

        $databaseBytes = max(0, (int) ($database['logical_bytes'] ?? 0));
        $siteUsedBytes = $authoritativeBytes + $generatedBytes + $databaseBytes;
        $remaining = $quota === null ? null : max(0, $quota - $siteUsedBytes);
        $ratio = $quota === null ? null : $siteUsedBytes / $quota;
        $status = 'healthy';

        if ($quota === null) {
            $status = 'unconfigured';
        } elseif ($siteUsedBytes >= $quota) {
            $status = 'full';
        } elseif ($ratio >= self::WARNING_RATIO) {
            $status = 'near_capacity';
        }

        return [
            'configured' => $quotaConfiguration['configured'],
            'configuration_valid' => true,
            'measurement_available' => true,
            'status' => $status,
            'quota_bytes' => $quota,
            'authoritative_bytes' => $authoritativeBytes,
            'generated_bytes' => $generatedBytes,
            'managed_bytes' => $authoritativeBytes + $generatedBytes,
            'database_bytes' => $databaseBytes,
            'site_used_bytes' => $siteUsedBytes,
            'reclaimable_bytes' => $generatedBytes,
            'remaining_bytes' => $remaining,
            'site_used_ratio' => $ratio,
            'authoritative_ratio' => $ratio,
            'original_files' => $originalFiles,
            'generated_files' => $generatedFiles,
            'authoritative_file_bytes' => $authoritativeFiles,
            'database' => $database,
        ];
    }

    /** @return array<string,mixed> */
    public function cachedSnapshot(): array
    {
        return Cache::rememberForever($this->displayCacheKey(), fn (): array => $this->snapshot());
    }

    /** @return array<string,mixed> */
    public function refreshCachedSnapshot(): array
    {
        $this->forgetCachedSnapshot();

        return $this->cachedSnapshot();
    }

    /**
     * Return the presentation snapshot only when another boundary has already
     * measured it. Normal admin navigation must never trigger a filesystem or
     * database-wide storage measurement on a cache miss.
     *
     * @return array<string,mixed>|null
     */
    public function cachedSnapshotIfAvailable(): ?array
    {
        $snapshot = Cache::get($this->displayCacheKey());

        return is_array($snapshot) ? $snapshot : null;
    }

    public function forgetCachedSnapshot(): void
    {
        Cache::forget($this->displayCacheKey());
    }

    public function warningThresholdPercent(): int
    {
        return (int) round(self::WARNING_RATIO * 100);
    }

    public function assertCanStoreOriginal(int $bytes): void
    {
        if ($bytes <= 0) {
            throw ValidationException::withMessages(['media' => 'The uploaded media has an invalid size.']);
        }

        $quotaConfiguration = $this->quotaConfiguration();
        if (! $quotaConfiguration['valid']) {
            throw ValidationException::withMessages(['media' => 'Storage allowance configuration could not be verified. Try the upload again later.']);
        }

        $quota = $quotaConfiguration['bytes'];
        if ($quota === null) {
            return;
        }

        try {
            [$authoritativeBytes] = $this->measurePrefix('originals');
            [$generatedBytes] = $this->measurePrefix('variants');
        } catch (Throwable) {
            throw ValidationException::withMessages(['media' => 'Storage capacity could not be verified. Try the upload again later.']);
        }

        $database = $this->databaseUsage->snapshot();
        if (($database['measurement_available'] ?? false) !== true) {
            throw ValidationException::withMessages(['media' => 'Storage capacity could not be verified. Try the upload again later.']);
        }

        $siteUsedBytes = $authoritativeBytes
            + $generatedBytes
            + max(0, (int) ($database['logical_bytes'] ?? 0));

        if ($siteUsedBytes >= $quota || $bytes > ($quota - $siteUsedBytes)) {
            throw ValidationException::withMessages(['media' => 'The site storage allowance is full. Remove reclaimable storage or ask the operator to increase the allowance before uploading.']);
        }
    }

    /** @return array{configured:bool,valid:bool,bytes:int|null} */
    private function quotaConfiguration(): array
    {
        $configured = config('media.quota_bytes');
        if ($configured === null || $configured === '') {
            return ['configured' => false, 'valid' => true, 'bytes' => null];
        }

        if (is_int($configured)) {
            return $configured > 0
                ? ['configured' => true, 'valid' => true, 'bytes' => $configured]
                : ['configured' => true, 'valid' => false, 'bytes' => null];
        }

        if (is_string($configured) && preg_match('/^[1-9][0-9]*$/D', $configured) === 1) {
            $quota = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (is_int($quota)) {
                return ['configured' => true, 'valid' => true, 'bytes' => $quota];
            }
        }

        return ['configured' => true, 'valid' => false, 'bytes' => null];
    }

    /** @return array<string,mixed> */
    private function unavailableSnapshot(bool $configured, bool $configurationValid, ?int $quota): array
    {
        return [
            'configured' => $configured,
            'configuration_valid' => $configurationValid,
            'measurement_available' => false,
            'status' => 'unavailable',
            'quota_bytes' => $quota,
            'authoritative_bytes' => null,
            'generated_bytes' => null,
            'managed_bytes' => null,
            'database_bytes' => null,
            'site_used_bytes' => null,
            'reclaimable_bytes' => null,
            'remaining_bytes' => null,
            'site_used_ratio' => null,
            'authoritative_ratio' => null,
            'original_files' => null,
            'generated_files' => null,
            'authoritative_file_bytes' => null,
            'database' => null,
        ];
    }

    private function displayCacheKey(): string
    {
        $configured = config('media.quota_bytes');
        $quotaToken = is_scalar($configured) || $configured === null
            ? var_export($configured, true)
            : get_debug_type($configured);

        return 'media-capacity:display:'.sha1((string) config('media.disk').'|'.$quotaToken);
    }

    /** @return array{int, int, array<string, int>} */
    private function measurePrefix(string $prefix, bool $captureFiles = false): array
    {
        $disk = Storage::disk((string) config('media.disk'));
        $bytes = 0;
        $files = 0;
        $fileBytes = [];

        foreach ($disk->allFiles($prefix) as $path) {
            $size = $disk->size($path);
            $bytes += $size;
            $files++;

            if ($captureFiles) {
                $fileBytes[$path] = $size;
            }
        }

        return [$bytes, $files, $fileBytes];
    }
}
