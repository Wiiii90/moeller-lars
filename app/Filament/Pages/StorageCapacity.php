<?php

namespace App\Filament\Pages;

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageBreakdown;
use App\Domain\Media\MediaStorageUnits;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    public function refreshCapacity(): void
    {
        app(MediaCapacityService::class)->forgetCachedSnapshot();
        $this->loadCapacity();
        Notification::make()->title('Storage measurement refreshed')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh measurement')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->refreshCapacity();
                }),
        ];
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
                'invalid_configuration' => 'Configuration invalid',
                'measurement_unavailable' => 'Measurement unavailable',
                default => 'Not configured',
            },
            'authoritative_bytes' => $snapshot['authoritative_bytes'],
            'authoritative_display' => MediaStorageUnits::formatBytes($snapshot['authoritative_bytes']),
            'rebuildable_bytes' => $snapshot['rebuildable_bytes'],
            'rebuildable_display' => MediaStorageUnits::formatBytes($snapshot['rebuildable_bytes']),
            'allowance_bytes' => $snapshot['allowance_bytes'],
            'allowance_display' => is_int($snapshot['allowance_bytes']) ? MediaStorageUnits::formatBytes($snapshot['allowance_bytes']) : 'Not configured',
            'remaining_bytes' => $snapshot['remaining_bytes'],
            'remaining_display' => is_int($snapshot['remaining_bytes']) ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes']) : '—',
            'percentage' => $ratio === null ? null : round($ratio * 100, 1),
            'guidance' => match ($snapshot['status']) {
                'full' => 'Uploads are paused until authoritative originals are removed or the operator increases the allowance. Existing public media stays readable.',
                'near_capacity' => 'Review the largest originals and unused media before the allowance is exhausted.',
                'healthy' => 'There is sufficient headroom for normal media work.',
                'invalid_configuration' => 'The configured allowance is invalid. Uploads fail closed until the operator corrects it.',
                'measurement_unavailable' => 'Authoritative media usage could not be measured. Uploads fail closed until measurement is available again.',
                default => 'The operator has not configured an artist media allowance yet.',
            },
        ];

        $this->breakdown = array_map(static fn (array $row): array => [
            'label' => $row['label'],
            'bytes' => $row['bytes'],
            'display' => MediaStorageUnits::formatBytes($row['bytes']),
        ], MediaStorageBreakdown::fromMeasuredOriginals($snapshot['measured_originals'])->rows);

        $this->heavyConsumers = array_map(static fn (array $row): array => [
            ...$row,
            'display' => MediaStorageUnits::formatBytes($row['bytes']),
        ], MediaStorageBreakdown::fromMeasuredOriginals($snapshot['measured_originals'])->largestOriginals);

        $this->availableAssets = MediaAsset::query()->where('state', 'available')->count();
        $this->unusedAssets = MediaAsset::query()->where('state', 'available')->whereDoesntHave('artworkMedia')->whereDoesntHave('exhibitionMedia')->whereDoesntHave('cvEntries')->whereDoesntHave('blogPosts')->whereDoesntHave('faviconSettings')->count();
    }
}
