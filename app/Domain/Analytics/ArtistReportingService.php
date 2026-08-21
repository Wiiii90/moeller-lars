<?php

namespace App\Domain\Analytics;

use InvalidArgumentException;

final class ArtistReportingService
{
    private const PRESETS = ['today', '7d', '30d', '12m'];

    public function __construct(
        private readonly MatomoReportingClient $matomo,
        private readonly ArtworkAttentionReport $artworkAttention,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(string $range = '30d'): array
    {
        $report = $this->report($range);

        return [
            ...$this->base($report),
            'metrics' => [
                'visits' => $this->summaryMetric($report, 'nb_visits'),
                'visitors' => $this->summaryMetric($report, 'nb_uniq_visitors', unsupportedWhenMissing: true),
                'actions' => $this->summaryMetric($report, 'nb_actions'),
                'actions_per_visit' => $this->summaryMetric($report, 'nb_actions_per_visit'),
                'duration' => $this->summaryMetric($report, 'avg_time_on_site'),
                'bounce_rate' => $this->summaryMetric($report, 'bounce_rate'),
            ],
            'trend' => $this->dataset($report, 'series'),
        ];
    }

    /**
     * Canonical Gallery snippet. Pass the public category path (for example `/paintings`)
     * and the stable analytics keys of the artworks currently belonging to that Gallery.
     *
     * @param  list<string>  $artworkAnalyticsKeys
     * @return array<string, mixed>
     */
    public function gallery(string $publicPath, array $artworkAnalyticsKeys, string $range = '30d'): array
    {
        $report = $this->report($range);
        $keys = array_values(array_unique(array_filter(
            $artworkAnalyticsKeys,
            static fn (string $key): bool => trim($key) !== '',
        )));
        $attention = $this->artworkDataset($report, $keys);
        $rows = $attention['rows'];

        $interactions = null;
        if ($attention['state'] !== 'unavailable') {
            $interactions = array_sum(array_map(
                static fn (array $row): int => array_sum([
                    (int) ($row['viewer_opens'] ?? 0),
                    (int) ($row['zooms'] ?? 0),
                    (int) ($row['navigation'] ?? 0),
                    (int) ($row['attention_events'] ?? 0),
                ]),
                $rows,
            ));
        }

        return [
            ...$this->base($report),
            'page' => [
                'visits' => $this->pathMetric($report, $publicPath, 'nb_visits'),
                'views' => $this->pathMetric($report, $publicPath, 'nb_hits'),
            ],
            'artworks' => $attention,
            'artwork_interactions' => $interactions === null
                ? $this->unavailableMetric()
                : $this->availableMetric((float) $interactions),
            'trend' => $this->artworkTrend($attention),
        ];
    }

    /**
     * Canonical Blog snippet. With a public post path it returns that post's Matomo page
     * metrics; without one it returns the ranked `/blog/…` content dataset.
     *
     * @return array<string, mixed>
     */
    public function blog(?string $publicPostPath = null, string $range = '30d'): array
    {
        $report = $this->report($range);

        return [
            ...$this->base($report),
            'post' => $publicPostPath === null ? null : [
                'visits' => $this->pathMetric($report, $publicPostPath, 'nb_visits'),
                'views' => $this->pathMetric($report, $publicPostPath, 'nb_hits'),
            ],
            'reads' => $this->eventMetric($report, 'blog_view'),
            'top_posts' => $this->contentDataset($report, '/blog/'),
        ];
    }

    /** @return array<string, mixed> */
    public function exhibitions(string $range = '30d'): array
    {
        $report = $this->report($range);

        return [
            ...$this->base($report),
            'page' => [
                'visits' => $this->pathMetric($report, '/exhibitions', 'nb_visits'),
                'views' => $this->pathMetric($report, '/exhibitions', 'nb_hits'),
            ],
            'views' => $this->eventMetric($report, 'exhibition_view'),
            'external_clicks' => $this->eventMetric($report, 'exhibition_external_click'),
            'directions_clicks' => $this->eventMetric($report, 'exhibition_directions_click'),
        ];
    }

    /** @return array<string, mixed> */
    public function contact(string $range = '30d'): array
    {
        $report = $this->report($range);

        return [
            ...$this->base($report),
            'messages' => $this->eventMetric($report, 'contact_submit_success'),
            'email_clicks' => $this->eventMetric($report, 'email_click'),
            'instagram_clicks' => $this->eventMetric($report, 'instagram_click'),
        ];
    }

    /** @return array<string, mixed> */
    private function report(string $range): array
    {
        if (! in_array($range, self::PRESETS, true)) {
            throw new InvalidArgumentException('Unsupported analytics range.');
        }

        return $this->matomo->report($range);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function base(array $report): array
    {
        return [
            'status' => (string) ($report['status'] ?? 'unavailable'),
            'cache' => $report['cache'] ?? null,
            'message' => $report['message'] ?? null,
            'range' => $report['range'] ?? null,
            'generated_at' => $report['generated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{state:string,value:float|null}
     */
    private function summaryMetric(array $report, string $metric, bool $unsupportedWhenMissing = false): array
    {
        if (! $this->humanReportAvailable($report)) {
            return $this->unavailableMetric();
        }

        $value = $report['metrics'][$metric] ?? null;
        if (! is_numeric($value)) {
            return $unsupportedWhenMissing ? $this->unsupportedMetric() : $this->unavailableMetric();
        }

        return $this->availableMetric((float) $value);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{state:string,value:float|null}
     */
    private function eventMetric(array $report, string $action): array
    {
        if (! $this->humanReportAvailable($report)) {
            return $this->unavailableMetric();
        }

        $availability = AnalyticsReportAvailability::fromReport($report);
        if (! $availability->isAvailable('events')) {
            return $this->unavailableMetric();
        }

        foreach ($report['events'] ?? [] as $row) {
            if (! is_array($row) || ($row['label'] ?? null) !== $action) {
                continue;
            }

            return is_numeric($row['nb_events'] ?? null)
                ? $this->availableMetric((float) $row['nb_events'])
                : $this->unavailableMetric();
        }

        // Successful Matomo aggregate reports omit dimensions whose measured count is zero.
        return $this->availableMetric(0.0);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{state:string,value:float|null}
     */
    private function pathMetric(array $report, string $path, string $metric): array
    {
        if (! $this->humanReportAvailable($report)) {
            return $this->unavailableMetric();
        }

        $availability = AnalyticsReportAvailability::fromReport($report);
        if (! $availability->isAvailable('content')) {
            return $this->unavailableMetric();
        }

        $path = $this->normalizePath($path);
        foreach ($report['content'] ?? [] as $row) {
            if (! is_array($row) || ($row['label'] ?? null) !== $path) {
                continue;
            }

            return is_numeric($row[$metric] ?? null)
                ? $this->availableMetric((float) $row[$metric])
                : $this->unavailableMetric();
        }

        return $this->availableMetric(0.0);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{state:string,rows:array<int, mixed>}
     */
    private function dataset(array $report, string $name): array
    {
        if (! $this->humanReportAvailable($report)) {
            return ['state' => 'unavailable', 'rows' => []];
        }

        $availability = AnalyticsReportAvailability::fromReport($report);
        if (! $availability->isAvailable($name)) {
            return ['state' => 'unavailable', 'rows' => []];
        }

        $rows = array_values(array_filter($report[$name] ?? [], 'is_array'));

        return ['state' => $rows === [] ? 'empty' : 'available', 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{state:string,rows:array<int, array<string, mixed>>}
     */
    private function contentDataset(array $report, string $prefix): array
    {
        $dataset = $this->dataset($report, 'content');
        if ($dataset['state'] === 'unavailable') {
            return $dataset;
        }

        $rows = array_values(array_filter(
            $dataset['rows'],
            static fn (array $row): bool => is_string($row['label'] ?? null)
                && str_starts_with($row['label'], $prefix),
        ));

        return ['state' => $rows === [] ? 'empty' : 'available', 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<string>  $keys
     * @return array{state:string,rows:array<int, array<string, mixed>>}
     */
    private function artworkDataset(array $report, array $keys): array
    {
        if (! $this->humanReportAvailable($report)) {
            return ['state' => 'unavailable', 'rows' => []];
        }

        $availability = AnalyticsReportAvailability::fromReport($report);
        if (! $availability->isAvailable('artwork_events')) {
            return ['state' => 'unavailable', 'rows' => []];
        }

        if ($keys === []) {
            return ['state' => 'empty', 'rows' => []];
        }

        $allowed = array_fill_keys($keys, true);
        $events = array_values(array_filter(
            $report['artwork_events'] ?? [],
            static fn (mixed $row): bool => is_array($row)
                && is_string($row['analytics_key'] ?? null)
                && isset($allowed[$row['analytics_key']]),
        ));
        $series = array_values(array_filter(
            $report['artwork_event_series'] ?? [],
            static fn (mixed $row): bool => is_array($row)
                && is_string($row['analytics_key'] ?? null)
                && isset($allowed[$row['analytics_key']]),
        ));
        $rows = $this->artworkAttention->build($events, $series);

        return ['state' => $rows === [] ? 'empty' : 'available', 'rows' => $rows];
    }

    /**
     * @param  array{state:string,rows:array<int, array<string, mixed>>}  $artworks
     * @return array{state:string,rows:list<array{date:string,detail_views:int,viewer_opens:int,zooms:int,attention_seconds:float}>}
     */
    private function artworkTrend(array $artworks): array
    {
        if ($artworks['state'] === 'unavailable') {
            return ['state' => 'unavailable', 'rows' => []];
        }

        $days = [];
        foreach ($artworks['rows'] as $artwork) {
            foreach ($artwork['trend'] ?? [] as $day) {
                if (! is_array($day) || ! is_string($day['date'] ?? null)) {
                    continue;
                }

                $date = $day['date'];
                $days[$date] ??= [
                    'date' => $date,
                    'detail_views' => 0,
                    'viewer_opens' => 0,
                    'zooms' => 0,
                    'attention_seconds' => 0.0,
                ];
                $days[$date]['detail_views'] += (int) ($day['detail_views'] ?? 0);
                $days[$date]['viewer_opens'] += (int) ($day['viewer_opens'] ?? 0);
                $days[$date]['zooms'] += (int) ($day['zooms'] ?? 0);
                $days[$date]['attention_seconds'] += (float) ($day['attention_seconds'] ?? 0);
            }
        }

        ksort($days);
        $rows = array_values($days);

        return ['state' => $rows === [] ? 'empty' : 'available', 'rows' => $rows];
    }

    /** @param array<string, mixed> $report */
    private function humanReportAvailable(array $report): bool
    {
        return in_array($report['status'] ?? null, ['available', 'stale'], true);
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');
        $path = preg_replace('/[?#].*$/', '', $path) ?: '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return array{state:'available',value:float} */
    private function availableMetric(float $value): array
    {
        return ['state' => 'available', 'value' => $value];
    }

    /** @return array{state:'unavailable',value:null} */
    private function unavailableMetric(): array
    {
        return ['state' => 'unavailable', 'value' => null];
    }

    /** @return array{state:'unsupported',value:null} */
    private function unsupportedMetric(): array
    {
        return ['state' => 'unsupported', 'value' => null];
    }
}
