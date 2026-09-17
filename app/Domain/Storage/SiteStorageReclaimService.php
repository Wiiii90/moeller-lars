<?php

namespace App\Domain\Storage;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminUndoContext;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaVariantRegenerationService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationSnapshot;
use App\Models\AdminActionReceipt;
use Illuminate\Support\Facades\DB;

final class SiteStorageReclaimService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly AdminUndoContext $undoContext,
        private readonly PublicationMediaCleanupService $mediaCleanup,
        private readonly MediaVariantRegenerationService $variantRegeneration,
        private readonly MediaCapacityService $capacity,
    ) {}

    /** @return array{undo_receipts:int,publication_snapshots:int,publication_rows:int,protected_snapshots:int,generated_files:int,generated_bytes:int} */
    public function reclaim(): array
    {
        $actor = $this->audit->requireActor();

        $result = DB::transaction(function () use ($actor): array {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            $protectedIds = $this->protectedPublicationCheckpointIds();
            $releasableIds = DB::table('publication_checkpoints')
                ->where('snapshot_available', true)
                ->when(
                    $protectedIds !== [],
                    static fn ($query) => $query->whereNotIn('id', $protectedIds),
                )
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $publicationRows = 0;
            if ($releasableIds !== []) {
                $publicationRows = DB::table('publication_version_rows')
                    ->whereIn('publication_checkpoint_id', $releasableIds)
                    ->count();

                $this->queueSnapshotMedia($releasableIds);

                DB::table('publication_version_rows')
                    ->whereIn('publication_checkpoint_id', $releasableIds)
                    ->delete();

                DB::table('publication_checkpoints')
                    ->whereIn('id', $releasableIds)
                    ->update(['snapshot_available' => false]);
            }

            $undoReceipts = AdminActionReceipt::query()->count();
            AdminActionReceipt::query()->delete();
            $this->queueDeletedWorkingMedia();

            $this->undoContext->withoutReceipts(
                fn () => $this->audit->record(
                    $actor,
                    'storage.reclaimed',
                    'storage',
                    1,
                ),
            );

            return [
                'undo_receipts' => $undoReceipts,
                'publication_snapshots' => count($releasableIds),
                'publication_rows' => $publicationRows,
                'protected_snapshots' => count($protectedIds),
            ];
        }, attempts: 1);

        $this->mediaCleanup->drain();
        $generated = $this->variantRegeneration->reclaimRebuildableFiles();
        $this->capacity->refreshCachedSnapshot();

        return [
            ...$result,
            'generated_files' => $generated['files'],
            'generated_bytes' => $generated['bytes'],
        ];
    }

    /** @return list<int> */
    private function protectedPublicationCheckpointIds(): array
    {
        $ids = [];

        $liveId = DB::table('publication_checkpoints')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->value('id');
        if (is_numeric($liveId)) {
            $ids[] = (int) $liveId;
        }

        $workingSourceId = DB::table('publication_working_context')
            ->where('id', 1)
            ->value('source_publication_checkpoint_id');
        if (is_numeric($workingSourceId)) {
            $ids[] = (int) $workingSourceId;
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $checkpointIds */
    private function queueSnapshotMedia(array $checkpointIds): void
    {
        $keysByAsset = [];
        $rows = DB::table('publication_version_rows')
            ->whereIn('publication_checkpoint_id', $checkpointIds)
            ->whereIn('table_name', ['media_assets', 'media_variants'])
            ->get(['table_name', 'row_key', 'payload']);

        foreach ($rows as $row) {
            $payload = $this->payload($row->payload ?? null);
            $storageKey = $payload['storage_key'] ?? null;
            if (! is_string($storageKey) || $storageKey === '') {
                continue;
            }

            $assetId = (string) ($row->table_name ?? '') === 'media_assets'
                ? $this->positiveInt($payload['id'] ?? $row->row_key ?? null)
                : $this->positiveInt($payload['media_asset_id'] ?? null);
            if ($assetId === null) {
                continue;
            }

            $keysByAsset[$assetId][] = $storageKey;
        }

        foreach ($keysByAsset as $assetId => $storageKeys) {
            $this->mediaCleanup->queue((int) $assetId, array_values(array_unique($storageKeys)));
        }
    }

    private function queueDeletedWorkingMedia(): void
    {
        $assets = DB::table('media_assets')
            ->where('state', 'deleted')
            ->get(['id', 'storage_key']);
        if ($assets->isEmpty()) {
            return;
        }

        $keysByAsset = [];
        $assetIds = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset->id;
            if ($assetId < 1) {
                continue;
            }

            $assetIds[] = $assetId;
            $storageKey = (string) ($asset->storage_key ?? '');
            if ($storageKey !== '') {
                $keysByAsset[$assetId][] = $storageKey;
            }
        }

        if ($assetIds !== []) {
            $variants = DB::table('media_variants')
                ->whereIn('media_asset_id', $assetIds)
                ->get(['media_asset_id', 'storage_key']);

            foreach ($variants as $variant) {
                $assetId = (int) $variant->media_asset_id;
                $storageKey = (string) ($variant->storage_key ?? '');
                if ($assetId > 0 && $storageKey !== '') {
                    $keysByAsset[$assetId][] = $storageKey;
                }
            }
        }

        foreach ($keysByAsset as $assetId => $storageKeys) {
            $this->mediaCleanup->queue((int) $assetId, array_values(array_unique($storageKeys)));
        }
    }

    /** @return array<string,mixed> */
    private function payload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
