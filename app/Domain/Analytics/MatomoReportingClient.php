<?php

namespace App\Domain\Analytics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;
use Throwable;

final class MatomoReportingClient
{
    private const METRICS = [
        'nb_visits',
        'nb_uniq_visitors',
        'nb_actions',
        'nb_actions_per_visit',
        'avg_time_on_site',
        'bounce_rate',
    ];

    private const PRESETS = ['today', '7d', '30d', '12m'];

    public function __construct(private readonly MatomoConfiguration $configuration) {}

    /** @return array<string, mixed> */
    public function report(string $preset = '30d'): array
    {
        if (! in_array($preset, self::PRESETS, true)) {
            throw new \InvalidArgumentException('Unsupported analytics range.');
        }

        if (! (bool) config('analytics.matomo.enabled')) {
            return ['status' => 'disabled', 'message' => 'Matomo tracking is disabled.'];
        }

        try {
            $siteId = $this->configuration->siteId();
            $range = $this->range($preset);
            $freshKey = "analytics:matomo:v2:site:{$siteId}:{$preset}:fresh";
            $staleKey = "analytics:matomo:v2:site:{$siteId}:{$preset}:stale";

            $cached = Cache::get($freshKey);
            if (is_array($cached)) {
                $cached['cache'] = 'fresh';

                return $cached;
            }

            try {
                $report = $this->fetchReport($siteId, $range);
                $report['cache'] = 'live';
                Cache::put($freshKey, $report, $this->configuration->reportCacheSeconds());
                Cache::put($staleKey, $report, $this->configuration->reportStaleSeconds());

                return $report;
            } catch (Throwable $exception) {
                $stale = Cache::get($staleKey);
                if (is_array($stale)) {
                    $stale['status'] = 'stale';
                    $stale['cache'] = 'stale';
                    $stale['message'] = 'Live Matomo reporting is unavailable. Showing cached aggregate data.';

                    return $stale;
                }

                throw $exception;
            }
        } catch (ConnectionException) {
            return ['status' => 'unavailable', 'message' => 'Matomo Reporting API is unreachable.'];
        } catch (LogicException|RuntimeException $exception) {
            return ['status' => 'unavailable', 'message' => $exception->getMessage()];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'message' => 'Matomo Reporting API failed unexpectedly.'];
        }
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $report = $this->report('30d');

        if (! in_array($report['status'] ?? null, ['available', 'stale'], true)) {
            return $report;
        }

