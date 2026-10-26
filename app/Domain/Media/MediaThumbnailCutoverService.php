<?php

namespace App\Domain\Media;

use App\Domain\Publication\MediaRightsPublicationSelections;
use App\Domain\Publication\PublicationSnapshot;
use App\Domain\Publication\PublicationVersionService;
use App\Models\PublicationCheckpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One-time, pre-release cutover of public-v1 thumbnails to the single libvips
 * generator. Never invoked by HTTP, a timer or the normal deploy pipeline.
 *
 * Originals are immutable. Variant IDs survive, including in version rows,
 * so restoring older editorial snapshots does not require a parallel runtime
 * decoder. Checkpoint hashes are rebaselined explicitly, never silently.
 */
final class MediaThumbnailCutoverService
{
    /**
     * @return array{variants:int,checkpoints:int,rights_selections:int,assets:int}
     */
    public function preview(): array
    {
        $candidates = $this->candidates();
        $ids = array_keys($candidates);

        return [
            'variants' => count($ids),
            'checkpoints' => (int) DB::table('publication_checkpoints')
                ->where('snapshot_available', true)->count(),
            'rights_selections' => $ids === [] || ! Schema::hasTable('media_rights_artifact_selections')
                ? 0
                : (int) DB::table('media_rights_artifact_selections')
                    ->where('role', 'thumbnail')->whereIn('source_id', $ids)->count(),
            'assets' => count(array_unique(array_column($candidates, 'media_asset_id'))),
        ];
    }

