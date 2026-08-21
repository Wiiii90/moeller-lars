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
     * @param  array<string, int>  $authoritativeFiles
     * @return list<array{key:string,label:string,bytes:int,files:int,percent:float}>
     */
    public function build(array $authoritativeFiles): array
    {
        return $this->analyze($authoritativeFiles, 0)['breakdown'];
    }

    /**
     * Build exclusive library-use attribution and a small, path-free list of the
     * largest authoritative originals from the same measured file snapshot.
     *
     * @param  array<string, int>  $authoritativeFiles
     * @return array{breakdown:list<array{key:string,label:string,bytes:int,files:int,percent:float}>,heavy_consumers:list<array{label:string,classification:string,bytes:int}>}
     */
    public function analyze(array $authoritativeFiles, int $heavyLimit = 5): array
    {
        $normalized = $this->normalize($authoritativeFiles);
        if ($normalized === []) {
            return ['breakdown' => [], 'heavy_consumers' => []];
        }

        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->withCount(['artworks', 'exhibitions', 'cvEntries', 'blogPosts'])
            ->whereIn('storage_key', array_keys($normalized))
            ->get(['id', 'storage_key', 'original_filename']);

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

        arsort($normalized, SORT_NUMERIC);
        $heavyConsumers = [];
        foreach (array_slice($normalized, 0, max(0, $heavyLimit), true) as $storageKey => $bytes) {
            $asset = $assetsByStorageKey[$storageKey] ?? null;
            $bucket = $this->classify($asset);
            $filename = $asset?->getAttribute('original_filename');

            $heavyConsumers[] = [
                'label' => is_string($filename) && trim($filename) !== '' ? $filename : 'Uncatalogued original',
                'classification' => self::LABELS[$bucket],
                'bytes' => $bytes,
            ];
        }

        return ['breakdown' => $rows, 'heavy_consumers' => $heavyConsumers];
    }

    /** @param array<string, int> $authoritativeFiles
     *  @return array<string, int>
     */
    private function normalize(array $authoritativeFiles): array
    {
        $normalized = [];
        foreach ($authoritativeFiles as $storageKey => $bytes) {
            if ($storageKey === '' || $bytes < 0) {
                continue;
            }

            $normalized[$storageKey] = $bytes;
        }

        return $normalized;
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
