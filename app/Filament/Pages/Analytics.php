<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\AnalyticsReportAvailability;
use App\Domain\Analytics\ArtworkAttentionReport;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricsQuery;
use App\Models\DailyMetric;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Analytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.analytics';

    /** @var array<string, string> */
    private const DETAIL_REPORTS = [
        'content' => 'Content',
        'geography' => 'Geography',
        'acquisition' => 'Acquisition',
        'interactions' => 'Interactions',
        'artwork' => 'Artwork',
        'technology' => 'Technology',
    ];

    /** @var list<int> */
    private const DETAIL_PAGE_SIZES = [12, 25, 50];

    public string $range = '30d';

    public string $search = '';

    public string $detailReport = 'content';

    public int $detailPage = 1;

    public int $detailPageSize = 12;

    /** @var array<string, mixed> */
    public array $matomo = [];

    /** @var array<int, array<string, mixed>> */
    public array $kpis = [];

    /** @var array<string, mixed> */
    public array $trendChart = [];

    /** @var array<string, int|float|string> */
    public array $interactionSignals = [];

    /** @var array<int, array<string, mixed>> */
    public array $artworkAttention = [];

    public ?string $selectedArtworkAnalyticsKey = null;

    /** @var array<int, array{label:string,value:string,detail:string}> */
    public array $audienceHighlights = [];

    /** @var array<int, array{label:string,value:string,detail:string}> */
    public array $applicationSignals = [];

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
        $this->detailPage = 1;
        $this->loadRange();
    }

    public function setDetailReport(string $report): void
    {
        if (! array_key_exists($report, self::DETAIL_REPORTS)) {
            return;
        }

        $this->detailReport = $report;
        $this->search = '';
        $this->detailPage = 1;
    }

    public function updatedDetailReport(string $report): void
    {
        if (! array_key_exists($report, self::DETAIL_REPORTS)) {
            $this->detailReport = 'content';
        }

        $this->search = '';
        $this->detailPage = 1;
    }

    public function updatedSearch(): void
    {
        $this->detailPage = 1;
    }

    public function updatedDetailPageSize(int $size): void
    {
        if (! in_array($size, self::DETAIL_PAGE_SIZES, true)) {
            $this->detailPageSize = 12;
        }

        $this->detailPage = 1;
    }

    public function previousDetailPage(): void
    {
        $this->detailPage = max(1, $this->detailPage - 1);
    }

    public function nextDetailPage(): void
    {
        $pages = (int) ($this->detailTable()['pages'] ?? 1);
        $this->detailPage = min(max(1, $pages), $this->detailPage + 1);
    }

    /** @return array<string, string> */
    public function detailReportOptions(): array
    {
        return self::DETAIL_REPORTS;
    }

    /**
     * @return array{
     *     columns:list<string>,
     *     rows:list<list<string>>,
     *     state:string,
     *     message:string|null,
     *     partial:string|null,
     *     total:int,
     *     page:int,
     *     pages:int,
     *     start:int,
     *     end:int
     * }
     */
    public function detailTable(): array
    {
        $definition = $this->detailDefinition($this->detailReport);

        if ($definition['state'] === 'unavailable') {
            return [
                ...$definition,
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'start' => 0,
                'end' => 0,
            ];
        }

        $rows = $definition['rows'];
        $search = mb_strtolower(trim($this->search));
        if ($search !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_contains(
                    mb_strtolower(implode(' ', array_map(static fn (mixed $value): string => (string) $value, $row))),
                    $search,
                ),
            ));
        }

        $total = count($rows);
        $pageSize = in_array($this->detailPageSize, self::DETAIL_PAGE_SIZES, true)
            ? $this->detailPageSize
            : 12;
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $this->detailPage), $pages);
        $start = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $end = $total === 0 ? 0 : min($total, $page * $pageSize);
        $pageRows = array_slice($rows, ($page - 1) * $pageSize, $pageSize);

        $message = $definition['message'];
        if ($total === 0 && $search !== '') {
            $message = 'No rows match this search.';
        } elseif ($total === 0 && $message === null) {
            $message = 'No detail rows in this period.';
        }

        return [
            ...$definition,
            'rows' => $pageRows,
            'state' => $total === 0 ? 'empty' : 'available',
            'message' => $message,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'start' => $start,
            'end' => $end,
        ];
    }

    public function selectArtwork(string $analyticsKey): void
    {
        $exists = collect($this->artworkAttention)
            ->contains(static fn (array $row): bool => ($row['analytics_key'] ?? null) === $analyticsKey);

        $this->selectedArtworkAnalyticsKey = $exists ? $analyticsKey : null;
    }

    public function clearArtworkSelection(): void
    {
        $this->selectedArtworkAnalyticsKey = null;
    }

    private function loadRange(): void
    {
        $this->matomo = app(MatomoReportingClient::class)->report($this->range);
        $this->kpis = $this->buildKpis($this->matomo);
        $this->trendChart = $this->buildTrendChart($this->matomo['series'] ?? []);
        $this->artworkAttention = app(ArtworkAttentionReport::class)->build(
            $this->matomo['artwork_events'] ?? [],
            $this->matomo['artwork_event_series'] ?? [],
        );

        $warnings = array_values(array_filter(
            $this->matomo['warnings'] ?? [],
            static fn (mixed $warning): bool => is_string($warning),
        ));
        $eventsAvailable = ! in_array('Events report is unavailable.', $warnings, true);
        $artworkEventsAvailable = ! in_array('Per-artwork interaction report is unavailable.', $warnings, true);

        $this->interactionSignals = $this->buildInteractionSignals(
            $this->matomo['events'] ?? [],
            $this->artworkAttention,
            $eventsAvailable,
            $artworkEventsAvailable,
        );
        $this->audienceHighlights = $this->buildAudienceHighlights($this->matomo);

        if ($this->selectedArtworkAnalyticsKey !== null) {
            $selectedStillExists = collect($this->artworkAttention)
                ->contains(fn (array $row): bool => ($row['analytics_key'] ?? null) === $this->selectedArtworkAnalyticsKey);
            if (! $selectedStillExists) {
                $this->selectedArtworkAnalyticsKey = null;
            }
        }

        $days = match ($this->range) {
            'today' => 1,
            '7d' => 7,
            '12m' => 365,
            default => 30,
        };

        $botRows = app(OperationalMetricsQuery::class)->recent($days)
            ->filter(static fn (DailyMetric $metric): bool => $metric->getAttribute('metric_name') === 'bot:request')
            ->map(static fn (DailyMetric $metric): array => [
                'name' => 'bot:request',
                'value' => (float) $metric->getAttribute('value'),
            ])
            ->values()
            ->all();
        $this->applicationSignals = $this->buildApplicationSignals($botRows);
    }

    /**
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function detailDefinition(string $report): array
    {
        $report = array_key_exists($report, self::DETAIL_REPORTS) ? $report : 'content';
        $columns = $this->detailColumns($report);
        $status = $this->matomo['status'] ?? null;

        if (! in_array($status, ['available', 'stale'], true)) {
            return $this->unavailableDetail(
                $columns,
                $status === 'disabled'
                    ? 'No reporting data for this environment.'
                    : 'Reporting data is currently unavailable.',
            );
        }

        return match ($report) {
            'geography' => $this->geographyDetail($columns),
            'acquisition' => $this->acquisitionDetail($columns),
            'interactions' => $this->interactionDetail($columns),
            'artwork' => $this->artworkDetail($columns),
            'technology' => $this->technologyDetail($columns),
            default => $this->contentDetail($columns),
        };
    }

    /** @return list<string> */
    private function detailColumns(string $report): array
    {
        return match ($report) {
            'geography' => ['Country', 'Visits', 'Share', 'Rank', 'Tracked actions'],
            'acquisition' => ['Type', 'Source', 'Visits', 'Unique visitors', 'Tracked actions'],
            'interactions' => ['Event', 'Events', 'Visits', 'Unique visitors'],
            'artwork' => ['Artwork', 'Gallery', 'Detail views', 'Opens', 'Zooms', 'Active time', 'Average active'],
            'technology' => ['Type', 'Item', 'Visits', 'Unique visitors', 'Bounce rate'],
            default => ['Content', 'Views', 'Visits', 'Bounce rate', 'Average time'],
        };
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function contentDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        if (! $availability->isAvailable('content')) {
            return $this->unavailableDetail($columns, 'Content report is unavailable.');
        }

        $rows = [];
        foreach ($this->matomo['content'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                continue;
            }

            $rows[] = [
                $row['label'],
                $this->formatNullableInteger($row['nb_hits'] ?? null),
                $this->formatNullableInteger($row['nb_visits'] ?? null),
                $this->formatNullablePercentage($row['bounce_rate'] ?? null),
                $this->formatNullableDuration($row['avg_time_on_page'] ?? null),
            ];
        }

        return $this->availableDetail($columns, $rows, 'No content activity in this period.');
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function geographyDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        if (! $availability->isAvailable('countries')) {
            return $this->unavailableDetail($columns, 'Country-level visit report is unavailable.');
        }

        $countryRows = array_values(array_filter(
            $this->matomo['countries'] ?? [],
            static fn (mixed $row): bool => is_array($row) && is_string($row['label'] ?? null),
        ));
        usort($countryRows, static function (array $a, array $b): int {
            $aVisits = is_numeric($a['nb_visits'] ?? null) ? (float) $a['nb_visits'] : -1.0;
            $bVisits = is_numeric($b['nb_visits'] ?? null) ? (float) $b['nb_visits'] : -1.0;

            return $bVisits <=> $aVisits;
        });

        $totalVisits = is_numeric($this->matomo['metrics']['nb_visits'] ?? null)
            ? (float) $this->matomo['metrics']['nb_visits']
            : null;
        $rank = 0;
        $rows = [];
        foreach ($countryRows as $row) {
            $visits = is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null;
            $rankLabel = '—';
            if ($visits !== null) {
                $rank++;
                $rankLabel = number_format($rank);
            }

            $share = null;
            if ($visits !== null && $totalVisits !== null) {
                $share = $totalVisits > 0 ? ($visits / $totalVisits) * 100 : 0.0;
            }

            $rows[] = [
                (string) $row['label'],
                $this->formatNullableInteger($visits),
                $this->formatNullablePercentage($share),
                $rankLabel,
                $this->formatNullableInteger($row['nb_actions'] ?? null),
            ];
        }

        return $this->availableDetail($columns, $rows, 'No country-level visits in this period.');
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function acquisitionDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        $groups = [
            'referrer_websites' => 'Website',
            'socials' => 'Social network',
            'search_engines' => 'Search engine',
            'ai_assistants' => 'AI assistant',
            'campaigns' => 'Campaign',
        ];
        $availableGroups = 0;
        $unavailableGroups = 0;
        $rows = [];

        foreach ($groups as $report => $label) {
            if (! $availability->isAvailable($report)) {
                $unavailableGroups++;
                continue;
            }

            $availableGroups++;
            foreach ($this->matomo[$report] ?? [] as $row) {
                if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                    continue;
                }

                $rows[] = [
                    $label,
                    $row['label'],
                    $this->formatNullableInteger($row['nb_visits'] ?? null),
                    $this->formatNullableInteger($row['nb_uniq_visitors'] ?? null),
                    $this->formatNullableInteger($row['nb_actions'] ?? null),
                ];
            }
        }

        if ($availableGroups === 0) {
            return $this->unavailableDetail($columns, 'Acquisition reports are unavailable.');
        }

        return $this->availableDetail(
            $columns,
            $rows,
            'No acquisition activity in this period.',
            $unavailableGroups > 0 ? 'Some acquisition reports are unavailable.' : null,
        );
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function interactionDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        if (! $availability->isAvailable('events')) {
            return $this->unavailableDetail($columns, 'Interaction event report is unavailable.');
        }

        $rows = [];
        foreach ($this->matomo['events'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                continue;
            }

            $rows[] = [
                $this->humanizeLabel($row['label']),
                $this->formatNullableInteger($row['nb_events'] ?? null),
                $this->formatNullableInteger($row['nb_visits'] ?? null),
                $this->formatNullableInteger($row['nb_uniq_visitors'] ?? null),
            ];
        }

        return $this->availableDetail($columns, $rows, 'No tracked interactions in this period.');
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function artworkDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        if (! $availability->isAvailable('artwork_events')) {
            return $this->unavailableDetail($columns, 'Per-artwork interaction report is unavailable.');
        }

        $rows = [];
        foreach ($this->artworkAttention as $row) {
            $rows[] = [
                (string) ($row['title'] ?? 'Untitled'),
                (string) ($row['category'] ?? 'No Gallery'),
                $this->formatNullableInteger($row['detail_views'] ?? null),
                $this->formatNullableInteger($row['viewer_opens'] ?? null),
                $this->formatNullableInteger($row['zooms'] ?? null),
                (string) ($row['attention_label'] ?? '—'),
                (string) ($row['average_attention_label'] ?? '—'),
            ];
        }

        return $this->availableDetail(
            $columns,
            $rows,
            'No stable per-artwork interaction data is available for this period yet.',
        );
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function technologyDetail(array $columns): array
    {
        $availability = AnalyticsReportAvailability::fromReport($this->matomo);
        $groups = [
            'devices' => 'Device',
            'browsers' => 'Browser',
            'operating_systems' => 'Operating system',
        ];
        $availableGroups = 0;
        $unavailableGroups = 0;
        $rows = [];

        foreach ($groups as $report => $label) {
            if (! $availability->isAvailable($report)) {
                $unavailableGroups++;
                continue;
            }

            $availableGroups++;
            foreach ($this->matomo[$report] ?? [] as $row) {
                if (! is_array($row) || ! is_string($row['label'] ?? null)) {
                    continue;
                }

                $rows[] = [
                    $label,
                    $row['label'],
                    $this->formatNullableInteger($row['nb_visits'] ?? null),
                    $this->formatNullableInteger($row['nb_uniq_visitors'] ?? null),
                    $this->formatNullablePercentage($row['bounce_rate'] ?? null),
                ];
            }
        }

        if ($availableGroups === 0) {
            return $this->unavailableDetail($columns, 'Technology reports are unavailable.');
        }

        return $this->availableDetail(
            $columns,
            $rows,
            'No technology distribution in this period.',
            $unavailableGroups > 0 ? 'Some technology reports are unavailable.' : null,
        );
    }

    /**
     * @param list<string> $columns
     * @param list<list<string>> $rows
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string|null,partial:string|null}
     */
    private function availableDetail(array $columns, array $rows, ?string $emptyMessage = null, ?string $partial = null): array
    {
        return [
            'columns' => $columns,
            'rows' => $rows,
            'state' => $rows === [] ? 'empty' : 'available',
            'message' => $rows === [] ? $emptyMessage : null,
            'partial' => $partial,
        ];
    }

    /**
     * @param list<string> $columns
     * @return array{columns:list<string>,rows:list<list<string>>,state:string,message:string,partial:null}
     */
    private function unavailableDetail(array $columns, string $message): array
    {
        return [
            'columns' => $columns,
            'rows' => [],
            'state' => 'unavailable',
            'message' => $message,
            'partial' => null,
        ];
    }

    /** @param array<string, mixed> $report
     * @return array<int, array<string, mixed>>
     */
    private function buildKpis(array $report): array
    {
        $definitions = [
            'nb_visits' => 'Visits',
            'nb_uniq_visitors' => 'Unique visitors',
            'nb_actions' => 'Tracked actions',
            'nb_actions_per_visit' => 'Actions / visit',
            'avg_time_on_site' => 'Average visit',
            'bounce_rate' => 'Bounce rate',
        ];

        $status = $report['status'] ?? null;
        $available = in_array($status, ['available', 'stale'], true);
        $metrics = $report['metrics'] ?? [];
        $comparison = $report['comparison'] ?? [];
        $kpis = [];

        foreach ($definitions as $key => $label) {
            if (! $available) {
                $kpis[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => '—',
                    'comparison' => 'Not measured',
                    'delta' => null,
                ];

                continue;
            }

            $rawValue = $metrics[$key] ?? null;
            $delta = $comparison[$key] ?? null;
            if ($rawValue === null) {
                $kpis[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => '—',
                    'comparison' => $key === 'nb_uniq_visitors'
                        ? 'Not processed for this rolling range'
                        : 'Metric unavailable',
                    'delta' => null,
                ];

                continue;
            }

            $value = (float) $rawValue;
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
     * @param  array<int, array<string, mixed>>  $artworkAttention
     * @return array<string, int|float|string>
     */
    private function buildInteractionSignals(
        array $events,
        array $artworkAttention,
        bool $eventsAvailable = true,
        bool $artworkEventsAvailable = true,
    ): array {
        /** @var array<string, int|null> $counts */
        $counts = [];
        foreach ($events as $event) {
            $label = is_string($event['label'] ?? null) ? trim($event['label']) : '';
            if ($label === '') {
                continue;
            }

            $rawCount = $event['nb_events'] ?? null;
            $counts[$label] = is_numeric($rawCount) ? (int) round((float) $rawCount) : null;
        }

        $eventSignal = static function (string $label) use ($counts, $eventsAvailable): int|string {
            if (! $eventsAvailable) {
                return '—';
            }
            if (array_key_exists($label, $counts) && $counts[$label] === null) {
                return '—';
            }

            return $counts[$label] ?? 0;
        };
        $combinedEventSignal = static function (array $labels) use ($eventSignal): int|string {
            $total = 0;
            foreach ($labels as $label) {
                $value = $eventSignal($label);
                if (! is_int($value)) {
                    return '—';
                }
                $total += $value;
            }

            return $total;
        };

        $detailViews = array_sum(array_map(static fn (array $row): int => (int) ($row['detail_views'] ?? 0), $artworkAttention));
        $attentionEvents = array_sum(array_map(static fn (array $row): int => (int) ($row['attention_events'] ?? 0), $artworkAttention));

        return [
            'Artwork detail views' => $artworkEventsAvailable ? $detailViews : '—',
            'Artwork opens' => $eventSignal('artwork_open'),
            'Artwork zooms' => $eventSignal('artwork_zoom_used'),
            'Active artwork views' => $artworkEventsAvailable ? $attentionEvents : '—',
            'Next / previous' => $combinedEventSignal(['artwork_next', 'artwork_previous']),
            'Exhibition views' => $eventSignal('exhibition_view'),
            'Exhibition outbound' => $combinedEventSignal(['exhibition_external_click', 'exhibition_directions_click']),
            'Blog reads' => $eventSignal('blog_view'),
            'Contact messages' => $eventSignal('contact_submit_success'),
            'Email / Instagram clicks' => $combinedEventSignal(['email_click', 'instagram_click']),
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

        $availability = AnalyticsReportAvailability::fromReport($report);
        $rawVisits = $report['metrics']['nb_visits'] ?? null;
        $rawReturning = $report['returning']['nb_visits_returning'] ?? null;
        $visits = is_numeric($rawVisits) ? (int) round((float) $rawVisits) : null;
        $returning = is_numeric($rawReturning) ? (int) round((float) $rawReturning) : null;

        if ($visits === 0 && $returning === null) {
            $returning = 0;
        }

        $visitorSplit = $visits !== null && $returning !== null
            ? [
                'value' => number_format(max(0, $visits - $returning)).' / '.number_format($returning),
                'detail' => 'visits in selected period',
            ]
            : [
                'value' => '—',
                'detail' => 'Returning-visitor split unavailable',
            ];

        $sourceAvailable = $availability->isAvailable('referrers');
        $countryAvailable = $availability->isAvailable('countries');
        $contentAvailable = $availability->isAvailable('content');
        $aiAvailable = $availability->isAvailable('ai_assistants');

        $topSource = $sourceAvailable ? $this->topRow($report['referrers'] ?? []) : null;
        $topCountry = $countryAvailable ? $this->topRow($report['countries'] ?? []) : null;
        $topContent = $contentAvailable ? $this->topRow($report['content'] ?? [], 'nb_hits') : null;
        $topAi = $aiAvailable ? $this->topRow($report['ai_assistants'] ?? []) : null;

        return [
            [
                'label' => 'New / returning',
                'value' => $visitorSplit['value'],
                'detail' => $visitorSplit['detail'],
            ],
            [
                'label' => 'Leading source',
                'value' => $sourceAvailable ? ($topSource['label'] ?? 'No data') : '—',
                'detail' => ! $sourceAvailable
                    ? 'Referrer report unavailable'
                    : (isset($topSource['nb_visits']) ? number_format((int) $topSource['nb_visits']).' visits' : 'No referrer data'),
            ],
            [
                'label' => 'Leading country',
                'value' => $countryAvailable ? ($topCountry['label'] ?? 'No data') : '—',
                'detail' => ! $countryAvailable
                    ? 'Country report unavailable'
                    : (isset($topCountry['nb_visits']) ? number_format((int) $topCountry['nb_visits']).' visits' : 'No geography data'),
            ],
            [
                'label' => 'Most viewed content',
                'value' => $contentAvailable ? ($topContent['label'] ?? 'No data') : '—',
                'detail' => ! $contentAvailable
                    ? 'Content report unavailable'
                    : (isset($topContent['nb_hits']) ? number_format((int) $topContent['nb_hits']).' views/actions' : 'No content data'),
            ],
            [
                'label' => 'AI referrals',
                'value' => $aiAvailable ? ($topAi['label'] ?? 'None detected') : '—',
                'detail' => ! $aiAvailable
                    ? 'AI-assistant report unavailable'
                    : (isset($topAi['nb_visits']) ? number_format((int) $topAi['nb_visits']).' visits' : 'No AI-assistant referrals in range'),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function topRow(array $rows, string $metric = 'nb_visits'): ?array
    {
        $rankedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => is_string($row['label'] ?? null)
                && trim($row['label']) !== ''
                && is_numeric($row[$metric] ?? null),
        ));

        if ($rankedRows === []) {
            return null;
        }

        usort($rankedRows, static fn (array $a, array $b): int => ((float) $b[$metric]) <=> ((float) $a[$metric]));

        return $rankedRows[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{label:string,value:string,detail:string}>
     */
    private function buildApplicationSignals(array $rows): array
    {
        $botRequests = 0;
        $measured = false;

        foreach ($rows as $row) {
            if (($row['name'] ?? null) !== 'bot:request' || ! is_numeric($row['value'] ?? null)) {
                continue;
            }

            $measured = true;
            $botRequests += (int) round((float) $row['value']);
        }

        if (! $measured) {
            return [];
        }

        return [[
            'label' => 'Bot requests',
            'value' => number_format($botRequests),
            'detail' => 'Application telemetry',
        ]];
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

    private function formatNullableInteger(mixed $value): string
    {
        return is_numeric($value) ? number_format((int) round((float) $value)) : '—';
    }

    private function formatNullablePercentage(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1).'%' : '—';
    }

    private function formatNullableDuration(mixed $value): string
    {
        return is_numeric($value) ? $this->formatDuration((int) round((float) $value)) : '—';
    }

    private function humanizeLabel(string $label): string
    {
        return ucwords(str_replace(['_', '-'], ' ', trim($label)));
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
}
