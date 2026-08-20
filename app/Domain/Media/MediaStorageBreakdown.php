<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class MediaStorageBreakdown
{
    /** @var array<string, string> */
    private const LABELS = [
        'artworks' => 'Artworks',
        'exhibitions' => 'Exhibitions',
        'vita' => 'Vita / CV',
        'blog' => 'Blog',
        'shared' => 'Shared across sections',
        'unassigned' => 'Unassigned library media',
        'uncatalogued' => 'Uncatalogued originals',
    ];

    /**
     * Build an exclusive attribution of the already-measured authoritative files.
     * Every measured original lands in exactly one bucket so bytes never get
     * duplicated when one reusable media asset is referenced from multiple areas.
     *
     * @param  array<string, int>  $authoritativeFiles
     * @return list<array{key:string,label:string,bytes:int,files:int,percent:float}>
     */
    public function build(array $authoritativeFiles): array
    {
        if ($authoritativeFiles === []) {
            return [];
        }

        $normalized = [];
        foreach ($authoritativeFiles as $storageKey => $bytes) {
            if ($storageKey === '' || $bytes < 0) {
                continue;
            }

            $normalized[$storageKey] = $bytes;
        }

        if ($normalized === []) {
            return [];
        }

        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->select(['id', 'storage_key'])
            ->withCount(['artworks', 'exhibitions', 'cvEntries', 'blogPosts'])
            ->whereIn('storage_key', array_keys($normalized))
            ->get();

        /** @var array<string, MediaAsset> $assetsByStorageKey */
        $assetsByStorageKey = [];
        foreach ($assets as $asset) {
            $storageKey = (string) $asset->getAttribute('storage_key');
            if ($storageKey !== '') {
                $assetsByStorageKey[$storageKey] = $asset;
            }
        }

        /** @var array<string, array{bytes:int,files:int}> $buckets */
        $buckets = [];
        foreach (self::LABELS as $key => $_label) {
            $buckets[$key] = ['bytes' => 0, 'files' => 0];
        }

        foreach ($normalized as $storageKey => $bytes) {
            $bucket = $this->classify($assetsByStorageKey[$storageKey] ?? null);
            $buckets[$bucket]['bytes'] += $bytes;
            $buckets[$bucket]['files']++;
        }

        $totalBytes = (int) array_sum($normalized);
        $rows = [];
        foreach (self::LABELS as $key => $label) {
            if ($buckets[$key]['files'] === 0) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'bytes' => $buckets[$key]['bytes'],
                'files' => $buckets[$key]['files'],
                'percent' => $totalBytes > 0
                    ? round(($buckets[$key]['bytes'] / $totalBytes) * 100, 1)
                    : 0.0,
            ];
        }

        return $rows;
    }

    private function classify(?MediaAsset $asset): string
    {
        if (! $asset instanceof MediaAsset) {
            return 'uncatalogued';
        }

        $uses = [];
        if ((int) $asset->getAttribute('artworks_count') > 0) {
            $uses[] = 'artworks';
        }
        if ((int) $asset->getAttribute('exhibitions_count') > 0) {
            $uses[] = 'exhibitions';
        }
        if ((int) $asset->getAttribute('cv_entries_count') > 0) {
            $uses[] = 'vita';
        }
        if ((int) $asset->getAttribute('blog_posts_count') > 0) {
            $uses[] = 'blog';
        }

        return match (count($uses)) {
            0 => 'unassigned',
            1 => $uses[0],
            default => 'shared',
        };
    }
}
