<?php

namespace App\Domain\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MediaCapacityService
{
    private const WARNING_RATIO = 0.85;

    private const DISPLAY_CACHE_SECONDS = 300;

    /**
     * @return array{configured:bool,measurement_available:bool,status:'unconfigured'|'healthy'|'near_capacity'|'full'|'unavailable',quota_bytes:int|null,authoritative_bytes:int|null,generated_bytes:int|null,managed_bytes:int|null,remaining_bytes:int|null,authoritative_ratio:float|null,original_files:int|null,generated_files:int|null,authoritative_file_bytes:array<string,int>|null}
     */
    public function snapshot(): array
    {
        $quota = $this->quotaBytes();

        try {
            [$authoritativeBytes, $originalFiles, $authoritativeFiles] = $this->measurePrefix('originals', true);
            [$generatedBytes, $generatedFiles] = $this->measurePrefix('variants');
        } catch (Throwable) {
            return [
                'configured' => $quota !== null,
                'measurement_available' => false,
                'status' => 'unavailable',
                'quota_bytes' => $quota,
                'authoritative_bytes' => null,
                'generated_bytes' => null,
                'managed_bytes' => null,
                'remaining_bytes' => null,
                'authoritative_ratio' => null,
                'original_files' => null,
                'generated_files' => null,
                'authoritative_file_bytes' => null,
            ];
        }

        $remaining = $quota === null ? null : max(0, $quota - $authoritativeBytes);
        $ratio = $quota === null ? null : $authoritativeBytes / $quota;
        $status = 'healthy';
        if ($quota === null) {
            $status = 'unconfigured';
        } elseif ($authoritativeBytes >= $quota) {
            $status = 'full';
        } elseif ($ratio >= self::WARNING_RATIO) {
            $status = 'near_capacity';
        }

        return [
            'configured' => $quota !== null,
            'measurement_available' => true,
            'status' => $status,
            'quota_bytes' => $quota,
            'authoritative_bytes' => $authoritativeBytes,
            'generated_bytes' => $generatedBytes,
            'managed_bytes' => $authoritativeBytes + $generatedBytes,
            'remaining_bytes' => $remaining,
            'authoritative_ratio' => $ratio,
            'original_files' => $originalFiles,
            'generated_files' => $generatedFiles,
            'authoritative_file_bytes' => $authoritativeFiles,
        ];
    }

    /** @return array{configured:bool,measurement_available:bool,status:'unconfigured'|'healthy'|'near_capacity'|'full'|'unavailable',quota_bytes:int|null,authoritative_bytes:int|null,generated_bytes:int|null,managed_bytes:int|null,remaining_bytes:int|null,authoritative_ratio:float|null,original_files:int|null,generated_files:int|null,authoritative_file_bytes:array<string,int>|null} */
    public function cachedSnapshot(): array
    {
        return Cache::remember($this->displayCacheKey(), self::DISPLAY_CACHE_SECONDS, fn (): array => $this->snapshot());
    }

    public function forgetCachedSnapshot(): void
    {
        Cache::forget($this->displayCacheKey());
    }

    public function assertCanStoreOriginal(int $bytes): void
    {
        if ($bytes <= 0) {
            throw ValidationException::withMessages(['media' => 'The uploaded media has an invalid size.']);
        }

        $quota = $this->quotaBytes();
        if ($quota === null) {
            return;
        }

        $snapshot = $this->snapshot();
        if (empty($snapshot['measurement_available'])) {
            throw ValidationException::withMessages(['media' => 'Storage capacity could not be verified. Try the upload again later.']);
        }

        $authoritativeBytes = $snapshot['authoritative_bytes'];
        if (is_int($authoritativeBytes)) {
            if ($authoritativeBytes + $bytes > $quota) {
                throw ValidationException::withMessages(['media' => 'The media storage allowance is full. Remove unused original media or ask the operator to increase the allowance before uploading.']);
            }

            return;
        }

        throw ValidationException::withMessages(['media' => 'Storage capacity could not be verified. Try the upload again later.']);
    }

    private function quotaBytes(): ?int
    {
        $configured = config('media.quota_bytes');
        if (is_int($configured)) {
            return $configured > 0 ? $configured : null;
        }
        if (is_string($configured) && ctype_digit($configured)) {
            $quota = (int) $configured;

            return $quota > 0 ? $quota : null;
        }

        return null;
    }

    private function displayCacheKey(): string
    {
        return 'media-capacity:display:'.sha1((string) config('media.disk').'|'.(string) ($this->quotaBytes() ?? 'none'));
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
