<?php

namespace App\Filament\Support;

use App\Domain\Analytics\AnalyticsReportAvailability;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageUnits;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\StorageCapacity;
use App\Models\Artwork;
use App\Models\SiteSection;

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
        $reportingAvailable = in_array($status, ['available', 'stale'], true);
        $message = is_string($report['message'] ?? null) ? $report['message'] : null;
        $metrics = $reportingAvailable && is_array($report['metrics'] ?? null) ? $report['metrics'] : [];
        $comparison = $reportingAvailable && is_array($report['comparison'] ?? null) ? $report['comparison'] : [];
        $visits = is_numeric($metrics['nb_visits'] ?? null) ? (float) $metrics['nb_visits'] : null;
        $visitors = is_numeric($metrics['nb_uniq_visitors'] ?? null) ? (float) $metrics['nb_uniq_visitors'] : null;
        $visitsDelta = is_numeric($comparison['nb_visits'] ?? null) ? (float) $comparison['nb_visits'] : null;
        $availability = AnalyticsReportAvailability::fromReport($report);
        $countriesAvailable = $reportingAvailable && $availability->isAvailable('countries');
        $countryRows = $countriesAvailable
            ? array_values(array_filter(
                $report['countries'] ?? [],
                static fn (mixed $row): bool => is_array($row)
                    && is_string($row['label'] ?? null)
                    && trim($row['label']) !== '',
            ))
            : [];
        $countryState = ! $countriesAvailable
            ? 'unavailable'
            : ($countryRows === [] ? 'empty' : 'available');

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'available' => 'Live',
                'stale' => 'Cached',
                'disabled' => 'Disabled',
                'loading' => 'Loading',
                default => 'Unavailable',
            },
            'message' => $message ?? ($reportingAvailable ? null : 'Analytics data is currently unavailable.'),
            'range' => 'Last 30 days',
            'visits_display' => $visits === null ? '—' : number_format((int) round($visits)),
            'visitors_display' => $visitors === null ? '—' : number_format((int) round($visitors)),
            'visits_delta' => $visitsDelta === null ? null : sprintf('%+.1f%%', $visitsDelta),
            'country_state' => $countryState,
            'map_points' => $countryState === 'available' ? $this->countryMapPoints($countryRows) : [],
            'url' => Analytics::getUrl(),
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

        $positiveVisits = array_values(array_filter(
            array_map(
                static fn (array $row): ?float => is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null,
                $countryRows,
            ),
            static fn (?float $value): bool => $value !== null && $value > 0,
        ));
        $countryMax = $positiveVisits === [] ? 1.0 : max($positiveVisits);
        $points = [];

        foreach ($countryRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $visits = is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null;
            $coords = $centroids[$label] ?? null;

            if ($label === '' || $visits === null || $visits <= 0 || ! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $latitude = (float) $coords[0];
            $longitude = (float) $coords[1];
            $points[] = [
                'label' => $label,
                'visits' => (int) round($visits),
                'x' => min(99.0, max(1.0, (($longitude + 180.0) / 360.0) * 100.0)),
                'y' => min(98.0, max(2.0, ((90.0 - $latitude) / 180.0) * 100.0)),
                'size' => 9.0 + (22.0 * sqrt($visits / $countryMax)),
            ];
        }

        return $points;
    }

    /** @return array<string, mixed> */
    private function storageOverview(?array $snapshot): array
    {
        if ($snapshot === null) {
            return [
                'status' => 'not_measured',
                'label' => 'No recent measurement',
                'detail' => 'Open Storage for a current measurement.',
                'metric_detail' => 'No cached measurement',
                'configured' => false,
                'configuration_valid' => false,
                'measurement_available' => false,
                'percent' => null,
                'authoritative' => '—',
                'generated' => '—',
                'used' => '—',
                'remaining' => '—',
                'allowance' => '—',
                'url' => StorageCapacity::getUrl(),
            ];
        }

        $configurationValid = (bool) ($snapshot['configuration_valid'] ?? false);
        $configured = (bool) ($snapshot['configured'] ?? false);
        $measurementAvailable = (bool) ($snapshot['measurement_available'] ?? false);
        $ratio = is_numeric($snapshot['authoritative_ratio'] ?? null) ? (float) $snapshot['authoritative_ratio'] : null;
        $status = is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'unavailable';
        $authoritative = MediaStorageUnits::formatBytes($snapshot['authoritative_bytes'] ?? null);
        $generated = MediaStorageUnits::formatBytes($snapshot['generated_bytes'] ?? null);
        $remaining = $configured && $measurementAvailable
            ? MediaStorageUnits::formatBytes($snapshot['remaining_bytes'] ?? null)
            : '—';
        $allowance = $configured && $configurationValid
            ? MediaStorageUnits::formatBytes($snapshot['quota_bytes'] ?? null)
            : '—';
        $percent = $configured && $measurementAvailable && $ratio !== null
            ? (int) round(min(1, max(0, $ratio)) * 100)
            : null;

        return [
            'status' => $status,
            'label' => match ($status) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Healthy',
                'unavailable' => $configurationValid ? 'Measurement unavailable' : 'Allowance unavailable',
                default => 'Allowance not configured',
            },
            'detail' => null,
            'metric_detail' => match (true) {
                ! $configurationValid => 'Allowance unavailable',
                ! $configured => 'Allowance not configured',
                ! $measurementAvailable => 'Measurement unavailable',
                default => 'Cached authoritative originals',
            },
            'configured' => $configured,
            'configuration_valid' => $configurationValid,
            'measurement_available' => $measurementAvailable,
            'percent' => $percent,
            'authoritative' => $authoritative,
            'generated' => $generated,
            'used' => $authoritative,
            'remaining' => $remaining,
            'allowance' => $allowance,
            'url' => StorageCapacity::getUrl(),
        ];
    }

    /** @return array<string, mixed> */
    private function activityOverview(): array
    {
        $overview = app(AdminActivityFeed::class)->overview(null, null, days: 30, search: '');
        $hourly = is_array($overview['hourly'] ?? null) ? $overview['hourly'] : [];
        $clockActivity = [];

        foreach ($hourly as $hour => $count) {
            $clockActivity[] = [
                'hour' => (int) $hour,
                'count' => (int) $count,
            ];
        }

        $peakHour = null;
        if (array_sum($hourly) > 0) {
            $peakCount = max($hourly);
            $peakHour = (int) array_search($peakCount, $hourly, true);
        }

        return [
            'recent_changes' => (int) ($overview['total'] ?? 0),
            'clock_activity' => $clockActivity,
            'clock_peak_hour' => $peakHour,
            'clock_peak_count' => $peakHour !== null ? (int) ($hourly[$peakHour] ?? 0) : 0,
            'url' => Activity::getUrl(),
        ];
    }
}
