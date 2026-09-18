<?php

namespace App\Domain\Publication;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaReferenceQuery;
use App\Models\MediaAsset;
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

    /** @param list<string> $storageKeys */
    public function deleteNow(array $storageKeys): void
    {
        $mediaAssetId = $this->resolveMediaAssetId($storageKeys);
        if ($mediaAssetId !== null && ! $this->canDeletePhysicalMedia($mediaAssetId)) {
            $this->queue($mediaAssetId, $storageKeys);

            return;
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
    }

    public function drain(): void
    {
        $rows = DB::table('publication_media_cleanups')
            ->orderBy('id')
            ->get(['id', 'media_asset_id', 'storage_key']);
        $changed = false;

        foreach ($rows as $row) {
            $mediaAssetId = (int) $row->media_asset_id;
            if (! $this->canDeletePhysicalMedia($mediaAssetId)) {
                continue;
            }

            $key = (string) $row->storage_key;
            if (! $this->deleteKey($key)) {
                continue;
            }

            DB::table('publication_media_cleanups')->where('id', $row->id)->delete();
            $changed = true;
        }

        if ($changed) {
            $this->refreshCapacitySnapshot();
        }
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

        $assetId = DB::table('media_assets')->whereIn('storage_key', $keys)->value('id');
        if (is_numeric($assetId)) {
            return (int) $assetId;
        }

        $variantAssetId = DB::table('media_variants')->whereIn('storage_key', $keys)->value('media_asset_id');

        return is_numeric($variantAssetId) ? (int) $variantAssetId : null;
    }

    private function canDeletePhysicalMedia(int $mediaAssetId): bool
    {
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

    private function deleteKey(string $key): bool
    {
        $disk = Storage::disk(config('media.disk'));

        try {
            if ($disk->exists($key) && ! $disk->delete($key)) {
                return false;
            }

            return ! $disk->exists($key);
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