    /**
     * @return array{converted:int,rebased:int,invalidated_rights:int,reclaimed:int}
     */
    public function apply(bool $rebaselineCheckpoints = false, bool $invalidateThumbnailRights = false): array
    {
        if (! app()->environment(['local', 'testing'])
            || DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Thumbnail cutover requires a local pre-release PostgreSQL database.');
        }

        $lock = Cache::lock('media-ingest', 1800);
        if (! $lock->get()) {
            throw new RuntimeException('A media operation is in progress. Retry the cutover when it finishes.');
        }

        $disk = Storage::disk((string) config('media.disk'));
        $written = [];
        $installed = false;

        try {
            // This locks out Commit/Restore while new bytes are prepared.
            // The existing single database connection owns this session lock.
            DB::select('SELECT pg_advisory_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            try {
                $items = $this->candidates();
                if ($items === []) {
                    return ['converted' => 0, 'rebased' => 0, 'invalidated_rights' => 0, 'reclaimed' => $this->reclaimUnreferencedOldThumbnails()];
                }

                $checkpoints = (int) DB::table('publication_checkpoints')
                    ->where('snapshot_available', true)->count();
                if ($checkpoints > 0 && ! $rebaselineCheckpoints) {
                    throw new RuntimeException(
                        'Existing restorable Publication checkpoints require explicit --rebaseline-checkpoints. '
                        .'All content and Undo snapshots are preserved, but their hashes necessarily change.',
                    );
                }
                if (app(MediaRightsPublicationSelections::class)->deliveryEnabled()) {
                    throw new RuntimeException('Disable rights artifact delivery before the thumbnail cutover.');
                }
                $rightsCount = Schema::hasTable('media_rights_artifact_selections')
                    ? (int) DB::table('media_rights_artifact_selections')
                        ->where('role', 'thumbnail')
                        ->whereIn('source_id', array_keys($items))
                        ->count() : 0;
                if ($rightsCount > 0 && ! $invalidateThumbnailRights) {
                    throw new RuntimeException(
                        'Existing thumbnail rights selections require explicit --invalidate-thumbnail-rights '
                        .'and a subsequent offline rights preparation before publication.',
                    );
                }

                $prepared = [];
                foreach ($items as $id => $row) {
                    $original = $this->originalFor((int) $row['media_asset_id']);
                    $key = (string) $original['storage_key'];
                    $path = $disk->path($key);
                    if (! is_file($path)
                        || ! hash_equals((string) $original['sha256'], (string) hash_file('sha256', $path))) {
                        throw new RuntimeException("Missing or changed original for media asset {$row['media_asset_id']}.");
                    }
                    $image = app(MediaDerivativeGenerator::class)->imageThumbnail($path);
                    $sha = hash('sha256', $image['bytes']);
                    $newKey = 'variants/cutover-'.$id.'-'.$sha.'.webp';

                    if ($disk->exists($newKey)) {
                        if (! hash_equals($sha, hash('sha256', $disk->get($newKey)))) {
                            throw new RuntimeException('A previous cutover derivative has conflicting bytes.');
                        }
                    } else {
                        // Old and new variants coexist until the atomic DB
                        // cutover. Reserve staging bytes before writing any
                        // new file, using the ordinary whole-site quota owner.
                        app(MediaCapacityService::class)->assertCanStoreGenerated(strlen($image['bytes']));
                        // Register ownership before writing: a failed
                        // filesystem write may leave a partial file behind.
                        // The failure cleanup must remove it as well.
                        $written[] = $newKey;
                        if (! $disk->put($newKey, $image['bytes'])) {
                            throw new RuntimeException('Unable to write the newly generated thumbnail.');
                        }
                    }

                    $prepared[$id] = [
                        'storage_key' => $newKey,
                        'sha256' => $sha,
                        'byte_size' => strlen($image['bytes']),
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'transform_profile' => MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE,
                    ];
                }

                // All DB representations are updated together. Failure leaves
                // the old originals and old variant registry intact.
                $result = DB::transaction(function () use ($prepared, $checkpoints): array {
                    DB::statement(
                        'LOCK TABLE public.media_variants, committed.media_variants, publication_version_rows, '
                        .'publication_checkpoints IN SHARE ROW EXCLUSIVE MODE',
                    );

                    foreach ($prepared as $id => $columns) {
                        DB::table('media_variants')
                            ->where('id', $id)
                            ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
                            ->update($columns);

                        DB::table('committed.media_variants')
                            ->where('id', $id)
                            ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
                            ->update($columns);

                        DB::update(
                            "UPDATE publication_version_rows SET payload = payload || ?::jsonb
                             WHERE table_name = 'media_variants'
                               AND row_key = ?
                               AND payload->>'transform_profile' = ?",
                            [
                                json_encode($columns, JSON_THROW_ON_ERROR),
                                (string) $id,
                                MediaIngestService::TRANSFORM_PROFILE,
                            ],
                        );
                    }

                    $invalidated = 0;
                    if (Schema::hasTable('media_rights_artifact_selections')) {
                        $invalidated = (int) DB::table('media_rights_artifact_selections')
                            ->where('role', 'thumbnail')
                            ->whereIn('source_id', array_keys($prepared))
                            ->delete();
                    }

                    if ($checkpoints > 0) {
                        $this->rebaselineCheckpoints();
                    }

                    return [
                        'converted' => count($prepared),
                        'rebased' => $checkpoints,
                        'invalidated_rights' => $invalidated,
                    ];
                }, attempts: 1);

                $installed = true;
                $reclaimed = $this->reclaimUnreferencedOldThumbnails();
                app(MediaCapacityService::class)->forgetCachedSnapshot();

                return [...$result, 'reclaimed' => $reclaimed];
            } finally {
                DB::select('SELECT pg_advisory_unlock(?)', [PublicationSnapshot::LOCK_KEY]);
            }
        } finally {
            // Never delete files on failed DB cutover unless we own them.
            // Successfully cut-over variants are intentionally retained.
            if (! $installed) {
                foreach ($written as $key) {
                    $disk->delete($key);
                }
            }
            $lock->release();
        }
    }

