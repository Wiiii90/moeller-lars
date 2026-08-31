<?php

namespace App\Domain\Publication;

use App\Domain\Media\MediaReferenceQuery;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class PublicationMediaCleanupService
{
    public function __construct(private readonly MediaReferenceQuery $referenceQuery) {}

    /** @param list<string> $storageKeys */
    public function queue(int $mediaAssetId, array $storageKeys): void
    {
        $rows = collect($storageKeys)
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
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

    /** @param list<string> $storageKeys */
    public function deleteNow(array $storageKeys): void
    {
        $mediaAssetId = $this->resolveMediaAssetId($storageKeys);
        if ($mediaAssetId !== null && ! $this->canDeletePhysicalMedia($mediaAssetId)) {
            $this->queue($mediaAssetId, $storageKeys);

            return;
        }

        $failed = [];

        foreach (array_values(array_unique($storageKeys)) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! $this->deleteKey($key)) {
                $failed[] = $key;
            }
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
        }
    }

    /** @param list<string> $storageKeys */
    private function resolveMediaAssetId(array $storageKeys): ?int
    {
        $keys = array_values(array_unique(array_filter(
            $storageKeys,
            static fn (mixed $key): bool => is_string($key) && $key !== '',
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
}
