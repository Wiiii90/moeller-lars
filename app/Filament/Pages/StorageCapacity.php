<?php

namespace App\Filament\Pages;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageBreakdown;
use App\Domain\Media\MediaStorageUnits;
use App\Filament\Support\MediaStorageReferenceCatalog;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use UnitEnum;

final class StorageCapacity extends Page
{
    /** @var list<int> */
    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Storage';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.storage-capacity';

    /** @var array<string, mixed> */
    public array $capacity = [];

    /** @var list<array<string, mixed>> */
    public array $breakdown = [];

    /** @var list<array<string, mixed>> */
    public array $fileRows = [];

    /** @var list<array<string, mixed>> */
    public array $files = [];

    /** @var list<array<string, mixed>> */
    public array $referenceOptions = [];

    /** @var array<string, mixed> */
    public array $attention = [];

    public int $availableAssets = 0;

    public int $unusedAssets = 0;

    public string $search = '';

    public string $areaFilter = 'all';

    public string $referenceState = 'all';

    public string $referenceFilter = 'all';

    public int $page = 1;

    public int $pageSize = self::DEFAULT_PAGE_SIZE;

    public int $total = 0;

    public int $pages = 1;

    public function mount(): void
    {
        $this->loadCapacity();
    }

    public function updatedSearch(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedAreaFilter(): void
    {
        $this->referenceFilter = 'all';
        $this->refreshFromFirstPage();
    }

    public function updatedReferenceState(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedReferenceFilter(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedPageSize(mixed $value): void
    {
        $this->pageSize = $this->normalizePageSize($value);
        $this->refreshFromFirstPage();
    }

    public function resetTableFilters(): void
    {
        $this->search = '';
        $this->areaFilter = 'all';
        $this->referenceState = 'all';
        $this->referenceFilter = 'all';
        $this->refreshFromFirstPage();
    }

    public function selectArea(string $area): void
    {
        $valid = collect($this->breakdown)->pluck('key')->contains($area);
        if (! $valid) {
            return;
        }

        $this->areaFilter = $area;
        $this->referenceFilter = 'all';
        $this->page = 1;
        $this->loadTable();
    }

    public function selectReferenceState(string $state): void
    {
        if (! in_array($state, ['all', 'referenced', 'unreferenced', 'uncatalogued'], true)) {
            return;
        }

        $this->referenceState = $state;
        $this->areaFilter = 'all';
        $this->referenceFilter = 'all';
        $this->page = 1;
        $this->loadTable();
    }

    public function selectReference(string $reference): void
    {
        $target = collect($this->referenceOptions)->first(
            static fn (array $row): bool => ($row['key'] ?? null) === $reference,
        );
        if (! is_array($target)) {
            return;
        }

        $this->referenceFilter = $reference;
        $this->referenceState = 'referenced';
        $this->areaFilter = (string) ($target['area'] ?? 'all');
        $this->page = 1;
        $this->loadTable();
    }

    public function previousPage(): void
    {
        if ($this->page <= 1) {
            return;
        }

        $this->page--;
        $this->loadTable();
    }

    public function nextPage(): void
    {
        if ($this->page >= $this->pages) {
            return;
        }

        $this->page++;
        $this->loadTable();
    }

    private function refreshFromFirstPage(): void
    {
        $this->page = 1;
        $this->loadTable();
    }

    private function loadCapacity(): void
    {
        $capacityService = app(MediaCapacityService::class);
        $snapshot = $capacityService->cachedSnapshot();
        $ratio = is_numeric($snapshot['authoritative_ratio'] ?? null)
            ? (float) $snapshot['authoritative_ratio']
            : null;
        $configurationValid = (bool) ($snapshot['configuration_valid'] ?? false);
        $configured = (bool) ($snapshot['configured'] ?? false);
        $measurementAvailable = (bool) ($snapshot['measurement_available'] ?? false);
        $status = (string) ($snapshot['status'] ?? 'unavailable');

        $this->capacity = [
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
                'healthy' => 'Healthy',
                'unavailable' => $configurationValid ? 'Measurement unavailable' : 'Allowance unavailable',
                default => 'Allowance not configured',
            },
            'action' => match ($status) {
                'full' => 'New media uploads are blocked until unused originals are removed or the operator raises the allowance.',
                'near_capacity' => 'Capacity is approaching the configured ceiling. Review unused originals before the next larger upload.',
                'unavailable' => $configurationValid
                    ? 'Existing media remains readable. New uploads stay blocked while authoritative usage cannot be verified.'
                    : 'The operator storage allowance is invalid. Existing media remains readable; new uploads stay blocked until the runtime configuration is corrected.',
                'unconfigured' => 'Authoritative storage is measured, but no operator allowance is configured.',
                default => null,
            },
            'percent' => $configured && $measurementAvailable && $ratio !== null
                ? (int) round(min(1, max(0, $ratio)) * 100)
                : null,
            'authoritative' => MediaStorageUnits::formatBytes($snapshot['authoritative_bytes'] ?? null),
            'generated' => MediaStorageUnits::formatBytes($snapshot['generated_bytes'] ?? null),
            'remaining' => $configured && $measurementAvailable
                ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes'] ?? null)
                : '—',
            'allowance' => $configured && $configurationValid
                ? MediaStorageUnits::formatBytes($snapshot['quota_bytes'] ?? null)
                : '—',
            'original_files' => (int) ($snapshot['original_files'] ?? 0),
            'generated_files' => (int) ($snapshot['generated_files'] ?? 0),
            'warning_threshold' => $capacityService->warningThresholdPercent().'%',
            'unit_note' => 'Decimal units · 1 GB = 1,000,000,000 bytes',
        ];

        $referenceCatalog = app(MediaStorageReferenceCatalog::class);
        $metrics = $referenceCatalog->libraryMetrics();
        $this->availableAssets = $metrics['files'];
        $this->unusedAssets = $metrics['unreferenced'];

        $authoritativeFiles = is_array($snapshot['authoritative_file_bytes'] ?? null)
            ? $snapshot['authoritative_file_bytes']
            : [];

        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = new EloquentCollection();
        if ($authoritativeFiles !== []) {
            $assetQuery = MediaAsset::query()->whereIn('storage_key', array_keys($authoritativeFiles));
            $referenceCatalog->eagerLoad($assetQuery);
            $assets = $assetQuery->get();
        }

        $referencesByAssetId = $referenceCatalog->referencesByAssetId($assets);
        $referencedIds = $referenceCatalog->referencedIds(
            $assets->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        $analysis = app(MediaStorageBreakdown::class)->analyze(
            $authoritativeFiles,
            $assets,
            $referencesByAssetId,
            $referencedIds,
        );

        $this->breakdown = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);

            return $row;
        }, $analysis['breakdown']);

        $this->fileRows = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);
            $row['display_share'] = number_format((float) $row['share'], ((float) $row['share']) < 1 ? 2 : 1).'%';

