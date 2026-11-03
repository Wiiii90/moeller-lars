<?php

namespace App\Domain\Publication;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\MediaReferenceQuery;
use App\Models\MediaAsset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class PublicationMediaCleanupService
{
    public function __construct(
        private readonly MediaReferenceQuery $referenceQuery,
        private readonly MediaCapacityService $capacity,
    ) {}

    /** @param list<string> $storageKeys */
    public function queue(int $mediaAssetId, array $storageKeys): void
    {
        $rows = collect($storageKeys)
            ->filter(static fn (string $key): bool => $key !== '')
            ->unique()
            ->map(static fn (string $key): array => [
                'media_asset_id' => $mediaAssetId,
                'storage_key' => $key,
                'created_at' => now(),
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('publication_media_cleanups')->insertOrIgnore($rows);
        }
    }

    /**
     * Acknowledge only physically completed files. If deletion failed,
     * retain the same outbox rows so drain() can retry idempotently.
     *
     * @param list<string> $storageKeys
     */
    public function acknowledgeRemoved(int $mediaAssetId, array $storageKeys): void
    {
        if ($mediaAssetId < 1 || $storageKeys === []) {
            return;
        }

        DB::table('publication_media_cleanups')
            ->where('media_asset_id', $mediaAssetId)
            ->whereIn('storage_key', $storageKeys)
            ->delete();
    }

    public function queueAbandonedWorkingMedia(): void
    {
        $keysByAsset = [];

        $assets = DB::table('media_assets as working')
            ->leftJoin('committed.media_assets as committed', 'committed.id', '=', 'working.id')
            ->whereNull('committed.id')
            ->get(['working.id', 'working.storage_key']);

        foreach ($assets as $asset) {
            $assetId = (int) $asset->id;
            $key = (string) ($asset->storage_key ?? '');
            if ($assetId > 0 && $key !== '') {
                $keysByAsset[$assetId][] = $key;
            }
        }

        $variants = DB::table('media_variants as working')
            ->leftJoin('committed.media_variants as committed', 'committed.id', '=', 'working.id')
            ->whereNull('committed.id')
            ->get(['working.media_asset_id', 'working.storage_key']);

        foreach ($variants as $variant) {
            $assetId = (int) $variant->media_asset_id;
            $key = (string) ($variant->storage_key ?? '');
            if ($assetId > 0 && $key !== '') {
                $keysByAsset[$assetId][] = $key;
            }
        }

        foreach ($keysByAsset as $assetId => $keys) {
            $this->queue((int) $assetId, array_values(array_unique($keys)));
        }
    }

    /**
     * @param list<string> $storageKeys
     * @return bool True only if physical cleanup completed; false if a
     *              committed or historical Publication owner requires deferral.
     */
    public function deleteNow(array $storageKeys): bool
    {
        $mediaAssetId = $this->resolveMediaAssetId($storageKeys);
        if ($mediaAssetId === null) {
            // Never physically delete keys without a single proven canonical
            // owner. The separately guarded orphan sweep handles unowned
            // paths; deleteNow cannot use a guessed owner from another key.
            report(new RuntimeException('Media cleanup could not prove one owner for all requested storage keys.'));

            return false;
        }
        if (! $this->canDeletePhysicalMedia($mediaAssetId)) {
            $this->queue($mediaAssetId, $storageKeys);

            return false;
        }

        $failed = [];
        $changed = false;

        foreach (array_values(array_unique($storageKeys)) as $key) {
            if ($key === '') {
                continue;
            }

            if (! $this->deleteKey($key)) {
                $failed[] = $key;

                continue;
            }

            $changed = true;
        }

        if ($changed) {
            $this->refreshCapacitySnapshot();
        }

        if ($failed !== []) {
            throw new RuntimeException('Media storage cleanup failed for: '.implode(', ', $failed));
        }

        return true;
    }

    public function drain(): int
    {
        // Seek by ID in bounded batches. A protected Publication may leave
        // a large outbox waiting for its restore owner to expire; never load
        // the whole queue into the web worker's memory.
        $rows = DB::table('publication_media_cleanups')
            ->select(['id', 'media_asset_id', 'storage_key'])
            ->lazyById(100);
        $changed = false;
        $removedFiles = 0;

        foreach ($rows as $row) {
            $mediaAssetId = (int) $row->media_asset_id;
            if (! $this->canDeletePhysicalMedia($mediaAssetId)) {
                continue;
            }

            $key = (string) $row->storage_key;
            // The outbox records intent, not ownership authority. Check the
            // actual key against all current, committed and retained owners:
            // another asset must never lose its file because of a stale row.
            if ($key === '' || $this->storageKeyOwners($key) !== [$mediaAssetId]) {
                report(new RuntimeException('Media cleanup ownership conflict; retaining queued file: '.$key));
                continue;
            }

            $removed = false;
            if (! $this->deleteKey($key, $removed)) {
                continue;
            }

            DB::table('publication_media_cleanups')->where('id', $row->id)->delete();
            $changed = true;
            $removedFiles += (int) $removed;
        }

        if ($changed) {
            $this->refreshCapacitySnapshot();
        }

        // Reuse this canonical post-Commit/Undo/Reset cleanup boundary. A
        // small bounded pass reclaims only unreferenced registry entries,
        // leaving full orphan-directory scans to the explicit operator CLI.
        if (config('media.rights_preparation_enabled', false) === true) {
            try {
                $rights = $this->reclaimRightsArtifacts(20, false);
                $removedFiles += $rights['files_removed'];
            } catch (Throwable $exception) {
                // Publication is already committed; cleanup is independently
                // retryable and must not rewrite the published result.
                report($exception);
            }
        }

        return $removedFiles;
    }


    /**
     * Reclaim only genuine filesystem orphans: canonical-looking originals or
     * previews with no registry entry, committed owner, history owner or
     * pending publication cleanup. Unassigned but registered media is NEVER
     * a candidate. Preserve recent files and serialize against all ingests.
     *
     * @return array{files:int,bytes:int,failed:int}
     */
    public function reclaimUncataloguedMedia(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Orphan recovery is limited to 1-100 files.');
        }

        $lock = Cache::lock('media-ingest', 420);
        if (! $lock->get()) {
            throw new RuntimeException('A media upload is in progress. Free storage can be retried later.');
        }

        try {
            return DB::transaction(function () use ($limit): array {
                DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

                $disk = Storage::disk((string) config('media.disk'));
                $now = time();
                $orphans = [];

                // Flat canonical directories only. Never inspect private
                // upload staging, rights artifacts or arbitrary operator files.
                foreach (['originals', 'variants'] as $folder) {
                    $directory = $disk->path($folder);
                    if (! is_dir($directory)) {
                        continue;
                    }

                    foreach (new \DirectoryIterator($directory) as $entry) {
                        if ($entry->isDot() || ! $entry->isFile()) {
                            continue;
                        }

                        $name = $entry->getFilename();
                        $isCanonical = $folder === 'originals'
                            ? preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\\.(?:jpg|jpeg|png|webp|mp4|webm|mp3|m4a|ogg|wav)$/D', $name) === 1
                            : (preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}-(?:thumbnail|poster)\\.webp$/D', $name) === 1
                                || preg_match('/^cutover-[1-9][0-9]*-[a-f0-9]{64}\\.webp$/D', $name) === 1);
                        if (! $isCanonical || $entry->getMTime() >= $now - 600) {
                            continue;
                        }

                        $key = $folder.'/'.$name;
                        if ($this->storageKeyHasOwner($key)) {
                            continue;
                        }

                        $orphans[] = ['key' => $key, 'size' => max(0, (int) $entry->getSize())];
                        if (count($orphans) >= $limit) {
                            break 2;
                        }
                    }
                }

                // All ownership checks have succeeded BEFORE the first write.
                // Any unexpected DB/schema failure therefore fails closed.
                $files = 0;
                $bytes = 0;
                $failed = 0;
                foreach ($orphans as $candidate) {
                    $removed = false;
                    if (! $this->deleteKey($candidate['key'], $removed)) {
                        $failed++;
                        report(new RuntimeException('Cannot remove orphaned media key: '.$candidate['key']));
                        continue;
                    }
                    if ($removed) {
                        $files++;
                        $bytes += $candidate['size'];
                    }
                }

                return ['files' => $files, 'bytes' => $bytes, 'failed' => $failed];
            }, attempts: 1);
        } finally {
            $lock->release();
        }
    }

    private function storageKeyHasOwner(string $key): bool
    {
        return $this->storageKeyOwners($key) !== [];
    }

    /**
     * Return every known owner of one physical key across current, committed,
     * historical and pending-deletion state. A malformed historical owner is
     * represented by 0 so deletion always fails closed.
     *
     * @return list<int>
     */
    private function storageKeyOwners(string $key): array
    {
        if ($key === '') {
            return [0];
        }

        $ids = [];
        foreach (['media_assets', 'committed.media_assets'] as $table) {
            foreach (DB::table($table)->where('storage_key', $key)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        foreach (['media_variants', 'committed.media_variants'] as $table) {
            foreach (DB::table($table)->where('storage_key', $key)->pluck('media_asset_id') as $id) {
                $ids[] = (int) $id;
            }
        }

        // publication_version_rows transparently resolves the immutable
        // historical payload store. No retired cutover path is required.
        $historical = DB::table('publication_version_rows')
            ->whereIn('table_name', ['media_assets', 'media_variants'])
            ->whereRaw("payload->>'storage_key' = ?", [$key])
            ->selectRaw("table_name, row_key, payload->>'id' AS original_id, payload->>'media_asset_id' AS variant_owner_id")
            ->get();

        foreach ($historical as $row) {
            $value = (string) $row->table_name === 'media_assets'
                ? ($row->original_id ?? $row->row_key)
                : $row->variant_owner_id;
            $ids[] = is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0
                ? (int) $value
                : 0;
        }

        foreach (DB::table('publication_media_cleanups')->where('storage_key', $key)->pluck('media_asset_id') as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_map(
            static fn (int $id): int => $id > 0 ? $id : 0,
            $ids,
        )));
    }

    /**
     * Generated rights files have a different retention rule from originals:
     * every Working or historic checkpoint selection is a permanent owner.
     * Inspect only generated keys in our dedicated namespace.
     *
     * @return array{unreferenced:list<array{id:int,key:string}>,orphan_keys:list<string>}
     */
    public function rightsCleanupCandidates(int $limit = 20, bool $includeFiles = true): array
    {
        if ($limit < 1 || $limit > 20) {
            throw new \InvalidArgumentException('Rights cleanup is limited to 1-20 items.');
        }
        if (config('media.rights_preparation_enabled', false) !== true) {
            throw new RuntimeException('Rights cleanup requires the migrated offline rights-artifact registry.');
        }

        $rows = DB::table('media_rights_artifacts as artifact')
            ->whereNotExists(static function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('media_rights_artifact_selections as selection')
                    ->whereColumn('selection.media_rights_artifact_id', 'artifact.id');
            })
            ->orderBy('artifact.id')
            ->limit($limit)
            ->get(['artifact.id', 'artifact.storage_key', 'artifact.revision', 'artifact.mime_type']);

        $unused = [];
        foreach ($rows as $row) {
            $key = (string) $row->storage_key;
            if (! $this->validRightsKey($key, (string) $row->revision, (string) $row->mime_type)) {
                throw new RuntimeException('Rights registry contains an unexpected storage key.');
            }
            $unused[] = ['id' => (int) $row->id, 'key' => $key];
        }

        $orphans = [];
        $orphanBudget = $limit - count($unused);
        if ($includeFiles && $orphanBudget > 0) {
            $disk = Storage::disk((string) config('media.disk'));
            foreach (['variants/rights', 'variants/rights-staging', 'rights-staging'] as $prefix) {
                foreach ($disk->allFiles($prefix) as $key) {
                    if (! $this->isOwnedRightsFile($key) || DB::table('media_rights_artifacts')->where('storage_key', $key)->exists()) {
                        continue;
                    }
                    $orphans[] = $key;
                    if (count($orphans) >= $orphanBudget) {
                        break 2;
                    }
                }
            }
        }

        return ['unreferenced' => $unused, 'orphan_keys' => $orphans];
    }

    /**
     * Execute a small reclamation inside the SAME advisory lock used by
     * media preparation, Commit, Undo and reset. Never remove a registry
     * artifact while any scope (including old checkpoints) retains it.
     *
     * @return array{registry_removed:int,files_removed:int}
     */
    public function reclaimRightsArtifacts(int $limit = 20, bool $includeFiles = false): array
    {
        if ($limit < 1 || $limit > 20) {
            throw new \InvalidArgumentException('Rights cleanup is limited to 1-20 items.');
        }

        $result = DB::transaction(function () use ($limit, $includeFiles): array {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $candidates = $this->rightsCleanupCandidates($limit, $includeFiles);
            $disk = Storage::disk((string) config('media.disk'));
            $removedRegistry = 0;
            $removedFiles = 0;

            foreach ($candidates['unreferenced'] as $candidate) {
                $row = DB::table('media_rights_artifacts')
                    ->where('id', $candidate['id'])
                    ->lockForUpdate()
                    ->first(['id', 'storage_key', 'revision', 'mime_type']);
                if ($row === null || DB::table('media_rights_artifact_selections')
                    ->where('media_rights_artifact_id', $row->id)->exists()) {
                    continue;
                }
                $key = (string) $row->storage_key;
                if ($key !== $candidate['key'] || ! $this->validRightsKey($key, (string) $row->revision, (string) $row->mime_type)) {
                    throw new RuntimeException('Rights file identity changed during cleanup.');
                }
                if ($disk->exists($key)) {
                    if (! $disk->delete($key) || $disk->exists($key)) {
                        throw new RuntimeException('Unable to reclaim an unreferenced rights file.');
                    }
                    $removedFiles++;
                }
                DB::table('media_rights_artifacts')->where('id', $row->id)->delete();
                $removedRegistry++;
            }

            foreach ($candidates['orphan_keys'] as $key) {
                if (! $this->isOwnedRightsFile($key)
                    || DB::table('media_rights_artifacts')->where('storage_key', $key)->exists()) {
                    continue;
                }
                if ($disk->exists($key)) {
                    if (! $disk->delete($key) || $disk->exists($key)) {
                        throw new RuntimeException('Unable to reclaim an orphaned rights file.');
                    }
                    $removedFiles++;
                }
            }

            return ['registry_removed' => $removedRegistry, 'files_removed' => $removedFiles];
        }, attempts: 1);

        if ($result['files_removed'] > 0) {
            $this->refreshCapacitySnapshot();
        }

        return $result;
    }

    private function validRightsKey(string $key, string $revision, string $mime): bool
    {
        $ext = MediaTypePolicy::extensionFor($mime);

        return $ext !== null && MediaTypePolicy::isImage($mime)
            && preg_match('/^[a-f0-9]{64}$/D', $revision) === 1
            && $key === 'variants/rights/'.$revision.'.'.$ext;
    }

    private function isOwnedRightsFile(string $key): bool
    {
        return preg_match('#^variants/rights/[a-f0-9]{64}\.(?:jpg|png|webp)$#D', $key) === 1
            || preg_match('#^(?:variants/)?rights-staging/[a-f0-9]{40}\.(?:jpg|png|webp)$#D', $key) === 1;
    }

    /** @param list<string> $storageKeys */
    private function resolveMediaAssetId(array $storageKeys): ?int
    {
        $keys = array_values(array_unique(array_filter(
            $storageKeys,
            static fn (string $key): bool => $key !== '',
        )));
        if ($keys === []) {
            return null;
        }

        $assetId = null;
        foreach ($keys as $key) {
            $owners = $this->storageKeyOwners($key);
            if (count($owners) !== 1 || $owners[0] < 1) {
                return null;
            }
            if ($assetId !== null && $assetId !== $owners[0]) {
                return null;
            }
            $assetId = $owners[0];
        }

        return $assetId;
    }

    private function canDeletePhysicalMedia(int $mediaAssetId): bool
    {
        if ($mediaAssetId < 1) {
            return false;
        }

        $committedRequiresAsset = DB::table('committed.media_assets')
            ->where('id', $mediaAssetId)
            ->where('state', '<>', 'deleted')
            ->exists();
        if ($committedRequiresAsset) {
            return false;
        }

        if (Schema::hasTable('publication_version_rows') || Schema::hasView('publication_version_rows')) {
            $historicalVersionRequiresAsset = DB::table('publication_version_rows')
                ->where('table_name', 'media_assets')
                ->where('row_key', (string) $mediaAssetId)
                ->whereRaw("COALESCE(payload->>'state', '') <> 'deleted'")
                ->exists();
            if ($historicalVersionRequiresAsset) {
                return false;
            }
        }

        /** @var MediaAsset|null $workingAsset */
        $workingAsset = MediaAsset::query()->find($mediaAssetId);
        if (! $workingAsset instanceof MediaAsset) {
            return true;
        }

        if ((string) $workingAsset->getAttribute('state') !== 'deleted') {
            return false;
        }

        return ! $this->referenceQuery->isReferenced($workingAsset);
    }

    private function deleteKey(string $key, ?bool &$removed = null): bool
    {
        $disk = Storage::disk(config('media.disk'));

        try {
            $removed = false;
            $existed = $disk->exists($key);
            if ($existed && ! $disk->delete($key)) {
                return false;
            }

            $complete = ! $disk->exists($key);
            $removed = $existed && $complete;

            return $complete;
        } catch (Throwable) {
            return false;
        }
    }

    private function refreshCapacitySnapshot(): void
    {
        try {
            $this->capacity->refreshCachedSnapshot();
        } catch (Throwable $exception) {
            report($exception);
            $this->capacity->forgetCachedSnapshot();
        }
    }
}
