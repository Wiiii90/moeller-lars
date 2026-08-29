<?php

namespace App\Domain\Admin;

use App\Domain\Analytics\AnalyticsReportAvailability;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageUnits;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\StorageCapacity;
use App\Models\Artwork;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class DashboardOverview
{
    /**
     * @return array{
     *   analytics:array<string,mixed>,
     *   storage:array<string,mixed>,
     *   activity:array<string,mixed>,
     *   metrics:list<array{label:string,value:string,detail:string}>
     * }
     */
    public function snapshot(): array
    {
        $analytics = $this->analyticsOverview(app(MatomoReportingClient::class)->report('30d'));
        $storage = $this->storageOverview(app(MediaCapacityService::class)->cachedSnapshotIfAvailable());
        $activity = $this->activityOverview();
        $publishedArtworks = Artwork::query()->where('state', 'published')->count();
        $publishedPages = SiteSection::query()
            ->where('type', '<>', SiteNodeType::NavigationNode->value)
            ->where('state', 'published')
            ->count();

        $metrics = [
            ['label' => 'Visits', 'value' => $analytics['visits_display'], 'detail' => 'Last 30 days'],
            ['label' => 'Unique visitors', 'value' => $analytics['visitors_display'], 'detail' => 'Last 30 days'],
            ['label' => 'Published artworks', 'value' => number_format($publishedArtworks), 'detail' => 'Public now'],
            ['label' => 'Published pages', 'value' => number_format($publishedPages), 'detail' => 'Navigation groups excluded'],
            ['label' => 'Storage used', 'value' => $storage['percent'] === null ? '—' : $storage['percent'].'%', 'detail' => $storage['metric_detail']],
            ['label' => 'Recent changes', 'value' => number_format($activity['recent_changes']), 'detail' => 'Last 30 days'],
        ];

        return compact('analytics', 'storage', 'activity', 'metrics');
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function analyticsOverview(array $report): array
    {
        $status = is_string($report['status'] ?? null) ? $report['status'] : 'unavailable';
        $message = is_string($report['message'] ?? null) ? $report['message'] : null;
        $url = Analytics::getUrl();

        if (! in_array($status, ['available', 'stale'], true)) {
            return [
                'status' => $status,
                'status_label' => match ($status) {
                    'disabled' => 'Disabled',
                    'loading' => 'Loading',
                    default => 'Unavailable',
                },
                'message' => $message ?? 'Analytics data is currently unavailable.',
                'range' => 'Last 30 days',
                'visits_display' => '—',
                'visitors_display' => '—',
                'visits_delta' => null,
                'country_state' => 'unavailable',
                'map_points' => [],
                'url' => $url,
            ];
        }

        $metrics = is_array($report['metrics'] ?? null) ? $report['metrics'] : [];
        $comparison = is_array($report['comparison'] ?? null) ? $report['comparison'] : [];
        $visits = is_numeric($metrics['nb_visits'] ?? null) ? (float) $metrics['nb_visits'] : null;
        $visitors = is_numeric($metrics['nb_uniq_visitors'] ?? null) ? (float) $metrics['nb_uniq_visitors'] : null;
        $visitsDelta = is_numeric($comparison['nb_visits'] ?? null) ? (float) $comparison['nb_visits'] : null;
        $availability = AnalyticsReportAvailability::fromReport($report);
        $countrySource = is_array($report['countries'] ?? null) ? $report['countries'] : [];
        $countryRows = array_values(array_filter($countrySource, 'is_array'));
        $countryState = ! $availability->isAvailable('countries')
            ? 'unavailable'
            : ($countryRows === [] ? 'empty' : 'available');

        return [
            'status' => $status,
            'status_label' => $status === 'stale' ? 'Cached' : 'Live',
            'message' => $message,
            'range' => 'Last 30 days',
            'visits_display' => $visits === null ? '—' : number_format((int) round($visits)),
            'visitors_display' => $visitors === null ? '—' : number_format((int) round($visitors)),
            'visits_delta' => $visitsDelta === null ? null : sprintf('%+.1f%%', $visitsDelta),
            'country_state' => $countryState,
            'map_points' => $countryState === 'available' ? $this->countryMapPoints($countryRows) : [],
            'url' => $url,
        ];
    }

    /** @param list<array<string, mixed>> $countryRows
     * @return list<array{label:string,visits:int,x:float,y:float,size:float}>
     */
    private function countryMapPoints(array $countryRows): array
    {
        $centroids = config('analytics-country-centroids', []);
        if (! is_array($centroids)) {
            return [];
        }

        $maxVisits = 0;
        foreach ($countryRows as $row) {
            $visits = $row['nb_visits'] ?? null;
            if (is_numeric($visits)) {
                $maxVisits = max($maxVisits, (int) round((float) $visits));
            }
        }
        $maxVisits = max(1, $maxVisits);
        $points = [];

        foreach ($countryRows as $row) {
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $visits = is_numeric($row['nb_visits'] ?? null) ? (int) round((float) $row['nb_visits']) : 0;
            $coords = $centroids[$label] ?? null;
            if ($label === '' || $visits <= 0 || ! is_array($coords) || count($coords) < 2 || ! is_numeric($coords[0]) || ! is_numeric($coords[1])) {
                continue;
            }

            $latitude = (float) $coords[0];
            $longitude = (float) $coords[1];
            $points[] = [
                'label' => $label,
                'visits' => $visits,
                'x' => min(99.0, max(1.0, (($longitude + 180.0) / 360.0) * 100.0)),
                'y' => min(98.0, max(2.0, ((90.0 - $latitude) / 180.0) * 100.0)),
                'size' => 7.0 + (15.0 * sqrt($visits / $maxVisits)),
            ];
        }

        return $points;
    }

    /** @return array<string, mixed> */
    private function storageOverview(?array $snapshot): array
    {
        $url = StorageCapacity::getUrl();

        if ($snapshot === null) {
            return [
                'status' => 'not_measured',
                'label' => 'No recent measurement',
                'detail' => 'Open Storage for a current measurement.',
                'metric_detail' => 'No cached measurement',
                'percent' => null,
                'used' => '—',
                'remaining' => '—',
                'allowance' => '—',
                'url' => $url,
            ];
        }

        $used = is_int($snapshot['authoritative_bytes'] ?? null)
            ? MediaStorageUnits::formatBytes($snapshot['authoritative_bytes'])
            : '—';
        $remaining = is_int($snapshot['remaining_bytes'] ?? null)
            ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes'])
            : '—';
        $allowance = is_int($snapshot['quota_bytes'] ?? null)
            ? MediaStorageUnits::formatBytes($snapshot['quota_bytes'])
            : '—';

        if (! ($snapshot['configuration_valid'] ?? true)) {
            return [
                'status' => 'unavailable',
                'label' => 'Allowance unavailable',
                'detail' => 'The runtime allowance configuration needs operator attention.',
                'metric_detail' => 'Allowance unavailable',
                'percent' => null,
                'used' => $used,
                'remaining' => $remaining,
                'allowance' => $allowance,
                'url' => $url,
            ];
        }

        if (! ($snapshot['configured'] ?? false)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Allowance not configured',
                'detail' => 'Storage usage is measurable, but no artist allowance is configured.',
                'metric_detail' => 'Allowance not configured',
                'percent' => null,
                'used' => $used,
                'remaining' => $remaining,
                'allowance' => $allowance,
                'url' => $url,
            ];
        }

        if (! ($snapshot['measurement_available'] ?? false)) {
            return [
                'status' => 'unavailable',
                'label' => 'Measurement unavailable',
                'detail' => 'Existing files remain readable; Storage can retry the authoritative measurement.',
                'metric_detail' => 'Measurement unavailable',
                'percent' => null,
                'used' => $used,
                'remaining' => $remaining,
                'allowance' => $allowance,
                'url' => $url,
            ];
        }

        $ratio = is_numeric($snapshot['authoritative_ratio'] ?? null) ? (float) $snapshot['authoritative_ratio'] : null;
        $status = is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'unavailable';

        return [
            'status' => $status,
            'label' => match ($status) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Healthy',
                default => 'Measurement unavailable',
            },
            'detail' => null,
            'metric_detail' => 'Cached authoritative originals',
            'percent' => $ratio === null ? null : (int) round(min(1.0, max(0.0, $ratio)) * 100),
            'used' => $used,
            'remaining' => $remaining,
            'allowance' => $allowance,
            'url' => $url,
        ];
    }

    /**
     * @return array{
     *   recent_changes:int,
     *   clock_points:list<array{label:string,x:float,y:float}>,
     *   calendar_label:string,
     *   calendar_days:list<array{day:int,date:string,count:int}|null>,
     *   url:string
     * }
     */
    private function activityOverview(): array
    {
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $metricStart = $today->copy()->subDays(29);
        $monthStart = $today->copy()->startOfMonth();
        $queryStart = $metricStart->lessThan($monthStart) ? $metricStart : $monthStart;

        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = AuditEvent::query()
            ->where('occurred_at', '>=', $queryStart)
            ->where('occurred_at', '<', $tomorrow)
            ->orderBy('occurred_at')
            ->get(['occurred_at']);

        $calendarCounts = [];
        for ($day = 1; $day <= $monthStart->daysInMonth; $day++) {
            $date = $monthStart->copy()->addDays($day - 1);
            $calendarCounts[$date->toDateString()] = 0;
        }

        $recentChanges = 0;
        $clockPoints = [];

        foreach ($events as $event) {
            $occurredAt = $event->getAttribute('occurred_at');
            if (! $occurredAt instanceof CarbonInterface) {
                continue;
            }

            if ($occurredAt->greaterThanOrEqualTo($metricStart)) {
                $recentChanges++;
                $minutes = ((int) $occurredAt->format('G') * 60) + (int) $occurredAt->format('i');
                $angle = $minutes / (24 * 60) * 2 * pi();

                $clockPoints[] = [
                    'label' => $occurredAt->format('M j · H:i'),
                    'x' => 50 + (42 * sin($angle)),
                    'y' => 50 - (42 * cos($angle)),
                ];
            }

            $date = $occurredAt->toDateString();
            if (array_key_exists($date, $calendarCounts)) {
                $calendarCounts[$date]++;
            }
        }

        $calendarDays = [];
        for ($offset = 1; $offset < $monthStart->dayOfWeekIso; $offset++) {
            $calendarDays[] = null;
        }

        foreach ($calendarCounts as $date => $count) {
            $calendarDays[] = [
                'day' => (int) date('j', strtotime($date)),
                'date' => $date,
                'count' => $count,
            ];
        }

        while (count($calendarDays) % 7 !== 0) {
            $calendarDays[] = null;
        }

        return [
            'recent_changes' => $recentChanges,
            'clock_points' => $clockPoints,
            'calendar_label' => $monthStart->format('F Y'),
            'calendar_days' => $calendarDays,
            'url' => Activity::getUrl(),
        ];
    }
}
