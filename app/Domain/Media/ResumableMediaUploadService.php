<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Transfer staging only. The immutable original, deduplication, derivatives,
 * authoritative quota and audit remain owned by MediaIngestService.
 * Staging lives on the persistent PRIVATE media volume, not the container overlay.
 */
final class ResumableMediaUploadService
{
    public const CHUNK_BYTES = 16 * 1024 * 1024;

    private const EXPIRY_SECONDS = 48 * 60 * 60;
    private const MIN_FREE_RESERVE = 256 * 1024 * 1024;

    /** @return array{id:string,offset:int,chunk_size:int,complete:bool,asset_id:int|null,duplicate:bool} */
    public function begin(int $userId, string $filename, int $size, ?string $resumeId): array
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        if ($filename === '' || mb_strlen($filename) > 240 || $size < 1) {
            throw $this->invalid('Select a valid nonempty media file.');
        }

        $this->ensureDirectory();
        $this->pruneExpired();

        if (is_string($resumeId) && Str::isUuid($resumeId)) {
            $existing = $this->locked($resumeId, function () use ($resumeId, $userId, $filename, $size): array {
                $meta = $this->readManifest($resumeId);
                if ($meta === null || (int) $meta['user_id'] !== $userId
                    || $meta['filename'] !== $filename || (int) $meta['size'] !== $size) {
                    throw $this->invalid('Previous upload does not match this file.');
                }

                return $this->status($resumeId, $meta);
            });

            return $existing;
        }

        $capacity = app(MediaCapacityService::class)->snapshot();
        if (($capacity['configuration_valid'] ?? false) !== true
            || ($capacity['measurement_available'] ?? false) !== true
            || ! is_int($capacity['quota_bytes'] ?? null)
            || ! is_int($capacity['remaining_bytes'] ?? null)) {
            throw $this->invalid('The site storage allowance is not configured or cannot be measured.');
        }

