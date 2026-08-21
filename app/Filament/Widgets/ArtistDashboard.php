<?php

namespace App\Filament\Widgets;

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminQuickActionService;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Media\MediaCapacityService;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ArtistDashboard extends Widget
{
    protected string $view = 'filament.widgets.artist-dashboard';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $artworkStates = $this->stateCounts(Artwork::class);
        $exhibitionStates = $this->stateCounts(Exhibition::class);
        $cvStates = $this->stateCounts(CvEntry::class);
        $blogStates = $this->stateCounts(BlogPost::class);
        $mediaStates = $this->stateCounts(MediaAsset::class);

        $editorialStatus = [
            [
                'label' => 'Published',
                'value' => $this->sumStates([$artworkStates, $exhibitionStates, $cvStates, $blogStates], 'published'),
                'tone' => 'positive',
            ],
            [
                'label' => 'Drafts',
                'value' => $this->sumStates([$artworkStates, $exhibitionStates, $cvStates, $blogStates], 'draft'),
                'tone' => 'attention',
            ],
            [
                'label' => 'Hidden',
                'value' => $this->sumStates([$exhibitionStates, $cvStates], 'hidden'),
                'tone' => 'muted',
            ],
            [
                'label' => 'Scheduled',
                'value' => (int) ($blogStates['scheduled'] ?? 0),
                'tone' => 'muted',
            ],
        ];

        $analytics = $this->analyticsOverview(app(MatomoReportingClient::class)->report('30d'));
        $storage = $this->storageOverview(app(MediaCapacityService::class)->cachedSnapshotIfAvailable());

        $missingAlt = MediaAsset::query()
            ->where('state', 'available')
            ->where(function (Builder $query): void {
                $query->whereNull('alt_text')->orWhere('alt_text', '');
            })
            ->count();
        $missingThumbnail = MediaAsset::query()
            ->where('state', 'available')
            ->whereDoesntHave('variants', fn (Builder $query): Builder => $query
                ->where('variant_kind', 'thumbnail')
                ->where('transform_profile', 'public-v1')
                ->where('state', 'available'))
            ->count();
        $publishedWithoutPrimary = Artwork::query()
            ->where('state', 'published')
            ->whereDoesntHave('artworkMedia', fn (Builder $query): Builder => $query->where('role', 'primary'))
            ->count();
        $quarantinedMedia = (int) ($mediaStates['quarantined'] ?? 0);
        $drafts = (int) collect($editorialStatus)->firstWhere('label', 'Drafts')['value'];

        $attention = array_values(array_filter([
            $drafts > 0 ? [
                'label' => 'Draft content awaiting publication',
                'value' => $drafts,
                'detail' => 'Across artworks, exhibitions, Vita / CV and Blog',
                'url' => null,
            ] : null,
            $missingAlt > 0 ? [
                'label' => 'Media missing ALT text',
                'value' => $missingAlt,
                'detail' => 'Available media should carry useful accessibility metadata',
                'url' => MediaAssetResource::getUrl('index'),
            ] : null,
            $missingThumbnail > 0 ? [
                'label' => 'Media missing current preview',
                'value' => $missingThumbnail,
                'detail' => 'Thumbnail variant public-v1 is not ready',
                'url' => MediaAssetResource::getUrl('index'),
            ] : null,
            $publishedWithoutPrimary > 0 ? [
                'label' => 'Published artwork without primary image',
                'value' => $publishedWithoutPrimary,
                'detail' => 'Published artwork is missing its primary visual',
                'url' => ArtworkResource::getUrl('index'),
            ] : null,
            $quarantinedMedia > 0 ? [
                'label' => 'Quarantined media',
                'value' => $quarantinedMedia,
                'detail' => 'Review media that is not available for editorial reuse',
                'url' => MediaAssetResource::getUrl('index'),
            ] : null,
            in_array($analytics['status'], ['disabled', 'unavailable'], true) ? [
                'label' => $analytics['status'] === 'disabled' ? 'Analytics reporting disabled' : 'Analytics reporting unavailable',
                'value' => null,
                'detail' => $analytics['message'],
                'url' => Analytics::getUrl(),
            ] : null,
            in_array($storage['status'], ['near_capacity', 'full'], true) ? [
                'label' => $storage['status'] === 'full' ? 'Media storage allowance full' : 'Media storage near capacity',
                'value' => $storage['percent'],
                'value_suffix' => '%',
                'detail' => $storage['remaining'] === null ? null : $storage['remaining'].' remaining',
                'url' => $storage['url'],
            ] : null,
        ]));

        $activity = app(AdminActivityFeed::class)->recent(6);
        $activityUrl = Activity::getUrl();
        $actor = app(AdminAuditService::class)->requireActor();
        $quickActions = app(AdminQuickActionService::class)->forUser($actor);

        return compact('analytics', 'editorialStatus', 'attention', 'activity', 'activityUrl', 'quickActions', 'storage');
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, int>
     */
    private function stateCounts(string $model): array
    {
        return $model::query()
            ->selectRaw('state, COUNT(*) AS aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<array<string, int>>  $groups
     */
    private function sumStates(array $groups, string $state): int
    {
        return array_sum(array_map(static fn (array $group): int => (int) ($group[$state] ?? 0), $groups));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function analyticsOverview(array $report): array
    {
        $status = is_string($report['status'] ?? null) ? $report['status'] : 'unavailable';
        $message = is_string($report['message'] ?? null) ? $report['message'] : null;

        if (! in_array($status, ['available', 'stale'], true)) {
            return [
                'status' => $status,
                'status_label' => match ($status) {
                    'disabled' => 'Disabled',
                    default => 'Unavailable',
                },
                'message' => $message ?? 'Analytics data is currently unavailable.',
                'range' => 'Last 30 days',
                'visits' => null,
                'visits_display' => '—',
                'visits_delta' => null,
                'actions_per_visit' => '—',
                'average_visit' => '—',
                'bounce_rate' => '—',
                'trend' => [],
                'content' => [],
                'content_state' => 'unavailable',
            ];
        }

        $metrics = is_array($report['metrics'] ?? null) ? $report['metrics'] : [];
        $comparison = is_array($report['comparison'] ?? null) ? $report['comparison'] : [];
        $visits = is_numeric($metrics['nb_visits'] ?? null) ? (int) round((float) $metrics['nb_visits']) : null;
        $actionsPerVisit = is_numeric($metrics['nb_actions_per_visit'] ?? null)
            ? number_format((float) $metrics['nb_actions_per_visit'], 1)
            : '—';
        $averageVisit = is_numeric($metrics['avg_time_on_site'] ?? null)
            ? $this->formatDuration((float) $metrics['avg_time_on_site'])
            : '—';
        $bounceRate = is_numeric($metrics['bounce_rate'] ?? null)
            ? number_format((float) $metrics['bounce_rate'], 1).'%'
            : '—';
        $visitsDelta = is_numeric($comparison['nb_visits'] ?? null)
            ? sprintf('%+.1f%%', (float) $comparison['nb_visits'])
            : null;

        $series = is_array($report['series'] ?? null) ? $report['series'] : [];
        $warnings = array_values(array_filter(
            is_array($report['warnings'] ?? null) ? $report['warnings'] : [],
            static fn (mixed $warning): bool => is_string($warning),
        ));
        $contentUnavailable = in_array('Content report is unavailable.', $warnings, true);
        $contentRows = is_array($report['content'] ?? null) ? $report['content'] : [];
        $content = [];

        foreach ($contentRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $hits = $row['nb_hits'] ?? null;
            if ($label === '' || ! is_numeric($hits)) {
                continue;
            }

            $content[] = [
                'label' => $label,
                'value' => (int) round((float) $hits),
            ];

            if (count($content) === 4) {
                break;
            }
        }

        return [
            'status' => $status,
            'status_label' => $status === 'stale' ? 'Cached' : 'Live',
            'message' => $message,
            'range' => 'Last 30 days',
            'visits' => $visits,
            'visits_display' => $visits === null ? '—' : number_format($visits),
            'visits_delta' => $visitsDelta,
            'actions_per_visit' => $actionsPerVisit,
            'average_visit' => $averageVisit,
            'bounce_rate' => $bounceRate,
            'trend' => $this->visitTrend($series),
            'content' => $content,
            'content_state' => $contentUnavailable ? 'unavailable' : ($content === [] ? 'empty' : 'available'),
        ];
    }

    /**
     * @param  array<int, mixed>  $series
     * @return array<string, mixed>
     */
    private function visitTrend(array $series): array
    {
        $rows = array_values(array_filter($series, static fn (mixed $row): bool => is_array($row)));
        if ($rows === []) {
            return [];
        }

        $values = array_map(
            static fn (array $row): float => is_numeric($row['visits'] ?? null) ? max(0.0, (float) $row['visits']) : 0.0,
            $rows,
        );
        $max = max(1.0, ...$values);
        $width = 680.0;
        $height = 150.0;
        $padding = 4.0;
        $count = count($rows);
        $points = [];

        foreach ($values as $index => $value) {
            $x = $count === 1
                ? $width / 2
                : $padding + (($width - 2 * $padding) * ($index / ($count - 1)));
            $y = $height - $padding - (($height - 2 * $padding) * ($value / $max));
            $points[] = round($x, 2).','.round($y, 2);
        }

        return [
            'points' => implode(' ', $points),
            'start' => is_string($rows[0]['date'] ?? null) ? $rows[0]['date'] : '',
            'end' => is_string($rows[$count - 1]['date'] ?? null) ? $rows[$count - 1]['date'] : '',
            'has_visits' => max($values) > 0,
        ];
    }

    /** @return array<string, mixed> */
    private function storageOverview(?array $snapshot): array
    {
        $url = StorageCapacity::getUrl();

        if ($snapshot === null) {
            return [
                'status' => 'not_measured',
                'label' => 'No recent measurement',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        if (! ($snapshot['configured'] ?? false)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Allowance not configured',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        if (! ($snapshot['measurement_available'] ?? false)) {
            return [
                'status' => 'unavailable',
                'label' => 'Measurement unavailable',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        $ratio = is_float($snapshot['authoritative_ratio'] ?? null) || is_int($snapshot['authoritative_ratio'] ?? null)
            ? (float) $snapshot['authoritative_ratio']
            : null;
        $remaining = is_int($snapshot['remaining_bytes'] ?? null)
            ? $this->formatBytes($snapshot['remaining_bytes'])
            : null;
        $status = is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'unavailable';

        return [
            'status' => $status,
            'label' => match ($status) {
                'full' => 'Allowance full',
                'near_capacity' => 'Near capacity',
                'healthy' => 'Healthy',
                default => 'Measurement unavailable',
            },
            'percent' => $ratio === null ? null : (int) round(min(1, max(0, $ratio)) * 100),
            'remaining' => $remaining,
            'url' => $url,
        ];
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        if ($seconds < 60) {
            return $seconds.' sec';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;
        if ($minutes < 60) {
            return $minutes.'m '.$remainingSeconds.'s';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours.'h '.$remainingMinutes.'m';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