    /**
     * @return array<int,array{id:int,media_asset_id:int,storage_key:string}>
     */
    private function candidates(): array
    {
        $items = [];
        foreach (['media_variants', 'committed.media_variants'] as $table) {
            $rows = DB::table($table)
                ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
                ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
                ->where('state', 'available')
                ->get(['id', 'media_asset_id', 'storage_key']);
            foreach ($rows as $row) {
                $this->addCandidate($items, (array) $row);
            }
        }

        $rows = DB::table('publication_version_rows as rows')
            ->join('publication_checkpoints as checkpoints', 'checkpoints.id', '=', 'rows.publication_checkpoint_id')
            ->where('checkpoints.snapshot_available', true)
            ->where('rows.table_name', 'media_variants')
            ->whereRaw("rows.payload->>'transform_profile' = ?", [MediaIngestService::TRANSFORM_PROFILE])
            ->whereRaw("rows.payload->>'variant_kind' = ?", [MediaIngestService::THUMBNAIL_KIND])
            ->whereRaw("rows.payload->>'state' = 'available'")
            ->get(['rows.payload']);
        foreach ($rows as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR) : (array) $row->payload;
            $this->addCandidate($items, $payload);
        }
        // Never convert a legacy variant into a second current thumbnail
        // for the same asset; publication requires exactly one.
        foreach ($items as $item) {
            foreach (['media_variants', 'committed.media_variants'] as $table) {
                if (DB::table($table)
                    ->where('media_asset_id', $item['media_asset_id'])
                    ->where('id', '<>', $item['id'])
                    ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
                    ->where('transform_profile', MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE)
                    ->where('state', 'available')
                    ->exists()) {
                    throw new RuntimeException('An asset already has a separate libvips thumbnail. Resolve duplicate variant IDs before cutover.');
                }
            }
        }
        ksort($items, SORT_NUMERIC);

