<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageBreakdown;
use App\Domain\Media\MediaStorageUnits;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class StorageWorkspaceOverview
{
    public function __construct(
        private readonly MediaCapacityService $capacityService,
        private readonly MediaStorageReferenceCatalog $references,
        private readonly MediaStorageBreakdown $breakdown,
    ) {}

    /**
     * @return array{
     *   capacity:array<string,mixed>,
     *   breakdown:list<array<string,mixed>>,
     *   attention:array<string,mixed>
     * }
     */
    public function snapshot(bool $measure = false): array
    {
        $snapshot = $measure
            ? $this->capacityService->cachedSnapshot()
            : $this->capacityService->cachedSnapshotIfAvailable();

        if (! is_array($snapshot)) {
            return [
                'capacity' => $this->unmeasuredCapacity(),
                'breakdown' => [],
                'attention' => [
                    'targets' => [],
                    'capacity_segments' => [],
                ],
            ];
        }

        $analysis = $this->analyze($this->authoritativeFiles($snapshot));
        $breakdown = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);
            $row['usage_filter'] = $this->usageFilterForArea((string) ($row['key'] ?? ''));

            return $row;
        }, $this->analysisRows($analysis, 'breakdown'));

        $targets = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes($row['bytes']);

            return $row;
        }, $this->analysisRows($analysis, 'target_breakdown'));

        $attention = is_array($analysis['attention'] ?? null) ? $analysis['attention'] : [];
        foreach (['largest_file', 'largest_area', 'largest_gallery', 'largest_unreferenced'] as $key) {
            if (is_array($attention[$key] ?? null)) {
                $attention[$key]['display_bytes'] = MediaStorageUnits::formatBytes(
                    (int) ($attention[$key]['bytes'] ?? 0),
                );
            }
        }

        $attention['unreferenced_display_bytes'] = MediaStorageUnits::formatBytes(
            (int) ($attention['unreferenced_bytes'] ?? 0),
        );
        $attention['targets'] = $targets;
        $attention['capacity_segments'] = $this->capacitySegments($analysis);

        $uncatalogued = collect($breakdown)->first(
            static fn (array $row): bool => ($row['key'] ?? null) === 'uncatalogued',
        );
        $attention['uncatalogued_files'] = is_array($uncatalogued) ? (int) ($uncatalogued['files'] ?? 0) : 0;
        $attention['uncatalogued_display_bytes'] = is_array($uncatalogued)
            ? (string) $uncatalogued['display_bytes']
            : '0 B';

        return [
            'capacity' => $this->capacity($snapshot),
            'breakdown' => $breakdown,
            'attention' => $attention,
        ];
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    private function capacity(array $snapshot): array
    {
        $ratioValue = $snapshot['site_used_ratio'] ?? $snapshot['authoritative_ratio'] ?? null;
        $ratio = is_numeric($ratioValue) ? (float) $ratioValue : null;
        $configurationValid = (bool) ($snapshot['configuration_valid'] ?? false);
        $configured = (bool) ($snapshot['configured'] ?? false);
        $measurementAvailable = (bool) ($snapshot['measurement_available'] ?? false);
        $status = (string) ($snapshot['status'] ?? 'unavailable');
        $allowance = $configured && $configurationValid
            ? MediaStorageUnits::formatBytes($snapshot['quota_bytes'] ?? null)
            : '—';
        $remaining = $configured && $measurementAvailable
            ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes'] ?? null)
            : '—';

        return [
            'configured' => $configured,
            'configuration_valid' => $configurationValid,
            'measurement_available' => $measurementAvailable,
            'status' => $status,
            'status_tone' => match ($status) {
                'healthy' => 'success',
                'near_capacity' => 'warning',
                'full', 'unavailable' => 'danger',
                default => 'neutral',
            },
            'status_label' => match ($status) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Storage healthy',
                'unavailable' => $configurationValid ? 'Measurement unavailable' : 'Allowance unavailable',
                default => 'Allowance not configured',
            },
            'percent' => $configured && $measurementAvailable && $ratio !== null
                ? round(min(1, max(0, $ratio)) * 100, 1)
                : null,
            'site_used' => MediaStorageUnits::formatBytes($snapshot['site_used_bytes'] ?? $snapshot['authoritative_bytes'] ?? null),
            'authoritative' => MediaStorageUnits::formatBytes($snapshot['authoritative_bytes'] ?? null),
            'generated' => MediaStorageUnits::formatBytes($snapshot['generated_bytes'] ?? null),
            'remaining' => $remaining,
            'allowance' => $allowance,
            'remaining_detail' => match (true) {
                ! $configurationValid => 'Allowance unavailable',
                ! $configured => 'No allowance configured',
                ! $measurementAvailable => 'Measurement unavailable',
                default => 'of '.$allowance.' allowance',
            },
            'warning_threshold' => $this->capacityService->warningThresholdPercent().'%',
        ];
    }

    /** @return array<string,mixed> */
    private function unmeasuredCapacity(): array
    {
        return [
            'configured' => false,
            'configuration_valid' => true,
            'measurement_available' => false,
            'status' => 'not_measured',
            'status_tone' => 'neutral',
            'status_label' => 'Measurement needed',
            'percent' => null,
            'site_used' => '—',
            'authoritative' => '—',
            'generated' => '—',
            'remaining' => '—',
            'allowance' => '—',
            'remaining_detail' => 'Awaiting measurement',
            'warning_threshold' => $this->capacityService->warningThresholdPercent().'%',
        ];
    }

    /** @param array<string,int> $authoritativeFiles @return array<string,mixed> */
    private function analyze(array $authoritativeFiles): array
    {
        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = new EloquentCollection;
        if ($authoritativeFiles !== []) {
            $query = MediaAsset::query()->whereIn('storage_key', array_keys($authoritativeFiles));
            $this->references->eagerLoad($query);
            $assets = $query->get();
        }

        $referencesByAssetId = $this->references->referencesByAssetId($assets);
        $referencedIds = $this->references->referencedIds(
            $assets->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        return $this->breakdown->analyze(
            $authoritativeFiles,
            $assets,
            $referencesByAssetId,
            $referencedIds,
        );
    }

    /** @param array<string,mixed> $snapshot @return array<string,int> */
    private function authoritativeFiles(array $snapshot): array
    {
        $value = $snapshot['authoritative_file_bytes'] ?? null;
        if (! is_array($value)) {
            return [];
        }

        $files = [];
        foreach ($value as $path => $bytes) {
            if (is_string($path) && is_int($bytes) && $bytes >= 0) {
                $files[$path] = $bytes;
            }
        }

        return $files;
    }

    /** @param array<string,mixed> $analysis @return list<array<string,mixed>> */
    private function analysisRows(array $analysis, string $key): array
    {
        $value = $analysis[$key] ?? null;
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /** @param array<string,mixed> $analysis @return list<array<string,mixed>> */
    private function capacitySegments(array $analysis): array
    {
        /** @var array<string,array{key:string,label:string,area:string,area_label:string,bytes:int,files:int}> $segments */
        $segments = [];

        foreach ($this->analysisRows($analysis, 'file_rows') as $row) {
            $bytes = max(0, (int) ($row['bytes'] ?? 0));
            if ($bytes === 0) {
                continue;
            }

            $state = (string) ($row['state'] ?? 'referenced');
            $descriptor = match ($state) {
                'uncatalogued' => [
                    'key' => 'uncatalogued',
                    'label' => 'Uncatalogued originals',
                    'area' => 'uncatalogued',
                    'area_label' => 'Uncatalogued originals',
                ],
                'unreferenced' => [
                    'key' => 'unassigned',
                    'label' => 'Unassigned library media',
                    'area' => 'unassigned',
                    'area_label' => 'Unassigned library media',
                ],
                default => null,
            };

            if ($descriptor === null) {
                /** @var array<string,array{label:string,area:string,area_label:string}> $targets */
                $targets = [];
                $references = is_array($row['references'] ?? null) ? $row['references'] : [];
                foreach ($references as $reference) {
                    if (! is_array($reference)) {
                        continue;
                    }

                    $targetKey = trim((string) ($reference['target_key'] ?? ''));
                    if ($targetKey === '') {
                        continue;
                    }

                    $targets[$targetKey] = [
                        'label' => (string) ($reference['target_label'] ?? $reference['label'] ?? 'Reference'),
                        'area' => (string) ($reference['area'] ?? 'referenced'),
                        'area_label' => (string) ($reference['area_label'] ?? 'Referenced'),
                    ];
                }

                if (count($targets) === 1) {
                    $targetKey = array_key_first($targets);
                    $target = $targets[$targetKey];
                    $descriptor = [
                        'key' => 'target:'.$targetKey,
                        'label' => $target['label'],
                        'area' => $target['area'],
                        'area_label' => $target['area_label'],
                    ];
                } elseif (count($targets) > 1) {
                    $descriptor = [
                        'key' => 'shared',
                        'label' => 'Shared across targets',
                        'area' => 'shared',
                        'area_label' => 'Shared across areas',
                    ];
                } else {
                    $descriptor = [
                        'key' => 'referenced',
                        'label' => 'Referenced',
                        'area' => 'referenced',
                        'area_label' => 'Referenced',
                    ];
                }
            }

            $key = $descriptor['key'];
            $segments[$key] ??= [
                ...$descriptor,
                'bytes' => 0,
                'files' => 0,
            ];
            $segments[$key]['bytes'] += $bytes;
            $segments[$key]['files']++;
        }

        $rows = array_values($segments);
        usort($rows, static fn (array $left, array $right): int => ($right['bytes'] <=> $left['bytes']) ?: strcmp((string) $left['label'], (string) $right['label']));

        return array_map(static function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes($row['bytes']);

            return $row;
        }, $rows);
    }

    private function usageFilterForArea(string $area): ?string
    {
        return match ($area) {
            'galleries' => 'kind:gallery',
            'journal' => 'kind:journal',
            'custom-pages' => 'kind:custom',
            'home' => 'home',
            'cv' => 'cv',
            'site-identity' => 'site-identity',
            'unassigned' => 'unreferenced',
            default => null,
        };
    }
}
