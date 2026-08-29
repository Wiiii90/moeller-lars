<?php

namespace App\Domain\Publication;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class PublicationMediaCleanupService
{
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
            ->leftJoin('committed.media_assets', 'committed.media_assets.id', '=', 'publication_media_cleanups.media_asset_id')
            ->where(function ($query): void {
                $query->whereNull('committed.media_assets.id')
                    ->orWhere('committed.media_assets.state', 'deleted');
            })
            ->orderBy('publication_media_cleanups.id')
            ->get([
                'publication_media_cleanups.id',
                'publication_media_cleanups.storage_key',
            ]);

        foreach ($rows as $row) {
            $key = (string) $row->storage_key;
            if (! $this->deleteKey($key)) {
                continue;
            }

            DB::table('publication_media_cleanups')->where('id', $row->id)->delete();
        }
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