        return $items;
    }

    /** @param array<int,array{id:int,media_asset_id:int,storage_key:string}> $items
     * @param array<string,mixed> $row
     */
    private function addCandidate(array &$items, array $row): void
    {
        $id = (int) ($row['id'] ?? 0);
        $assetId = (int) ($row['media_asset_id'] ?? 0);
        $key = (string) ($row['storage_key'] ?? '');
        if ($id < 1 || $assetId < 1 || ! str_starts_with($key, 'variants/')) {
            throw new RuntimeException('Invalid legacy thumbnail identity; cannot cut over safely.');
        }
        if (isset($items[$id]) && $items[$id]['media_asset_id'] !== $assetId) {
            throw new RuntimeException("Variant {$id} has conflicting asset identity in Publication history.");
        }
        $items[$id] ??= ['id' => $id, 'media_asset_id' => $assetId, 'storage_key' => $key];
    }

    /** @return array{storage_key:string,sha256:string} */
    private function originalFor(int $assetId): array
    {
        $identity = null;
        foreach (['media_assets', 'committed.media_assets'] as $table) {
            $asset = DB::table($table)->where('id', $assetId)->first(['storage_key', 'sha256']);
            if ($asset === null) {
                continue;
            }
            $candidate = [
                'storage_key' => (string) $asset->storage_key,
                'sha256' => (string) $asset->sha256,
            ];
            if ($identity !== null && $identity !== $candidate) {
                throw new RuntimeException("Conflicting original identity for media asset {$assetId}.");
            }
            $identity = $candidate;
        }
        if ($identity !== null) {
            return $identity;
        }
        $row = DB::table('publication_version_rows')
            ->where('table_name', 'media_assets')
            ->where('row_key', (string) $assetId)
            ->first(['payload']);
        if ($row !== null) {
            $data = is_string($row->payload)
                ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR)
                : (array) $row->payload;

            return [
                'storage_key' => (string) ($data['storage_key'] ?? ''),
                'sha256' => (string) ($data['sha256'] ?? ''),
            ];
        }

        throw new RuntimeException("No canonical original for media asset {$assetId}.");
    }

    /**
     * Reclaim generated old thumbnails that no current or restorable
     * Publication state can use. Retired snapshots are not restore roots.
     */
    private function reclaimUnreferencedOldThumbnails(): int
    {
        $disk = Storage::disk((string) config('media.disk'));
        $reclaimed = 0;

        foreach ($disk->allFiles('variants') as $key) {
            if (preg_match('#^variants/[0-9a-f-]{36}-thumbnail\.webp$#D', $key) !== 1) {
                continue;
            }
            if (DB::table('media_variants')->where('storage_key', $key)->exists()
                || DB::table('committed.media_variants')->where('storage_key', $key)->exists()
                || DB::table('publication_version_rows as rows')
                    ->join('publication_checkpoints as checkpoints', 'checkpoints.id', '=', 'rows.publication_checkpoint_id')
                    ->where('checkpoints.snapshot_available', true)
                    ->where('rows.table_name', 'media_variants')
                    ->whereRaw("rows.payload->>'storage_key' = ?", [$key])
                    ->exists()) {
                continue;
            }
            if ($disk->delete($key)) {
                $reclaimed++;
            }
        }

        return $reclaimed;
    }

    /**
     * In pre-release history, rebaseline cryptographic snapshot identities
     * after changing variant content. Parent/source links and rows stay intact.
     */
    private function rebaselineCheckpoints(): void
    {
        $checkpoints = PublicationCheckpoint::query()->orderBy('id')->get()->keyBy('id');
        $hashes = [];
        foreach ($checkpoints as $checkpoint) {
            if (! $checkpoint->getAttribute('snapshot_available')) {
                $hashes[(int) $checkpoint->getKey()] = (string) ($checkpoint->getAttribute('hash') ?? '');

                continue;
            }

            $id = (int) $checkpoint->getKey();
            $context = hash_init('sha256');
            foreach (PublicationSnapshot::TABLES as $table) {
                hash_update($context, "table:{$table}\n");
                $rows = DB::table('publication_version_rows')
                    ->where('publication_checkpoint_id', $id)
                    ->where('table_name', $table)
                    ->orderByRaw('row_key::bigint')
                    ->get(['row_key', DB::raw('payload::text as payload_text')]);
                foreach ($rows as $row) {
                    hash_update($context, (string) $row->row_key."\n".(string) $row->payload_text."\n");
                }
            }

            $snapshotHash = hash_final($context);
            $parentId = $checkpoint->getAttribute('parent_publication_checkpoint_id');
            $sourceId = $checkpoint->getAttribute('source_publication_checkpoint_id');
            $parentHash = $parentId !== null ? ($hashes[(int) $parentId] ?? null) : '';
            $sourceHash = $sourceId !== null ? ($hashes[(int) $sourceId] ?? null) : '';
            if ($parentHash === null || $sourceHash === null) {
                throw new RuntimeException('Publication checkpoint links are not in chronological dependency order.');
            }

            $date = $checkpoint->getAttribute('published_at');
            $timestamp = $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d\TH:i:s.uP') : (string) $date;
            $hash = hash('sha256', implode('|', [
                $parentHash, $snapshotHash,
                (string) ($checkpoint->getAttribute('admin_user_id') ?? ''),
                $timestamp,
                (string) ($checkpoint->getAttribute('message') ?? ''),
                (string) ($checkpoint->getAttribute('change_count') ?? ''),
                (string) ($checkpoint->getAttribute('operation') ?? ''),
                $sourceHash,
            ]));
            $checkpoint->forceFill(['hash' => $hash, 'snapshot_hash' => $snapshotHash])->save();
            $hashes[$id] = $hash;
        }

        // Catch corrupted or partial version rows rather than legitimizing
        // an incoherent current Committed snapshot.
        $current = app(PublicationVersionService::class)->currentLiveCheckpoint();
        if ($current !== null) {
            $expected = app(PublicationVersionService::class)->snapshotHash('committed');
            if (! hash_equals((string) $current->fresh()->getAttribute('snapshot_hash'), $expected)) {
                throw new RuntimeException('Committed snapshot and latest rebaselined publication are inconsistent.');
            }
        }
    }
}