        return [
            'status' => $report['status'],
            'metrics' => $report['metrics'],
            'message' => $report['message'] ?? null,
        ];
    }

    /** @param array{preset:string,label:string,start:string,end:string,previous_start:string,previous_end:string} $range
     *  @return array<string, mixed>
     */
    private function fetchReport(int $siteId, array $range): array
    {
        $date = $range['start'].','.$range['end'];
        $previousDate = $range['previous_start'].','.$range['previous_end'];

        $requests = [
            $this->nestedRequest('VisitsSummary.get', $siteId, 'range', $date),
            $this->nestedRequest('VisitsSummary.get', $siteId, 'range', $previousDate),
            $this->nestedRequest('VisitsSummary.get', $siteId, 'day', $date),
            $this->nestedRequest('Actions.getPageUrls', $siteId, 'range', $date, [
                'flat' => 1,
                'filter_limit' => 10,
                'filter_sort_column' => 'nb_hits',
                'filter_sort_order' => 'desc',
            ]),
            $this->nestedRequest('Events.getAction', $siteId, 'range', $date, [
                'filter_limit' => 30,
                'filter_sort_column' => 'nb_events',
                'filter_sort_order' => 'desc',
            ]),
            $this->nestedRequest('Referrers.getReferrerType', $siteId, 'range', $date, ['filter_limit' => 10]),
            $this->nestedRequest('UserCountry.getCountry', $siteId, 'range', $date, ['filter_limit' => 10]),
            $this->nestedRequest('DevicesDetection.getType', $siteId, 'range', $date, ['filter_limit' => 10]),
            $this->nestedRequest('DevicesDetection.getBrowsers', $siteId, 'range', $date, ['filter_limit' => 10]),
            $this->nestedRequest('DevicesDetection.getOsFamilies', $siteId, 'range', $date, ['filter_limit' => 10]),
        ];

        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->configuration->timeoutSeconds())
            ->post($this->configuration->baseUrl().'/index.php', [
                'module' => 'API',
                'method' => 'API.getBulkRequest',
                'format' => 'JSON',
                'token_auth' => $this->configuration->apiToken(),
                'urls' => $requests,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Matomo Reporting API returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new RuntimeException('Matomo Reporting API returned malformed bulk JSON.');
        }

        $summaryPayload = $this->reportPayload($payload, 0);
        if ($summaryPayload === null) {
            throw new RuntimeException('Matomo Reporting API omitted the required visit summary.');
        }

        $metrics = $this->normalizeSummary($summaryPayload);
        $warnings = [];

        $previousPayload = $this->reportPayload($payload, 1);
        $previousMetrics = $previousPayload === null ? null : $this->normalizeSummary($previousPayload, false);
        if ($previousMetrics === null) {
            $warnings[] = 'Previous-period comparison is unavailable.';
        }

        $seriesPayload = $this->reportPayload($payload, 2);
        $series = $seriesPayload === null ? [] : $this->normalizeSeries($seriesPayload);
        if ($series === []) {
            $warnings[] = 'Traffic time-series data is unavailable.';
        }

        $sections = [
            'content' => $this->normalizeRows($this->reportPayload($payload, 3), ['nb_visits', 'nb_hits', 'nb_actions']),
            'events' => $this->normalizeRows($this->reportPayload($payload, 4), ['nb_events', 'nb_visits', 'nb_uniq_visitors']),
            'referrers' => $this->normalizeRows($this->reportPayload($payload, 5), ['nb_visits', 'nb_uniq_visitors']),
            'countries' => $this->normalizeRows($this->reportPayload($payload, 6), ['nb_visits', 'nb_uniq_visitors']),
            'devices' => $this->normalizeRows($this->reportPayload($payload, 7), ['nb_visits', 'nb_uniq_visitors']),
            'browsers' => $this->normalizeRows($this->reportPayload($payload, 8), ['nb_visits', 'nb_uniq_visitors']),
            'operating_systems' => $this->normalizeRows($this->reportPayload($payload, 9), ['nb_visits', 'nb_uniq_visitors']),
        ];

        foreach ($sections as $name => $rows) {
            if ($this->reportPayload($payload, array_search($name, array_keys($sections), true) + 3) === null) {
                $warnings[] = ucfirst(str_replace('_', ' ', $name)).' report is unavailable.';
            }
        }

        return [
            'status' => 'available',
            'generated_at' => now()->toIso8601String(),
            'range' => $range,
            'metrics' => $metrics,
            'comparison' => $this->comparison($metrics, $previousMetrics),
            'series' => $series,
            ...$sections,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param array<string, scalar> $extra */
    private function nestedRequest(string $method, int $siteId, string $period, string $date, array $extra = []): string
    {
        return http_build_query([
            'method' => $method,
            'idSite' => $siteId,
            'period' => $period,
            'date' => $date,
            ...$extra,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<int, mixed> $payload */
    private function reportPayload(array $payload, int $index): ?array
    {
        $report = $payload[$index] ?? null;
        if (! is_array($report)) {
            return null;
        }
        if (($report['result'] ?? null) === 'error') {
            return null;
        }

        return $report;
    }

    /** @return array<string, float>|null */
    private function normalizeSummary(array $payload, bool $required = true): ?array
    {
        $metrics = [];
        foreach (self::METRICS as $metric) {
            if (! array_key_exists($metric, $payload)) {
                if ($required) {
                    throw new RuntimeException('Matomo Reporting API omitted required aggregate metric '.$metric.'.');
                }

                return null;
            }

            $value = $this->numericValue($payload[$metric]);
            if ($value === null) {
                if ($required) {
                    throw new RuntimeException('Matomo Reporting API returned an invalid aggregate metric '.$metric.'.');
                }

                return null;
            }
            $metrics[$metric] = $value;
        }

        return $metrics;
    }

    /** @return array<int, array{date:string,visits:float,actions:float}> */
    private function normalizeSeries(array $payload): array
    {
        $series = [];
        foreach ($payload as $key => $row) {
            if (! is_array($row) || ($row['result'] ?? null) === 'error') {
                continue;
            }

            $date = is_string($key) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) === 1
                ? $key
                : (is_string($row['date'] ?? null) ? $row['date'] : null);
            if ($date === null) {
                continue;
            }

            $series[] = [
                'date' => $date,
                'visits' => $this->numericValue($row['nb_visits'] ?? null) ?? 0.0,
                'actions' => $this->numericValue($row['nb_actions'] ?? null) ?? 0.0,
            ];
        }

        usort($series, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $series;
    }

    /** @param list<string> $metricNames
     *  @return array<int, array<string, float|string>>
     */
    private function normalizeRows(?array $payload, array $metricNames): array
    {
        if ($payload === null) {
            return [];
        }

        $rows = [];
        foreach ($payload as $row) {
            if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                continue;
            }

            $normalized = ['label' => $this->sanitizeLabel($row['label'])];
            foreach ($metricNames as $metric) {
                $normalized[$metric] = $this->numericValue($row[$metric] ?? null) ?? 0.0;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /** @param array<string, float>|null $previous
     *  @return array<string, float|null>
     */
    private function comparison(array $current, ?array $previous): array
    {
        $comparison = [];
        foreach (self::METRICS as $metric) {
            if ($previous === null) {
                $comparison[$metric] = null;
                continue;
            }

            $previousValue = $previous[$metric] ?? 0.0;
            if ($previousValue == 0.0) {
                $comparison[$metric] = ($current[$metric] ?? 0.0) == 0.0 ? 0.0 : null;
                continue;
            }

            $comparison[$metric] = (($current[$metric] - $previousValue) / abs($previousValue)) * 100;
        }

        return $comparison;
    }

    /** @return array{preset:string,label:string,start:string,end:string,previous_start:string,previous_end:string} */
    private function range(string $preset): array
    {
        $end = now()->startOfDay();
        $start = match ($preset) {
            'today' => $end->copy(),
            '7d' => $end->copy()->subDays(6),
            '30d' => $end->copy()->subDays(29),
            '12m' => $end->copy()->subYear()->addDay(),
            default => throw new \InvalidArgumentException('Unsupported analytics range.'),
        };

        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'preset' => $preset,
            'label' => match ($preset) {
                'today' => 'Today',
                '7d' => 'Last 7 days',
                '30d' => 'Last 30 days',
                '12m' => 'Last 12 months',
                default => throw new \InvalidArgumentException('Unsupported analytics range.'),
            },
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'previous_start' => $previousStart->toDateString(),
            'previous_end' => $previousEnd->toDateString(),
        ];
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(['%', ','], ['', '.'], $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function sanitizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Unknown';
        }

        if (filter_var($label, FILTER_VALIDATE_URL) !== false) {
            $path = parse_url($label, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? $path : '/';
        }

        return preg_replace('/[?#].*$/', '', $label) ?: $label;
    }
}
