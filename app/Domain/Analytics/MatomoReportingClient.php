<?php

namespace App\Domain\Analytics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class MatomoReportingClient
{
    private const CACHE_SCHEMA = 6;

    private const CACHE_NAMESPACE = 'analytics:matomo:v5';

    /** @var list<string> */
    private const METRICS = [
        'nb_visits',
        'nb_uniq_visitors',
        'nb_actions',
        'nb_actions_per_visit',
        'avg_time_on_site',
        'bounce_rate',
    ];

    public function __construct(private readonly MatomoConfiguration $configuration) {}

    /** @return array<string, mixed> */
    public function report(string $preset): array
    {
        $preset = in_array($preset, ['today', '7d', '30d', '12m'], true) ? $preset : '30d';

        if (! $this->configuration->reportingEnabled()) {
            return $this->emptyReport($preset, 'disabled');
        }

        try {
            $this->configuration->validateForReporting();
        } catch (RuntimeException $exception) {
            return $this->emptyReport($preset, 'unavailable', $exception->getMessage());
        }

        $freshKey = $this->cacheKey($preset, 'fresh');
        $staleKey = $this->cacheKey($preset, 'stale');
        $fresh = Cache::get($freshKey);
        if ($this->isCurrentSchema($fresh)) {
            $fresh['cache'] = 'fresh';

            return $fresh;
        }
        if ($fresh !== null) {
            Cache::forget($freshKey);
        }

        try {
            $report = $this->fetchReport($preset);
            Cache::put($freshKey, $report, now()->addSeconds($this->configuration->reportCacheSeconds()));
            Cache::put($staleKey, $report, now()->addSeconds($this->configuration->reportStaleSeconds()));
            $report['cache'] = 'miss';

            return $report;
        } catch (ConnectionException|RuntimeException $exception) {
            $stale = Cache::get($staleKey);
            if ($this->isCurrentSchema($stale)) {
                $stale['status'] = 'stale';
                $stale['cache'] = 'stale';
                $stale['warning'] = $exception->getMessage();

                return $stale;
            }
            if ($stale !== null) {
                Cache::forget($staleKey);
            }

            return $this->emptyReport($preset, 'unavailable', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyReport($preset, 'unavailable', 'Matomo reporting is temporarily unavailable.');
        }
    }

    /** @return array<string, mixed> */
    private function fetchReport(string $preset): array
    {
        $range = $this->range($preset);
        $siteId = $this->configuration->siteId();
        if ($siteId === null) {
            throw new RuntimeException('Matomo site ID is unavailable.');
        }

        $date = $range['start'].','.$range['end'];
        $previousDate = $range['previous_start'].','.$range['previous_end'];

        $definitions = [
            'summary' => $this->nestedRequest('VisitsSummary.get', $siteId, 'range', $date),
            'previous_summary' => $this->nestedRequest('VisitsSummary.get', $siteId, 'range', $previousDate),
            'series' => $this->nestedRequest('VisitsSummary.get', $siteId, 'day', $date),
            'content' => $this->nestedRequest('Actions.getPageUrls', $siteId, 'range', $date, $this->topRows(30, ['expanded' => 1, 'flat' => 1])),
            'entry_pages' => $this->nestedRequest('Actions.getEntryPageUrls', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'exit_pages' => $this->nestedRequest('Actions.getExitPageUrls', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'downloads' => $this->nestedRequest('Actions.getDownloads', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'outlinks' => $this->nestedRequest('Actions.getOutlinks', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'site_searches' => $this->nestedRequest('Actions.getSiteSearchKeywords', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'site_search_no_results' => $this->nestedRequest('Actions.getSiteSearchNoResultKeywords', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'events' => $this->nestedRequest('Events.getAction', $siteId, 'range', $date, $this->topRows(40, ['flat' => 1])),
            'event_categories' => $this->nestedRequest('Events.getCategory', $siteId, 'range', $date, $this->topRows(25, ['flat' => 1])),
            'event_names' => $this->nestedRequest('Events.getName', $siteId, 'range', $date, $this->topRows(25, ['flat' => 1])),
            'referrers' => $this->nestedRequest('Referrers.getReferrerType', $siteId, 'range', $date, $this->topRows(20)),
            'referrer_websites' => $this->nestedRequest('Referrers.getWebsites', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'socials' => $this->nestedRequest('Referrers.getSocials', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'search_engines' => $this->nestedRequest('Referrers.getSearchEngines', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'campaigns' => $this->nestedRequest('Referrers.getCampaigns', $siteId, 'range', $date, $this->topRows(20, ['expanded' => 1, 'flat' => 1])),
            'ai_assistants' => $this->nestedRequest('Referrers.getAll', $siteId, 'range', $date, $this->topRows(20, [
                'segment' => 'referrerType==6',
                'expanded' => 1,
                'flat' => 1,
            ])),
            'continents' => $this->nestedRequest('UserCountry.getContinent', $siteId, 'range', $date, $this->topRows(10)),
            'countries' => $this->nestedRequest('UserCountry.getCountry', $siteId, 'range', $date, $this->topRows(15)),
            'devices' => $this->nestedRequest('DevicesDetection.getType', $siteId, 'range', $date, $this->topRows(15)),
            'browsers' => $this->nestedRequest('DevicesDetection.getBrowsers', $siteId, 'range', $date, $this->topRows(15)),
            'operating_systems' => $this->nestedRequest('DevicesDetection.getOsFamilies', $siteId, 'range', $date, $this->topRows(15)),
            'visit_duration' => $this->nestedRequest('VisitorInterest.getNumberOfVisitsPerVisitDuration', $siteId, 'range', $date, $this->topRows(20)),
            'pages_per_visit' => $this->nestedRequest('VisitorInterest.getNumberOfVisitsPerPage', $siteId, 'range', $date, $this->topRows(20)),
            'local_time' => $this->nestedRequest('VisitTime.getVisitInformationPerLocalTime', $siteId, 'range', $date, $this->topRows(24)),
            'day_of_week' => $this->nestedRequest('VisitTime.getByDayOfWeek', $siteId, 'range', $date, $this->topRows(7)),
            'returning' => $this->nestedRequest('VisitsSummary.get', $siteId, 'range', $date, ['segment' => 'visitorType==returning']),
            'artwork_events' => $this->nestedRequest('Events.getAction', $siteId, 'range', $date, $this->topRows(100, ['expanded' => 1])),
            'artwork_event_series' => $this->nestedRequest('Events.getAction', $siteId, 'day', $date, $this->topRows(100, ['expanded' => 1])),
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
        $artworkEvents = $this->normalizeArtworkEvents($reports['artwork_events']);
        $artworkEventSeries = $this->normalizeArtworkEventSeries($reports['artwork_event_series'], $range['end']);

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
            'countries' => $this->normalizeRows($reports['countries'], $visitMetrics, 'generic', true),
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
        if ($reports['artwork_events'] === null) {
            $warnings[] = 'Per-artwork interaction report is unavailable.';
        }
        if ($reports['artwork_event_series'] === null) {
            $warnings[] = 'Per-artwork interaction trend is unavailable.';
        }
        if ($reports['returning'] === null) {
            $warnings[] = 'Returning-visitor report is unavailable.';
        }

        return [
            'schema' => self::CACHE_SCHEMA,
            'status' => 'available',
            'generated_at' => now()->toIso8601String(),
            'range' => $range,
            'metrics' => $metrics,
            'comparison' => $this->comparison($metrics, $previousMetrics),
            'series' => $series,
            ...$sections,
            'artwork_events' => $artworkEvents,
            'artwork_event_series' => $artworkEventSeries,
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

    /** @return list<array<string, float|string>> */
    private function normalizeArtworkEvents(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $rows = [];
        foreach ($payload as $actionRow) {
            if (! is_array($actionRow) || ! is_string($actionRow['label'] ?? null)) {
                continue;
            }

            $action = trim($actionRow['label']);
            if (! str_starts_with($action, 'artwork_')) {
                continue;
            }

            $subtable = $actionRow['subtable'] ?? null;
            if (! is_array($subtable)) {
                continue;
            }

            foreach ($subtable as $nameRow) {
                if (! is_array($nameRow) || ! is_string($nameRow['label'] ?? null)) {
                    continue;
                }

                $analyticsKey = trim($nameRow['label']);
                if ($analyticsKey === '') {
                    continue;
                }

                $rows[] = [
                    'action' => $action,
                    'analytics_key' => $analyticsKey,
                    'nb_events' => $this->numericValue($nameRow['nb_events'] ?? null) ?? 0.0,
                    'nb_visits' => $this->numericValue($nameRow['nb_visits'] ?? null) ?? 0.0,
                    'nb_uniq_visitors' => $this->numericValue($nameRow['nb_uniq_visitors'] ?? null) ?? 0.0,
                    'nb_events_with_value' => $this->numericValue($nameRow['nb_events_with_value'] ?? null) ?? 0.0,
                    'sum_event_value' => $this->numericValue($nameRow['sum_event_value'] ?? null) ?? 0.0,
                    'avg_event_value' => $this->numericValue($nameRow['avg_event_value'] ?? null) ?? 0.0,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, float|string>> */
    private function normalizeArtworkEventSeries(?array $payload, string $fallbackDate): array
    {
        if ($payload === null || $payload === []) {
            return [];
        }

        $series = [];
        if (array_is_list($payload)) {
            foreach ($this->normalizeArtworkEvents($payload) as $row) {
                $series[] = ['date' => $fallbackDate, ...$row];
            }

            return $series;
        }

        foreach ($payload as $date => $dayPayload) {
            if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! is_array($dayPayload)) {
                continue;
            }

            foreach ($this->normalizeArtworkEvents($dayPayload) as $row) {
                $series[] = ['date' => $date, ...$row];
            }
        }

        usort($series, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));

        return $series;
    }

    /** @param list<string> $metricNames
     * @return array<int, array<string, float|string|null>>
     */
    private function normalizeRows(
        ?array $payload,
        array $metricNames,
        string $labelMode = 'generic',
        bool $preservePresentationMetadata = false,
    ): array {
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
                $normalized[$metric] = $this->numericValue($row[$metric] ?? null);
            }

            if ($preservePresentationMetadata) {
                $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
                $code = $row['code'] ?? $metadata['code'] ?? null;
                $logo = $row['logo'] ?? $metadata['logo'] ?? null;

                if (is_string($code) && preg_match('/^[a-z]{2}$/i', trim($code)) === 1) {
                    $normalized['code'] = strtolower(trim($code));
                }
                if (is_string($logo) && trim($logo) !== '') {
                    $normalized['logo'] = trim($logo);
                }
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

    /** @return array<string, mixed> */
    private function emptyReport(string $preset, string $status, ?string $warning = null): array
    {
        return [
            'schema' => self::CACHE_SCHEMA,
            'status' => $status,
            'generated_at' => null,
            'range' => $this->range($preset),
            'metrics' => array_fill_keys(self::METRICS, null),
            'comparison' => array_fill_keys(self::METRICS, null),
            'series' => [],
            'content' => [],
            'entry_pages' => [],
            'exit_pages' => [],
            'downloads' => [],
            'outlinks' => [],
            'site_searches' => [],
            'site_search_no_results' => [],
            'events' => [],
            'event_categories' => [],
            'event_names' => [],
            'referrers' => [],
            'referrer_websites' => [],
            'socials' => [],
            'search_engines' => [],
            'campaigns' => [],
            'ai_assistants' => [],
            'continents' => [],
            'countries' => [],
            'devices' => [],
            'browsers' => [],
            'operating_systems' => [],
            'visit_duration' => [],
            'pages_per_visit' => [],
            'local_time' => [],
            'day_of_week' => [],
            'returning' => [],
            'artwork_events' => [],
            'artwork_event_series' => [],
            'warnings' => $warning === null ? [] : [$warning],
        ];
    }

    private function cacheKey(string $preset, string $kind): string
    {
        return self::CACHE_NAMESPACE.':site:'.$this->configuration->siteId().':'.$preset.':'.$kind;
    }

    private function isCurrentSchema(mixed $report): bool
    {
        return is_array($report) && ($report['schema'] ?? null) === self::CACHE_SCHEMA;
    }
}
