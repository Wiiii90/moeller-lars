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

        if (! (bool) config('analytics.matomo.reporting_enabled')) {
            return ['status' => 'disabled', 'message' => 'Matomo reporting is disabled.'];
        }

        try {
            $siteId = $this->configuration->siteId();
            $range = $this->range($preset);
            $freshKey = "analytics:matomo:v4:site:{$siteId}:{$preset}:fresh";
            $staleKey = "analytics:matomo:v4:site:{$siteId}:{$preset}:stale";

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
     * @return array<string, mixed>
     */
    private function fetchReport(int $siteId, array $range): array
    {
        $date = $range['start'].','.$range['end'];
        $previousDate = $range['previous_start'].','.$range['previous_end'];
        $summaryPeriod = $range['preset'] === 'today' ? 'day' : 'range';
        $summaryDate = $range['preset'] === 'today' ? $range['end'] : $date;
        $previousSummaryDate = $range['preset'] === 'today' ? $range['previous_end'] : $previousDate;

        $definitions = [
            'summary' => $this->nestedRequest('VisitsSummary.get', $siteId, $summaryPeriod, $summaryDate),
            'previous_summary' => $this->nestedRequest('VisitsSummary.get', $siteId, $summaryPeriod, $previousSummaryDate),
            'series' => $this->nestedRequest('VisitsSummary.get', $siteId, 'day', $date),
            'content' => $this->nestedRequest('Actions.getPageUrls', $siteId, 'range', $date, $this->topRows(15, ['flat' => 1, 'filter_sort_column' => 'nb_hits'])),
            'entry_pages' => $this->nestedRequest('Actions.getEntryPageUrls', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1, 'filter_sort_column' => 'nb_entrances'])),
            'exit_pages' => $this->nestedRequest('Actions.getExitPageUrls', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1, 'filter_sort_column' => 'nb_exits'])),
            'downloads' => $this->nestedRequest('Actions.getDownloads', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1, 'filter_sort_column' => 'nb_hits'])),
            'outlinks' => $this->nestedRequest('Actions.getOutlinks', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1, 'filter_sort_column' => 'nb_hits'])),
            'site_searches' => $this->nestedRequest('Actions.getSiteSearchKeywords', $siteId, 'range', $date, $this->topRows(12)),
            'site_search_no_results' => $this->nestedRequest('Actions.getSiteSearchNoResultKeywords', $siteId, 'range', $date, $this->topRows(8)),
            'events' => $this->nestedRequest('Events.getAction', $siteId, 'range', $date, $this->topRows(30, ['filter_sort_column' => 'nb_events'])),
            'event_categories' => $this->nestedRequest('Events.getCategory', $siteId, 'range', $date, $this->topRows(20, ['filter_sort_column' => 'nb_events'])),
            'event_names' => $this->nestedRequest('Events.getName', $siteId, 'range', $date, $this->topRows(30, ['filter_sort_column' => 'nb_events'])),
            'referrers' => $this->nestedRequest('Referrers.getReferrerType', $siteId, 'range', $date, $this->topRows(10)),
            'referrer_websites' => $this->nestedRequest('Referrers.getWebsites', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1])),
            'socials' => $this->nestedRequest('Referrers.getSocials', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1])),
            'search_engines' => $this->nestedRequest('Referrers.getSearchEngines', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1])),
            'campaigns' => $this->nestedRequest('Referrers.getCampaigns', $siteId, 'range', $date, $this->topRows(12)),
            'ai_assistants' => $this->nestedRequest('Referrers.getAIAssistants', $siteId, 'range', $date, $this->topRows(12, ['flat' => 1])),
            'continents' => $this->nestedRequest('UserCountry.getContinent', $siteId, 'range', $date, $this->topRows(10)),
            'countries' => $this->nestedRequest('UserCountry.getCountry', $siteId, 'range', $date, $this->topRows(15)),
            'devices' => $this->nestedRequest('DevicesDetection.getType', $siteId, 'range', $date, $this->topRows(10)),
            'browsers' => $this->nestedRequest('DevicesDetection.getBrowsers', $siteId, 'range', $date, $this->topRows(12)),
            'operating_systems' => $this->nestedRequest('DevicesDetection.getOsFamilies', $siteId, 'range', $date, $this->topRows(12)),
            'visit_duration' => $this->nestedRequest('VisitorInterest.getNumberOfVisitsPerVisitDuration', $siteId, 'range', $date),
            'pages_per_visit' => $this->nestedRequest('VisitorInterest.getNumberOfVisitsPerPage', $siteId, 'range', $date),
            'local_time' => $this->nestedRequest('VisitTime.getVisitInformationPerLocalTime', $siteId, 'range', $date),
            'day_of_week' => $this->nestedRequest('VisitTime.getByDayOfWeek', $siteId, 'range', $date),
            'returning' => $this->nestedRequest('VisitFrequency.get', $siteId, 'range', $date),
        ];

        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->configuration->timeoutSeconds())
            ->post($this->configuration->baseUrl().'/index.php', [
                'module' => 'API',
                'method' => 'API.getBulkRequest',
                'format' => 'JSON',
                'token_auth' => $this->configuration->apiToken(),
                'urls' => array_values($definitions),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Matomo Reporting API returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new RuntimeException('Matomo Reporting API returned malformed bulk JSON.');
        }

        $reports = [];
        foreach (array_keys($definitions) as $index => $name) {
            $reports[$name] = $this->reportPayload($payload, $index);
        }

        if ($reports['summary'] === null) {
            throw new RuntimeException('Matomo Reporting API omitted the required visit summary.');
        }

        $metrics = $this->normalizeSummary($reports['summary']);
        $previousMetrics = $reports['previous_summary'] === null
            ? null
            : $this->normalizeSummary($reports['previous_summary'], false);
        $series = $reports['series'] === null ? [] : $this->normalizeSeries($reports['series']);

        $rowMetrics = ['nb_visits', 'nb_uniq_visitors', 'nb_actions', 'nb_hits', 'nb_events', 'nb_entrances', 'nb_exits', 'bounce_rate', 'exit_rate', 'avg_time_on_page', 'avg_time_on_site'];
        $eventMetrics = ['nb_events', 'nb_visits', 'nb_uniq_visitors'];
        $visitMetrics = ['nb_visits', 'nb_uniq_visitors', 'nb_actions', 'bounce_rate', 'avg_time_on_site'];

        $sections = [
            'content' => $this->normalizeRows($reports['content'], $rowMetrics, 'content'),
            'entry_pages' => $this->normalizeRows($reports['entry_pages'], $rowMetrics, 'content'),
            'exit_pages' => $this->normalizeRows($reports['exit_pages'], $rowMetrics, 'content'),
            'downloads' => $this->normalizeRows($reports['downloads'], $rowMetrics, 'external'),
            'outlinks' => $this->normalizeRows($reports['outlinks'], $rowMetrics, 'external'),
            'site_searches' => $this->normalizeRows($reports['site_searches'], $rowMetrics),
            'site_search_no_results' => $this->normalizeRows($reports['site_search_no_results'], $rowMetrics),
            'events' => $this->normalizeRows($reports['events'], $eventMetrics),
            'event_categories' => $this->normalizeRows($reports['event_categories'], $eventMetrics),
            'event_names' => $this->normalizeRows($reports['event_names'], $eventMetrics),
            'referrers' => $this->normalizeRows($reports['referrers'], $visitMetrics),
            'referrer_websites' => $this->normalizeRows($reports['referrer_websites'], $visitMetrics, 'external'),
            'socials' => $this->normalizeRows($reports['socials'], $visitMetrics),
            'search_engines' => $this->normalizeRows($reports['search_engines'], $visitMetrics),
            'campaigns' => $this->normalizeRows($reports['campaigns'], $visitMetrics),
            'ai_assistants' => $this->normalizeRows($reports['ai_assistants'], $visitMetrics),
            'continents' => $this->normalizeRows($reports['continents'], $visitMetrics),
            'countries' => $this->normalizeRows($reports['countries'], $visitMetrics),
            'devices' => $this->normalizeRows($reports['devices'], $visitMetrics),
            'browsers' => $this->normalizeRows($reports['browsers'], $visitMetrics),
            'operating_systems' => $this->normalizeRows($reports['operating_systems'], $visitMetrics),
            'visit_duration' => $this->normalizeRows($reports['visit_duration'], ['nb_visits', 'nb_uniq_visitors']),
            'pages_per_visit' => $this->normalizeRows($reports['pages_per_visit'], ['nb_visits', 'nb_uniq_visitors']),
            'local_time' => $this->normalizeRows($reports['local_time'], ['nb_visits', 'nb_uniq_visitors']),
            'day_of_week' => $this->normalizeRows($reports['day_of_week'], ['nb_visits', 'nb_uniq_visitors']),
        ];

        $warnings = [];
        if ($previousMetrics === null) {
            $warnings[] = 'Previous-period comparison is unavailable.';
        }
        if (($metrics['nb_uniq_visitors'] ?? null) === null) {
            $warnings[] = 'Range-level unique visitors are not enabled in Matomo; the remaining aggregate reports are still available.';
        }
        if ($series === []) {
            $warnings[] = 'Traffic time-series data is unavailable.';
        }
        foreach ($sections as $name => $rows) {
            if ($reports[$name] === null) {
                $warnings[] = ucfirst(str_replace('_', ' ', $name)).' report is unavailable.';
            }
        }
        if ($reports['returning'] === null) {
            $warnings[] = 'Returning-visitor report is unavailable.';
        }

        return [
            'status' => 'available',
            'generated_at' => now()->toIso8601String(),
            'range' => $range,
            'metrics' => $metrics,
            'comparison' => $this->comparison($metrics, $previousMetrics),
            'series' => $series,
            ...$sections,
            'returning' => $this->normalizeNumericMap($reports['returning']),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param array<string, scalar> $extra
     * @return array<string, scalar>
     */
    private function topRows(int $limit, array $extra = []): array
    {
        return [
            'filter_limit' => $limit,
            'filter_sort_column' => 'nb_visits',
            'filter_sort_order' => 'desc',
            ...$extra,
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

    /** @return array<string, float|null> */
    private function normalizeSummary(array $payload, bool $required = true): array
    {
        $metrics = [];
        foreach (self::METRICS as $metric) {
            if (! array_key_exists($metric, $payload)) {
                if ($metric === 'nb_uniq_visitors' || $required === false) {
                    $metrics[$metric] = null;

                    continue;
                }

                throw new RuntimeException('Matomo Reporting API omitted required aggregate metric '.$metric.'.');
            }

            $value = $this->numericValue($payload[$metric]);
            if ($value === null) {
                if ($metric === 'nb_uniq_visitors' || $required === false) {
                    $metrics[$metric] = null;

                    continue;
                }

                throw new RuntimeException('Matomo Reporting API returned an invalid aggregate metric '.$metric.'.');
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
     * @return array<int, array<string, float|string>>
     */
    private function normalizeRows(?array $payload, array $metricNames, string $labelMode = 'generic'): array
    {
        if ($payload === null) {
            return [];
        }

        $rows = [];
        foreach ($payload as $row) {
            if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                continue;
            }

            $normalized = ['label' => $this->sanitizeLabel($row['label'], $labelMode)];
            foreach ($metricNames as $metric) {
                $normalized[$metric] = $this->numericValue($row[$metric] ?? null) ?? 0.0;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /** @return array<string, float> */
    private function normalizeNumericMap(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $number = $this->numericValue($value);
            if ($number !== null) {
                $normalized[$key] = $number;
            }
        }

        return $normalized;
    }

    /** @param array<string, float|null> $current
     * @param  array<string, float|null>|null  $previous
     * @return array<string, float|null>
     */
    private function comparison(array $current, ?array $previous): array
    {
        $comparison = [];
        foreach (self::METRICS as $metric) {
            $currentValue = $current[$metric] ?? null;
            $previousValue = $previous[$metric] ?? null;
            if ($currentValue === null || $previousValue === null) {
                $comparison[$metric] = null;

                continue;
            }

            if ($previousValue == 0.0) {
                $comparison[$metric] = $currentValue == 0.0 ? 0.0 : null;

                continue;
            }

            $comparison[$metric] = (($currentValue - $previousValue) / abs($previousValue)) * 100;
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
            default => $end->copy()->subYear()->addDay(),
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
                default => 'Last 12 months',
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

    private function sanitizeLabel(string $label, string $mode = 'generic'): string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Unknown';
        }

        if (filter_var($label, FILTER_VALIDATE_URL) !== false) {
            $host = parse_url($label, PHP_URL_HOST);
            $path = parse_url($label, PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : '/';

            if ($mode === 'content') {
                return $path;
            }

            if ($mode === 'external' && is_string($host) && $host !== '') {
                return $host.($path === '/' ? '' : $path);
            }
        }

        return preg_replace('/[?#].*$/', '', $label) ?: $label;
    }
}
