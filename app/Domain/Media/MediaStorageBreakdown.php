<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class MediaStorageBreakdown
{
    /** @var array<string, string> */
    private const AREA_LABELS = [
        'galleries' => 'Galleries',
        'journal' => 'Journal',
        'cv' => 'CV',
        'home' => 'Home',
        'custom-pages' => 'Custom pages',
        'site-identity' => 'Site identity',
        'referenced' => 'Referenced',
        'shared' => 'Shared across areas',
        'unassigned' => 'Unassigned library media',
        'uncatalogued' => 'Uncatalogued originals',
    ];

    /** @return array<string, string> */
    public static function areaLabels(): array
    {
        return self::AREA_LABELS;
    }

    /**
     * Build one file-level storage model from the measured authoritative snapshot.
     * Every measured original contributes its bytes exactly once to the exclusive
     * area breakdown. Multi-use originals expose every concrete reference in the
     * file rows but land in the Shared bucket for the overview.
     *
     * Target statistics are deliberately non-exclusive: they answer "how much
     * storage is used by files referenced here?" and therefore may overlap across
     * targets. A file is still counted at most once inside any individual target.
     *
     * @param array<string, int> $authoritativeFiles
     * @param EloquentCollection<int, MediaAsset> $assets
     * @param array<int, list<array{area:string,area_label:string,target_key:string,target_label:string,type:string,label:string,url:?string}>> $referencesByMediaId
     * @param list<int> $referencedIds
     * @return array{
     *   breakdown:list<array{key:string,label:string,bytes:int,files:int,percent:float}>,
     *   file_rows:list<array<string,mixed>>,
     *   target_breakdown:list<array{key:string,label:string,area:string,area_label:string,bytes:int,files:int}>,
     *   attention:array<string,mixed>
     * }
     */
    public function analyze(
        array $authoritativeFiles,
        EloquentCollection $assets,
        array $referencesByMediaId,
        array $referencedIds,
    ): array {
        $normalized = $this->normalize($authoritativeFiles);
        $totalBytes = (int) array_sum($normalized);
        $referencedSet = array_fill_keys(array_map('intval', $referencedIds), true);

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
        foreach (self::AREA_LABELS as $key => $_label) {
            $buckets[$key] = ['bytes' => 0, 'files' => 0];
        }

        /** @var array<string, array{key:string,label:string,area:string,area_label:string,bytes:int,files:int}> $targets */
        $targets = [];
        $fileRows = [];
        $unreferencedBytes = 0;
        $unreferencedFiles = 0;

        foreach ($normalized as $storageKey => $bytes) {
            $asset = $assetsByStorageKey[$storageKey] ?? null;
            $assetId = $asset instanceof MediaAsset ? (int) $asset->getKey() : null;
            $references = $assetId === null ? [] : ($referencesByMediaId[$assetId] ?? []);
            $referenced = $assetId !== null && isset($referencedSet[$assetId]);

            $areas = [];
            foreach ($references as $reference) {
                $area = (string) ($reference['area'] ?? 'referenced');
                $areaLabel = (string) ($reference['area_label'] ?? self::AREA_LABELS['referenced']);
                $areas[$area] = $areaLabel;
            }

            $bucket = $this->bucketFor($asset, $referenced, array_keys($areas));
            $buckets[$bucket]['bytes'] += $bytes;
            $buckets[$bucket]['files']++;

            if ($asset instanceof MediaAsset && ! $referenced) {
                $unreferencedBytes += $bytes;
                $unreferencedFiles++;
            }

            $targetsSeenForFile = [];
            foreach ($references as $reference) {
                $targetKey = (string) ($reference['target_key'] ?? '');
                if ($targetKey === '' || isset($targetsSeenForFile[$targetKey])) {
                    continue;
                }
                $targetsSeenForFile[$targetKey] = true;
                $targets[$targetKey] ??= [
                    'key' => $targetKey,
                    'label' => (string) ($reference['target_label'] ?? $reference['label'] ?? 'Reference'),
                    'area' => (string) ($reference['area'] ?? 'referenced'),
                    'area_label' => (string) ($reference['area_label'] ?? self::AREA_LABELS['referenced']),
                    'bytes' => 0,
                    'files' => 0,
                ];
                $targets[$targetKey]['bytes'] += $bytes;
                $targets[$targetKey]['files']++;
            }

            $mime = $asset instanceof MediaAsset ? (string) $asset->getAttribute('mime_type') : '';
            $filename = $asset instanceof MediaAsset ? trim((string) $asset->getAttribute('original_filename')) : '';
            $state = $asset === null ? 'uncatalogued' : ($referenced ? 'referenced' : 'unreferenced');
            $useLabels = array_values(array_unique(array_values($areas)));

            if ($useLabels === []) {
                $useLabels = match ($state) {
                    'uncatalogued' => [self::AREA_LABELS['uncatalogued']],
                    'unreferenced' => [self::AREA_LABELS['unassigned']],
                    default => [self::AREA_LABELS['referenced']],
                };
            }

            $fileRows[] = [
                'asset_id' => $assetId,
                'filename' => $filename !== '' ? $filename : 'Uncatalogued original',
                'mime' => $mime,
                'type_label' => $mime !== '' ? MediaTypePolicy::label($mime) : 'Unknown type',
                'kind' => $mime !== '' ? MediaTypePolicy::kind($mime) : 'unknown',
                'bytes' => $bytes,
                'share' => $totalBytes > 0 ? round(($bytes / $totalBytes) * 100, 2) : 0.0,
                'bucket_key' => $bucket,
                'area_keys' => array_keys($areas),
                'use_labels' => $useLabels,
                'references' => $references,
                'reference_count' => count($references),
                'state' => $state,
                'state_label' => match ($state) {
                    'referenced' => 'Referenced',
                    'unreferenced' => 'Unused',
                    default => 'Uncatalogued',
                },
            ];
        }

        usort($fileRows, static fn (array $left, array $right): int => ($right['bytes'] <=> $left['bytes']) ?: strcmp((string) $left['filename'], (string) $right['filename']));

        $breakdown = [];
        foreach (self::AREA_LABELS as $key => $label) {
            if ($buckets[$key]['files'] === 0) {
                continue;
            }

            $breakdown[] = [
                'key' => $key,
                'label' => $label,
                'bytes' => $buckets[$key]['bytes'],
                'files' => $buckets[$key]['files'],
                'percent' => $totalBytes > 0 ? round(($buckets[$key]['bytes'] / $totalBytes) * 100, 1) : 0.0,
            ];
        }
        usort($breakdown, static fn (array $left, array $right): int => ($right['bytes'] <=> $left['bytes']) ?: strcmp((string) $left['label'], (string) $right['label']));

        $targetBreakdown = array_values($targets);
        usort($targetBreakdown, static fn (array $left, array $right): int => ($right['bytes'] <=> $left['bytes']) ?: strcmp((string) $left['label'], (string) $right['label']));

        $largestGallery = null;
        foreach ($targetBreakdown as $target) {
            if ($target['area'] === 'galleries') {
                $largestGallery = $target;
                break;
            }
        }

        $largestUnreferenced = null;
        foreach ($fileRows as $row) {
            if ($row['state'] === 'unreferenced') {
                $largestUnreferenced = $row;
                break;
            }
        }

        return [
            'breakdown' => $breakdown,
            'file_rows' => $fileRows,
            'target_breakdown' => $targetBreakdown,
            'attention' => [
                'largest_file' => $fileRows[0] ?? null,
                'largest_area' => $breakdown[0] ?? null,
                'largest_gallery' => $largestGallery,
                'largest_unreferenced' => $largestUnreferenced,
                'unreferenced_files' => $unreferencedFiles,
                'unreferenced_bytes' => $unreferencedBytes,
            ],
        ];
    }

    /** @param array<string, int> $authoritativeFiles @return array<string, int> */
    private function normalize(array $authoritativeFiles): array
    {
        $normalized = [];
        foreach ($authoritativeFiles as $storageKey => $bytes) {
            if (! is_string($storageKey) || $storageKey === '' || ! is_numeric($bytes) || (int) $bytes < 0) {
                continue;
            }

            $normalized[$storageKey] = (int) $bytes;
        }

        return $normalized;
    }

    /** @param list<string> $areas */
    private function bucketFor(?MediaAsset $asset, bool $referenced, array $areas): string
    {
        if (! $asset instanceof MediaAsset) {
            return 'uncatalogued';
        }
        if (! $referenced) {
            return 'unassigned';
        }
        if (count($areas) === 1) {
            return $areas[0];
        }
        if (count($areas) > 1) {
            return 'shared';
        }

        return 'referenced';
    }
}
