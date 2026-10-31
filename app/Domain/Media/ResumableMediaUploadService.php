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

        // Admission is serialized so two simultaneous starts cannot both
        // observe the same remaining capacity and oversubscribe the quota.
        return $this->admissionLock(function () use ($userId, $filename, $size): array {
            $capacity = app(MediaCapacityService::class)->snapshot();
            if (($capacity['configuration_valid'] ?? false) !== true
                || ($capacity['measurement_available'] ?? false) !== true
                || ! is_int($capacity['quota_bytes'] ?? null)
                || ! is_int($capacity['remaining_bytes'] ?? null)) {
                throw $this->invalid('The site storage allowance is not configured or cannot be measured.');
            }
            if ($size > (int) $capacity['remaining_bytes']) {
                throw $this->invalid('The selected file exceeds the remaining site storage allowance.');
            }

            $free = @disk_free_space($this->directory());
            if (! is_float($free) && ! is_int($free)) {
                throw $this->invalid('Free temporary storage could not be verified.');
            }
            // Atomic rename retains one physical original, not two copies.
            // Leave headroom for derivative generation and other server work.
            if ($free < (int) ($capacity['reserved_upload_bytes'] ?? 0) + $size + self::MIN_FREE_RESERVE) {
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
            $result = app(MediaIngestService::class)->ingestUnique($upload, fromPrivateStaging: true);
            /** @var MediaAsset $asset */
            $asset = $result['asset'];
            $meta['asset_id'] = (int) $asset->getKey();
            $meta['duplicate'] = (bool) $result['duplicate'];
            $meta['updated_at'] = time();
            $this->writeManifest($id, $meta);
            @unlink($path);
            app(MediaCapacityService::class)->forgetCachedSnapshot();
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
        if (! Str::isUuid($id)) {
            throw $this->invalid('Invalid upload identifier.');
        }

        // Shared global gate allows independent sessions to progress, while
        // exclusive cleanup waits for ALL active writers before deleting an
        // expired session's lock file. Never unlink a live flock inode.
        return $this->withLock($this->directory().'/.admission.lock', function () use ($id, $action): mixed {
            if (! is_file($this->manifestPath($id))) {
                throw $this->invalid('Upload session not found or expired.');
            }
            return $this->withLock($this->directory().'/'.$id.'.lock', $action);
        }, exclusive: false);
    }

    private function admissionLock(callable $action): mixed
    {
        return $this->withLock($this->directory().'/.admission.lock', $action);
    }

    private function withLock(string $path, callable $action, bool $exclusive = true): mixed
    {
        $lock = @fopen($path, 'c+');
        if (! is_resource($lock) || ! flock($lock, $exclusive ? LOCK_EX : LOCK_SH)) {
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
     * Reclaim expired sessions and orphan artifacts with the global gate held
     * exclusively. Shared upload locks prevent deleting a session or its lock
     * while a chunk/finalization still uses it. One invocation removes at most
     * 100 groups of artifacts; a subsequent run continues the sweep.
     */
    public function pruneExpired(): int
    {
        if (! is_dir($this->directory())) {
            return 0;
        }

        return $this->admissionLock(function (): int {
            $removed = 0;
            $cutoff = time() - self::EXPIRY_SECONDS;

            foreach (glob($this->directory().'/*.json') ?: [] as $manifest) {
                if ($removed >= 100) {
                    break;
                }

                $id = basename($manifest, '.json');
                if (! Str::isUuid($id)) {
                    continue;
                }

                // Completed sessions are kept for recovery of a lost final
                // response, but their staged multi-GB copy can go immediately.
                $meta = $this->readManifest($id);
                if (is_array($meta) && isset($meta['asset_id']) && is_file($this->partPath($id))) {
                    @unlink($this->partPath($id));
                }

                $modified = @filemtime($manifest);
                if ($modified === false || $modified >= $cutoff) {
                    continue;
                }

                // The exclusive gate ensures nobody is currently using the
                // per-session lock or writing this manifest.
                if (@unlink($manifest)) {
                    @unlink($this->partPath($id));
                    @unlink($manifest.'.tmp');
                    @unlink($this->directory().'/'.$id.'.lock');
                    $removed++;
                }
            }

            // A crash between manifest creation, atomic replacement and
            // writing can orphan .part, .json.tmp or .lock individually.
            // Match only our own UUID-based names; never touch media originals.
            foreach (['part', 'json.tmp', 'lock'] as $suffix) {
                foreach (glob($this->directory().'/*.'.$suffix) ?: [] as $path) {
                    if ($removed >= 100) {
                        break 2;
                    }
                    $id = basename($path, '.'.$suffix);
                    // An expired .json.tmp is disposable even when its
                    // corresponding committed manifest remains in use.
                    if (! Str::isUuid($id)
                        || ($suffix !== 'json.tmp' && is_file($this->manifestPath($id)))) {
                        continue;
                    }
                    $modified = @filemtime($path);
                    if ($modified !== false && $modified < $cutoff && @unlink($path)) {
                        $removed++;
                    }
                }
            }

            return $removed;
        });
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['media' => $message]);
    }
}
