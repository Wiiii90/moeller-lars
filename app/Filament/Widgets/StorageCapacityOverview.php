<?php

namespace App\Filament\Widgets;

use App\Domain\Media\MediaCapacityService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StorageCapacityOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Site media allowance';

    protected ?string $description = 'Authoritative originals count against the site allowance. Generated previews are measured separately and can be rebuilt.';

    protected function getStats(): array
    {
        $snapshot = app(MediaCapacityService::class)->snapshot();

        if (! $snapshot['measurement_available']) {
            return [
                Stat::make('Storage measurement', 'Unavailable')
                    ->description('New uploads are blocked when an allowance is configured but current usage cannot be verified.')
                    ->color('warning'),
                Stat::make('Configured allowance', $this->formatBytes($snapshot['quota_bytes']))
                    ->description($snapshot['configured'] ? 'Operator-controlled ceiling' : 'Not configured by the operator'),
            ];
        }

        $status = match ($snapshot['status']) {
            'full' => ['Full', 'danger'],
            'near_capacity' => ['Near capacity', 'warning'],
            'healthy' => ['Healthy', 'success'],
            default => ['Allowance not configured', 'gray'],
        };

        return [
            Stat::make('Original media', $this->formatBytes($snapshot['authoritative_bytes']))
                ->description(($snapshot['original_files'] ?? 0).' authoritative files · counts against allowance')
                ->color($status[1]),
            Stat::make('Remaining', $snapshot['configured'] ? $this->formatBytes($snapshot['remaining_bytes']) : '—')
                ->description($snapshot['configured']
                    ? $status[0].' · '.$this->formatPercent($snapshot['authoritative_ratio']).' used'
                    : 'The operator has not configured a site allowance yet')
                ->color($status[1]),
            Stat::make('Allowance', $this->formatBytes($snapshot['quota_bytes']))
                ->description('Read-only in artist admin · changed by the site operator'),
            Stat::make('Generated previews', $this->formatBytes($snapshot['generated_bytes']))
                ->description(($snapshot['generated_files'] ?? 0).' rebuildable files · does not count against allowance'),
        ];
    }

    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Not configured';
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

    private function formatPercent(?float $ratio): string
    {
        return $ratio === null ? '—' : number_format(min(1, $ratio) * 100, 0).'%';
    }
}
