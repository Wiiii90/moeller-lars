<?php

namespace App\Filament\Pages;

use App\Domain\Media\MediaCapacityService;
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

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?string $navigationLabel = 'Storage';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.storage-capacity';

    /** @var array<string, mixed> */
    public array $capacity = [];

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
        $snapshot = app(MediaCapacityService::class)->cachedSnapshot();
        $ratio = is_float($snapshot['authoritative_ratio']) ? $snapshot['authoritative_ratio'] : null;

        $this->capacity = [
            'configured' => $snapshot['configured'],
            'measurement_available' => $snapshot['measurement_available'],
            'status' => $snapshot['status'],
            'status_label' => match ($snapshot['status']) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Healthy',
                'unavailable' => 'Measurement unavailable',
                default => 'Allowance not configured',
            },
            'percent' => $ratio === null ? 0 : (int) round(min(1, $ratio) * 100),
            'authoritative' => $this->formatBytes($snapshot['authoritative_bytes']),
            'generated' => $this->formatBytes($snapshot['generated_bytes']),
            'remaining' => $snapshot['configured'] ? $this->formatBytes($snapshot['remaining_bytes']) : '—',
            'allowance' => $this->formatBytes($snapshot['quota_bytes']),
            'original_files' => $snapshot['original_files'] ?? 0,
            'generated_files' => $snapshot['generated_files'] ?? 0,
        ];

        $this->availableAssets = MediaAsset::query()->where('state', 'available')->count();
        $this->unusedAssets = MediaAsset::query()
            ->where('state', 'available')
            ->whereDoesntHave('artworks')
            ->whereDoesntHave('exhibitions')
            ->whereDoesntHave('cvEntries')
            ->whereDoesntHave('blogPosts')
            ->count();
    }

    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