        // Staging isn't included in the published-media accounting. Reserve
        // unfinished transfers separately so concurrent starts cannot evade it.
        return $this->admissionLock(function () use ($userId, $filename, $size, $capacity): array {
            $reserved = 0;
            foreach (glob($this->directory().'/*.json') ?: [] as $file) {
                $meta = json_decode((string) @file_get_contents($file), true);
                if (is_array($meta) && ! isset($meta['asset_id'])) {
                    $reserved += max(0, (int) ($meta['size'] ?? 0));
                }
            }

            if ($size > (int) $capacity['remaining_bytes'] - $reserved) {
                throw $this->invalid('The selected file exceeds the remaining site storage allowance.');
            }

            $free = @disk_free_space($this->directory());
            if (! is_float($free) && ! is_int($free)) {
                throw $this->invalid('Free temporary storage could not be verified.');
            }
            // Finalization temporarily holds both staged and immutable copies.
            if ($free < $reserved + 2 * $size + self::MIN_FREE_RESERVE) {
                throw $this->invalid('Not enough physical space to safely stage and save this media file.');
            }

            $id = (string) Str::uuid();
            $meta = [
                'user_id' => $userId,
                'filename' => $filename,
                'size' => $size,
                'created_at' => time(),
                'updated_at' => time(),
            ];
            $this->writeManifest($id, $meta);
            if (@file_put_contents($this->partPath($id), '') === false) {
                @unlink($this->manifestPath($id));
                throw $this->invalid('Unable to reserve upload staging space.');
            }

            return $this->status($id, $meta);
        });
    }

    /** @return array{id:string,offset:int,chunk_size:int,complete:bool,asset_id:int|null,duplicate:bool} */
    public function append(int $userId, string $id, int $offset, mixed $input): array
    {
        return $this->locked($id, function () use ($userId, $id, $offset, $input): array {
            $meta = $this->authorizedManifest($userId, $id);
            if (isset($meta['asset_id'])) {
                return $this->status($id, $meta);
            }

            $file = @fopen($this->partPath($id), 'c+b');
            if (! is_resource($file)) {
                throw $this->invalid('The staged media file is not available.');
            }

            try {
                $current = fstat($file)['size'] ?? -1;
                if (! is_int($current) || $current !== $offset) {
                    // Caller can resume from the authoritative server offset.
                    throw $this->invalid('Upload offset changed. Resume from the server position.');
                }

                if ($offset < 0 || $offset >= (int) $meta['size'] || ! is_resource($input)) {
                    throw $this->invalid('Invalid media chunk.');
                }

                if (fseek($file, 0, SEEK_END) !== 0) {
                    throw $this->invalid('Unable to seek the staged media file.');
                }

                $remaining = min(self::CHUNK_BYTES, (int) $meta['size'] - $offset);
                $received = 0;
                while (! feof($input)) {
                    $buffer = fread($input, min(1024 * 1024, $remaining - $received + 1));
                    if ($buffer === false || $buffer === '') {
                        break;
                    }
                    $length = strlen($buffer);
                    if ($received + $length > $remaining) {
                        throw $this->invalid('Media chunk exceeds its declared boundary.');
                    }
                    $written = fwrite($file, $buffer);
                    if ($written !== $length) {
                        throw $this->invalid('Unable to save the complete media chunk.');
                    }
                    $received += $written;
                }

                if ($received === 0 || ($received !== self::CHUNK_BYTES && $offset + $received !== (int) $meta['size'])) {
                    throw $this->invalid('Incomplete media chunk; resume the upload.');
                }
                fflush($file);
                $meta['updated_at'] = time();
                $this->writeManifest($id, $meta);
                return $this->status($id, $meta);
            } finally {
                fclose($file);
            }
        });
    }

    /** @return array{id:string,offset:int,chunk_size:int,complete:bool,asset_id:int|null,duplicate:bool} */
    public function finish(int $userId, string $id): array
    {
        return $this->locked($id, function () use ($id, $userId): array {
            $meta = $this->authorizedManifest($userId, $id);
            if (isset($meta['asset_id'])) {
                return $this->status($id, $meta);
            }
            $path = $this->partPath($id);
            if (! is_file($path) || filesize($path) !== (int) $meta['size']) {
                throw $this->invalid('The media transfer is incomplete; resume it before processing.');
            }

            $upload = new UploadedFile($path, (string) $meta['filename'], null, UPLOAD_ERR_OK, true);
            // This service is the ONLY canonical ingest/persistence owner.
            $result = app(MediaIngestService::class)->ingestUnique($upload);
            /** @var MediaAsset $asset */
            $asset = $result['asset'];
            $meta['asset_id'] = (int) $asset->getKey();
            $meta['duplicate'] = (bool) $result['duplicate'];
            $meta['updated_at'] = time();
            $this->writeManifest($id, $meta);
            @unlink($path);
            return $this->status($id, $meta);
        });
    }

    /** @return array{id:string,offset:int,chunk_size:int,complete:bool,asset_id:int|null,duplicate:bool} */
    private function status(string $id, array $meta): array
    {
        $assetId = (int) ($meta['asset_id'] ?? 0);
        $offset = $assetId > 0
            ? (int) $meta['size']
            : (is_file($this->partPath($id)) ? (int) filesize($this->partPath($id)) : 0);

        return [
            'id' => $id,
            'offset' => $offset,
            'chunk_size' => self::CHUNK_BYTES,
            'complete' => $assetId > 0,
            'asset_id' => $assetId > 0 ? $assetId : null,
            'duplicate' => (bool) ($meta['duplicate'] ?? false),
        ];
    }

    private function authorizedManifest(int $userId, string $id): array
    {
        $meta = $this->readManifest($id);
        if ($meta === null || (int) $meta['user_id'] !== $userId) {
            throw $this->invalid('Upload session not found or expired.');
        }
        return $meta;
    }

    private function readManifest(string $id): ?array
    {
        $raw = @file_get_contents($this->manifestPath($id));
        $meta = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($meta) ? $meta : null;
    }

    private function writeManifest(string $id, array $meta): void
    {
        $path = $this->manifestPath($id);
        $temp = $path.'.tmp';
        if (@file_put_contents($temp, json_encode($meta, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || ! @rename($temp, $path)) {
            throw new RuntimeException('Cannot persist upload state.');
        }
    }

    private function locked(string $id, callable $action): mixed
    {
        if (! Str::isUuid($id) || ! is_file($this->manifestPath($id))) {
            // Never create per-upload lock files for guessed/expired IDs.
            throw $this->invalid('Upload session not found or expired.');
        }
        return $this->withLock($this->directory().'/'.$id.'.lock', $action);
    }

    private function admissionLock(callable $action): mixed
    {
        return $this->withLock($this->directory().'/.admission.lock', $action);
    }

    private function withLock(string $path, callable $action): mixed
    {
        $lock = @fopen($path, 'c+');
        if (! is_resource($lock) || ! flock($lock, LOCK_EX)) {
            throw $this->invalid('Upload storage is currently unavailable.');
        }
        try {
            return $action();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function directory(): string
    {
        return storage_path('app/private/incoming-media');
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory()) && ! @mkdir($this->directory(), 0700, true) && ! is_dir($this->directory())) {
            throw $this->invalid('Upload storage is not writable.');
        }
    }

    private function manifestPath(string $id): string
    {
        return $this->directory().'/'.$id.'.json';
    }

    private function partPath(string $id): string
    {
        return $this->directory().'/'.$id.'.part';
    }

    /**
     * Bounded cleanup; also callable from the standard Laravel daily scheduler.
     * Files are removed only when their upload lock can be acquired.
     */
    public function pruneExpired(): int
    {
        if (! is_dir($this->directory())) {
            return 0;
        }
        $removed = 0;
        foreach (array_slice(glob($this->directory().'/*.json') ?: [], 0, 100) as $manifest) {
            if (filemtime($manifest) !== false && filemtime($manifest) > time() - self::EXPIRY_SECONDS) {
                continue;
            }
            $id = basename($manifest, '.json');
            if (! Str::isUuid($id)) {
                continue;
            }
            $this->locked($id, function () use ($manifest, $id, &$removed): void {
                if (is_file($manifest) && filemtime($manifest) < time() - self::EXPIRY_SECONDS) {
                    @unlink($this->partPath($id));
                    if (@unlink($manifest)) {
                        $removed++;
                    }
                }
            });
            // Do not unlink a lock inode: another request may already hold it.
        }
        return $removed;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['media' => $message]);
    }
}
