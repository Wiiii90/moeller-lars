<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricsQuery;
use Filament\Pages\Page;

final class Analytics extends Page
{
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?int $navigationSort = 40;
    protected string $view = 'filament.pages.analytics';

    /** @var array<string, mixed> */
    public array $matomo = [];

    /** @var array<int, array<string, mixed>> */
    public array $operational = [];

    public function mount(): void
    {
        $this->matomo = app(MatomoReportingClient::class)->summary();
        $this->operational = app(OperationalMetricsQuery::class)
            ->recent()
            ->map(static fn ($metric): array => [
                'date' => $metric->metric_date->toDateString(),
                'name' => (string) $metric->metric_name,
                'value' => (string) $metric->value,
                'unit' => (string) $metric->unit,
                'sample_count' => $metric->sample_count === null ? null : (int) $metric->sample_count,
            ])
            ->all();
    }
}
