<?php

namespace App\Filament\Pages;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageBreakdown;
use App\Domain\Media\MediaStorageUnits;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class StorageCapacity extends Page
{
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
    public array $heavyConsumers = [];

    public int $availableAssets = 0;

    public int $unusedAssets = 0;

    public function mount(): void
    {
        $this->loadCapacity();
    }

    private function loadCapacity(): void
    {
        $capacityService = app(MediaCapacityService::class);
        $snapshot = $capacityService->cachedSnapshot();
        $ratio = is_float($snapshot['authoritative_ratio']) ? $snapshot['authoritative_ratio'] : null;
        $configurationValid = $snapshot['configuration_valid'];

        $this->capacity = [
            'configured' => $snapshot['configured'],
            'configuration_valid' => $configurationValid,
            'measurement_available' => $snapshot['measurement_available'],
            'status' => $snapshot['status'],
            'status_label' => match ($snapshot['status']) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Healthy',
                'unavailable' => $configurationValid ? 'Measurement unavailable' : 'Allowance unavailable',
                default => 'Allowance not configured',
            },
            'action' => match ($snapshot['status']) {
                'full' => 'New media uploads are blocked until unused originals are removed or the operator raises the allowance.',
                'near_capacity' => 'Capacity is approaching the configured ceiling. Review unused media before the next larger upload.',
                'unavailable' => $configurationValid
                    ? 'Existing media remains readable. New uploads stay blocked while authoritative usage cannot be verified.'
                    : 'The operator storage allowance is invalid. Existing media remains readable; new uploads stay blocked until the runtime configuration is corrected.',
                default => null,
            },
            'percent' => $ratio === null ? 0 : (int) round(min(1, $ratio) * 100),
            'authoritative' => MediaStorageUnits::formatBytes($snapshot['authoritative_bytes']),
            'generated' => MediaStorageUnits::formatBytes($snapshot['generated_bytes']),
            'remaining' => $snapshot['configured'] ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes']) : '—',
            'allowance' => MediaStorageUnits::formatBytes($snapshot['quota_bytes']),
            'original_files' => $snapshot['original_files'] ?? 0,
            'generated_files' => $snapshot['generated_files'] ?? 0,
            'warning_threshold' => $capacityService->warningThresholdPercent().'%',
            'unit_note' => 'Decimal units · 1 GB = 1,000,000,000 bytes',
        ];

        $analysis = app(MediaStorageBreakdown::class)->analyze($snapshot['authoritative_file_bytes'] ?? []);
        $this->breakdown = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);

            return $row;
        }, $analysis['breakdown']);
        $this->heavyConsumers = array_map(function (array $row): array {
            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);

            return $row;
        }, $analysis['heavy_consumers']);

        $this->availableAssets = MediaAsset::query()->where('state', 'available')->count();
        $this->unusedAssets = MediaAsset::query()
            ->where('state', 'available')
            ->whereDoesntHave('artworks')
            ->whereDoesntHave('exhibitions')
            ->whereDoesntHave('cvEntries')
            ->whereDoesntHave('blogPosts')
            ->count();
    }
}
