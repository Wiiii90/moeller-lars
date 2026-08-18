<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricsQuery;
use App\Models\DailyMetric;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use LogicException;
use UnitEnum;

final class Analytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.analytics';

    public string $range = '30d';

    /** @var array<string, mixed> */
    public array $matomo = [];

    /** @var array<int, array<string, mixed>> */
    public array $kpis = [];

    /** @var array<string, mixed> */
    public array $trendChart = [];

    /** @var array<string, int|float|string> */
    public array $interactionSignals = [];

    /** @var array<int, array{label:string,value:string,detail:string}> */
    public array $audienceHighlights = [];

    /** @var array<int, array<string, mixed>> */
    public array $operational = [];

    /** @var array<string, int|float|string> */
    public array $operationalSummary = [];

    public function mount(): void
    {
        $this->loadRange();
    }

    public function setRange(string $range): void
    {
        if (! in_array($range, ['today', '7d', '30d', '12m'], true)) {
            return;
        }

        $this->range = $range;
        $this->loadRange();
    }

    private function loadRange(): void
    {
        $this->matomo = app(MatomoReportingClient::class)->report($this->range);
        $this->kpis = $this->buildKpis($this->matomo);
        $this->trendChart = $this->buildTrendChart($this->matomo['series'] ?? []);
        $this->interactionSignals = $this->buildInteractionSignals($this->matomo['events'] ?? []);
        $this->audienceHighlights = $this->buildAudienceHighlights($this->matomo);

        $days = match ($this->range) {
            'today' => 1,
            '7d' => 7,
            '12m' => 365,
            default => 30,
        };

        $metrics = app(OperationalMetricsQuery::class)->recent($days);
        $this->operational = $metrics
            ->map(static function (DailyMetric $metric): array {
                $metricDate = $metric->getAttribute('metric_date');
                if (! $metricDate instanceof CarbonInterface) {
                    throw new LogicException('Operational metric date is invalid.');
                }

                $sampleCount = $metric->getAttribute('sample_count');
                $value = (float) $metric->getAttribute('value');
                $metricName = (string) $metric->getAttribute('metric_name');
                $samples = $sampleCount === null ? null : (int) $sampleCount;

                return [
                    'date' => $metricDate->toDateString(),
                    'name' => $metricName,
                    'label' => self::operationalLabel($metricName),
                    'value' => $value,
                    'display_value' => self::operationalDisplayValue($metricName, $value, $samples),
                    'unit' => (string) $metric->getAttribute('unit'),
                    'sample_count' => $samples,
                ];
            })
            ->values()
            ->all();
        $this->operationalSummary = $this->buildOperationalSummary($this->operational);
    }

    /** @param array<string, mixed> $report
     * @return array<int, array<string, mixed>>
     */
    private function buildKpis(array $report): array
    {
        if (! in_array($report['status'] ?? null, ['available', 'stale'], true)) {
            return [];
        }

        $definitions = [
            'nb_visits' => 'Visits',
            'nb_uniq_visitors' => 'Unique visitors',
            'nb_actions' => 'Tracked actions',
            'nb_actions_per_visit' => 'Actions / visit',
            'avg_time_on_site' => 'Average visit',
            'bounce_rate' => 'Bounce rate',
        ];

        $metrics = $report['metrics'] ?? [];
        $comparison = $report['comparison'] ?? [];
        $kpis = [];

        foreach ($definitions as $key => $label) {
            $value = (float) ($metrics[$key] ?? 0);
            $delta = $comparison[$key] ?? null;
            $kpis[] = [
                'key' => $key,
                'label' => $label,
                'value' => $this->formatMetric($key, $value),
                'comparison' => $delta === null
                    ? 'No comparable previous period'
                    : sprintf('%+.1f%% vs previous period', (float) $delta),
                'delta' => $delta,
            ];
        }

        return $kpis;
    }

    /** @param array<int, array<string, mixed>> $series
     * @return array<string, mixed>
     */
    private function buildTrendChart(array $series): array
    {
        if ($series === []) {
            return [];
        }

        $width = 1000.0;
        $height = 260.0;
        $padding = 22.0;
        $max = max(1.0, ...array_map(
            static fn (array $point): float => max((float) ($point['visits'] ?? 0), (float) ($point['actions'] ?? 0)),
            $series,
        ));
        $count = count($series);

        $visits = [];
        $actions = [];
        foreach ($series as $index => $point) {
            $x = $count === 1
                ? $width / 2
                : $padding + (($width - 2 * $padding) * ($index / ($count - 1)));
            $visitsY = $height - $padding - (($height - 2 * $padding) * (((float) ($point['visits'] ?? 0)) / $max));
            $actionsY = $height - $padding - (($height - 2 * $padding) * (((float) ($point['actions'] ?? 0)) / $max));
            $visits[] = round($x, 2).','.round($visitsY, 2);
            $actions[] = round($x, 2).','.round($actionsY, 2);
        }

        return [
            'visits_points' => implode(' ', $visits),
            'actions_points' => implode(' ', $actions),
            'max' => $max,
            'start' => (string) ($series[0]['date'] ?? ''),
            'end' => (string) ($series[$count - 1]['date'] ?? ''),
            'points' => $count,
        ];
    }

    /** @param array<int, array<string, mixed>> $events
     * @return array<string, int|float|string>
     */
    private function buildInteractionSignals(array $events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $label = (string) ($event['label'] ?? '');
            $counts[$label] = (int) round((float) ($event['nb_events'] ?? 0));
        }

        return [
            'Artwork opens' => $counts['artwork_open'] ?? 0,
            'Artwork zooms' => $counts['artwork_zoom_used'] ?? 0,
            'Next / previous' => ($counts['artwork_next'] ?? 0) + ($counts['artwork_previous'] ?? 0),
            'Exhibition views' => $counts['exhibition_view'] ?? 0,
            'Exhibition outbound' => ($counts['exhibition_external_click'] ?? 0) + ($counts['exhibition_directions_click'] ?? 0),
            'Blog reads' => $counts['blog_view'] ?? 0,
            'Contact messages' => $counts['contact_submit_success'] ?? 0,
            'Email / Instagram clicks' => ($counts['email_click'] ?? 0) + ($counts['instagram_click'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $report
     * @return array<int, array{label:string,value:string,detail:string}>
     */
    private function buildAudienceHighlights(array $report): array
    {
        if (! in_array($report['status'] ?? null, ['available', 'stale'], true)) {
            return [];
        }

        $visits = (int) round((float) ($report['metrics']['nb_visits'] ?? 0));
        $returning = (int) round((float) ($report['returning']['nb_visits_returning'] ?? 0));
        $new = max(0, $visits - $returning);

        $topSource = $this->topRow($report['referrers'] ?? []);
        $topCountry = $this->topRow($report['countries'] ?? []);
        $topContent = $this->topRow($report['content'] ?? [], 'nb_hits');
        $topAi = $this->topRow($report['ai_assistants'] ?? []);

        return [
            [
                'label' => 'New / returning',
                'value' => number_format($new).' / '.number_format($returning),
                'detail' => 'visits in selected period',
            ],
            [
                'label' => 'Leading source',
                'value' => $topSource['label'] ?? 'No data',
                'detail' => isset($topSource['nb_visits']) ? number_format((int) $topSource['nb_visits']).' visits' : 'No referrer data',
            ],
            [
                'label' => 'Leading country',
                'value' => $topCountry['label'] ?? 'No data',
                'detail' => isset($topCountry['nb_visits']) ? number_format((int) $topCountry['nb_visits']).' visits' : 'No geography data',
            ],
            [
                'label' => 'Most viewed content',
                'value' => $topContent['label'] ?? 'No data',
                'detail' => isset($topContent['nb_hits']) ? number_format((int) $topContent['nb_hits']).' views/actions' : 'No content data',
            ],
            [
                'label' => 'AI referrals',
                'value' => $topAi['label'] ?? 'None detected',
                'detail' => isset($topAi['nb_visits']) ? number_format((int) $topAi['nb_visits']).' visits' : 'No AI-assistant referrals in range',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function topRow(array $rows, string $metric = 'nb_visits'): ?array
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static fn (array $a, array $b): int => ((float) ($b[$metric] ?? 0)) <=> ((float) ($a[$metric] ?? 0)));

        return $rows[0] ?? null;
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<string, int|float|string>
     */
    private function buildOperationalSummary(array $rows): array
    {
        $errors = 0;
        $notFound = 0;
        $bots = 0;
        $adminRequests = 0;
        $duration = 0.0;
        $durationSamples = 0;
        $adminDuration = 0.0;
        $adminDurationSamples = 0;

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $value = (float) $row['value'];
            $samples = (int) ($row['sample_count'] ?? 0);

            if (str_starts_with($name, 'error:')) {
                $errors += (int) round($value);
            }
            if ($name === 'error:http_404') {
                $notFound += (int) round($value);
            }
            if ($name === 'bot:request') {
                $bots += (int) round($value);
            }
            if ($name === 'operation:admin_request') {
                $adminRequests += (int) round($value);
            }
            if ($name === 'performance:request_duration_ms') {
                $duration += $value;
                $durationSamples += $samples;
            }
            if ($name === 'performance:admin_request_duration_ms') {
                $adminDuration += $value;
                $adminDurationSamples += $samples;
            }
        }

        return [
            'Errors' => $errors,
            '404 responses' => $notFound,
            'Bot requests' => $bots,
            'Average response' => $durationSamples > 0 ? round($duration / $durationSamples, 1).' ms' : 'No data',
            'Admin requests' => $adminRequests,
            'Average admin response' => $adminDurationSamples > 0 ? round($adminDuration / $adminDurationSamples, 1).' ms' : 'No data',
        ];
    }

    private function formatMetric(string $key, float $value): string
    {
        return match ($key) {
            'nb_visits', 'nb_uniq_visitors', 'nb_actions' => number_format((int) round($value)),
            'nb_actions_per_visit' => number_format($value, 1),
            'avg_time_on_site' => $this->formatDuration((int) round($value)),
            'bounce_rate' => number_format($value, 1).'%',
            default => (string) $value,
        };
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $minutes.'m '.str_pad((string) $remaining, 2, '0', STR_PAD_LEFT).'s';
    }

    private static function operationalLabel(string $metricName): string
    {
        return match ($metricName) {
            'error:http_404' => 'HTTP 404 responses',
            'error:http_5xx' => 'HTTP 5xx responses',
            'error:request_exception' => 'Request exceptions',
            'bot:request' => 'Bot requests',
            'operation:admin_request' => 'Admin requests',
            'performance:request_duration_ms' => 'Average request duration',
            'performance:admin_request_duration_ms' => 'Average admin request duration',
            default => str_replace([':', '_'], [' · ', ' '], $metricName),
        };
    }

    private static function operationalDisplayValue(string $metricName, float $value, ?int $samples): string
    {
        if (str_starts_with($metricName, 'performance:') && $samples !== null && $samples > 0) {
            return number_format($value / $samples, 1).' ms avg';
        }

        return number_format($value, str_contains((string) $value, '.') ? 1 : 0);
    }
}
