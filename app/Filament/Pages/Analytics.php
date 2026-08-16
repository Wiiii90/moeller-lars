<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricsQuery;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use LogicException;

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
            ->map(static function (DailyMetric $metric): array {
                $metricDate = $metric->getAttribute('metric_date');
                if (! $metricDate instanceof CarbonInterface) {
                    throw new LogicException('Operational metric date is invalid.');
                }

                $sampleCount = $metric->getAttribute('sample_count');

                return [
                    'date' => $metricDate->toDateString(),
                    'name' => (string) $metric->getAttribute('metric_name'),
                    'value' => (string) $metric->getAttribute('value'),
                    'unit' => (string) $metric->getAttribute('unit'),
                    'sample_count' => $sampleCount === null ? null : (int) $sampleCount,
                ];
            })
            ->all();
    }
}