            return $row;
        }, $analysis['file_rows']);

        $this->referenceOptions = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);

            return $row;
        }, $analysis['target_breakdown']);

        $attention = $analysis['attention'];
        foreach (['largest_file', 'largest_unreferenced'] as $key) {
            if (is_array($attention[$key] ?? null)) {
                $attention[$key]['display_bytes'] = MediaStorageUnits::formatBytes((int) $attention[$key]['bytes']);
            }
        }
        foreach (['largest_area', 'largest_gallery'] as $key) {
            if (is_array($attention[$key] ?? null)) {
                $attention[$key]['display_bytes'] = MediaStorageUnits::formatBytes((int) $attention[$key]['bytes']);
            }
        }
        $attention['unreferenced_display_bytes'] = MediaStorageUnits::formatBytes((int) ($attention['unreferenced_bytes'] ?? 0));
        $this->attention = $attention;

        $this->pageSize = $this->normalizePageSize($this->pageSize);
        $this->loadTable();
    }

    private function loadTable(): void
    {
        $rows = $this->filteredFileRows();
        $this->total = count($rows);
        $this->pages = max(1, (int) ceil($this->total / $this->pageSize));
        $this->page = min(max(1, $this->page), $this->pages);
        $this->files = array_slice($rows, ($this->page - 1) * $this->pageSize, $this->pageSize);
    }

    /** @return list<array<string, mixed>> */
    private function filteredFileRows(): array
    {
        $search = Str::lower(trim($this->search));

        return array_values(array_filter($this->fileRows, function (array $row) use ($search): bool {
            if ($this->referenceState !== 'all' && ($row['state'] ?? null) !== $this->referenceState) {
                return false;
            }

            if ($this->areaFilter !== 'all') {
                $areaKeys = is_array($row['area_keys'] ?? null) ? $row['area_keys'] : [];
                $bucket = (string) ($row['bucket_key'] ?? '');
                if (! in_array($this->areaFilter, $areaKeys, true) && $bucket !== $this->areaFilter) {
                    return false;
                }
            }

            $references = is_array($row['references'] ?? null) ? $row['references'] : [];
            if ($this->referenceFilter !== 'all' && ! collect($references)->contains(
                fn (array $reference): bool => ($reference['target_key'] ?? null) === $this->referenceFilter,
            )) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $referenceText = collect($references)->flatMap(static fn (array $reference): array => [
                (string) ($reference['area_label'] ?? ''),
                (string) ($reference['target_label'] ?? ''),
                (string) ($reference['type'] ?? ''),
                (string) ($reference['label'] ?? ''),
            ])->implode(' ');
            $text = implode(' ', [
                (string) ($row['filename'] ?? ''),
                (string) ($row['type_label'] ?? ''),
                implode(' ', is_array($row['use_labels'] ?? null) ? $row['use_labels'] : []),
                (string) ($row['state_label'] ?? ''),
                $referenceText,
            ]);

            return Str::contains(Str::lower($text), $search);
        }));
    }

    private function normalizePageSize(mixed $value): int
    {
        $pageSize = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE;
    }
}
