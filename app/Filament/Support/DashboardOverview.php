<?php

namespace App\Filament\Support;

use App\Domain\Analytics\AnalyticsReportAvailability;
use App\Domain\Analytics\AnalyticsWorldMap;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
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
        $storage = $this->storageOverview();
        $activity = $this->activityOverview();
        $publishedArtworks = Artwork::query()->where('state', 'published')->count();
        $publishedPages = SiteSection::query()
            ->where('type', '<>', SiteSectionType::NavigationNode->value)
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
            'map_points' => $countryState === 'available'
                ? app(AnalyticsWorldMap::class)->points($countryRows)
                : [],
            'url' => Analytics::getUrl(),
        ];
    }

    /** @return array<string, mixed> */
    private function storageOverview(): array
    {
        $snapshot = app(StorageWorkspaceOverview::class)->snapshot();
        $capacity = $snapshot['capacity'];
        $breakdown = $snapshot['breakdown'];
        $attention = $snapshot['attention'];
        $segments = is_array($attention['capacity_segments'] ?? null) ? $attention['capacity_segments'] : [];

        return [
            ...$capacity,
            'metric_detail' => (string) ($capacity['remaining_detail'] ?? 'Storage allowance'),
            'used' => (string) ($capacity['authoritative'] ?? '—'),
            'breakdown' => array_values(array_filter($breakdown, 'is_array')),
            'segments' => array_values(array_filter($segments, 'is_array')),
            'url' => MediaAssetResource::getUrl('index'),
        ];
    }

    /** @return array<string, mixed> */
    private function activityOverview(): array
    {
        $overview = app(AdminActivityFeed::class)->overview(null, null, days: 30, search: '');
        $hourly = $overview['hourly'];
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
            'recent_changes' => $overview['total'],
            'clock_activity' => $clockActivity,
            'clock_peak_hour' => $peakHour,
            'clock_peak_count' => $peakHour !== null ? (int) ($hourly[$peakHour] ?? 0) : 0,
            'url' => Activity::getUrl(),
        ];
    }
}
