<?php

namespace App\Domain\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MediaCapacityService
{
    private const WARNING_RATIO = 0.85;

    /**
     * @return array{
     *     configured: bool,
     *     measurement_available: bool,
     *     status: 'unconfigured'|'healthy'|'near_capacity'|'full'|'unavailable',
     *     quota_bytes: int|null,
     *     authoritative_bytes: int|null,
     *     generated_bytes: int|null,
     *     managed_bytes: int|null,
     *     remaining_bytes: int|null,
     *     authoritative_ratio: float|null,
     *     original_files: int|null,
     *     generated_files: int|null
     * }
     */
    public function snapshot(): array
    {
        $quota = $this->quotaBytes();

        try {
            [$authoritativeBytes, $originalFiles] = $this->measurePrefix('originals');
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
        ];
    }

    public function assertCanStoreOriginal(int $bytes): void
    {
        if ($bytes <= 0) {
            throw ValidationException::withMessages([
                'media' => 'The uploaded media has an invalid size.',
            ]);
        }

        $quota = $this->quotaBytes();
        if ($quota === null) {
            return;
        }

        $snapshot = $this->snapshot();
        if (empty($snapshot['measurement_available'])) {
            throw ValidationException::withMessages([
                'media' => 'Storage capacity could not be verified. Try the upload again later.',
            ]);
        }

        $authoritativeBytes = $snapshot['authoritative_bytes'];
        if (is_int($authoritativeBytes)) {
            if ($authoritativeBytes + $bytes > $quota) {
                throw ValidationException::withMessages([
                    'media' => 'The media storage allowance is full. Remove unused original media or ask the operator to increase the allowance before uploading.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'media' => 'Storage capacity could not be verified. Try the upload again later.',
        ]);
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

    /** @return array{int, int} */
    private function measurePrefix(string $prefix): array
    {
        $disk = Storage::disk((string) config('media.disk'));
        $bytes = 0;
        $files = 0;

        foreach ($disk->allFiles($prefix) as $path) {
            $bytes += $disk->size($path);
            $files++;
        }

        return [$bytes, $files];
    }
}
