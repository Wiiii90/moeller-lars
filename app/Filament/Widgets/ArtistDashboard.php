<?php

namespace App\Filament\Widgets;

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminQuickActionService;
use App\Domain\Analytics\AnalyticsReportAvailability;
use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Media\MediaCapacityService;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ArtistDashboard extends Widget
{
    protected string $view = 'filament.widgets.artist-dashboard';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $analytics = ['loaded' => false, 'status' => 'loading'];

    public function loadAnalytics(): void
    {
        if (($this->analytics['loaded'] ?? false) === true) {
            return;
        }

        // Keep this boundary on the existing Analytics reporting contract. A cold
        // Matomo cache may take seconds, so it must not block the admin shell.
        $this->analytics = $this->analyticsOverview(
            app(MatomoReportingClient::class)->report('30d'),
        );
    }

    protected function getViewData(): array
    {
        $drafts = [
            'Artworks' => Artwork::query()->where('state', 'draft')->count(),
            'Exhibitions' => Exhibition::query()->where('state', 'draft')->count(),
            'Vita / CV' => CvEntry::query()->where('state', 'draft')->count(),
            'Blog' => BlogPost::query()->where('state', 'draft')->count(),
        ];
        $draftTotal = array_sum($drafts);

        $missingAlt = MediaAsset::query()
            ->where('state', 'available')
            ->where(function (Builder $query): void {
                $query->whereNull('alt_text')->orWhere('alt_text', '');
            })
            ->count();
        $missingPreview = MediaAsset::query()
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
        $quarantinedMedia = MediaAsset::query()->where('state', 'quarantined')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $contactRouteConfigured = trim((string) (PublicContentSetting::query()->value('contact_recipient_email') ?? '')) !== '';

        $storage = $this->storageOverview(
            app(MediaCapacityService::class)->cachedSnapshotIfAvailable(),
        );
        $readinessIssues = $missingAlt + $missingPreview + $publishedWithoutPrimary + $quarantinedMedia;

        $health = [
            [
                'label' => 'Drafts',
                'value' => (string) $draftTotal,
                'detail' => $draftTotal === 0 ? 'No unpublished drafts' : $this->draftBreakdown($drafts),
                'state' => $draftTotal > 0 ? 'attention' : 'clear',
            ],
            [
                'label' => 'Media readiness',
                'value' => (string) $readinessIssues,
                'detail' => $readinessIssues === 0 ? 'ALT, preview and primary-media checks clear' : 'Content or media needs review',
                'state' => $readinessIssues > 0 ? 'attention' : 'clear',
            ],
            [
                'label' => 'Failed jobs',
                'value' => (string) $failedJobs,
                'detail' => $failedJobs === 0 ? 'No failed background jobs' : 'Operator review required',
                'state' => $failedJobs > 0 ? 'danger' : 'clear',
            ],
            $storage,
        ];

        $attention = [];
        if ($draftTotal > 0) {
            $attention[] = [
                'label' => 'Draft content',
                'detail' => $this->draftBreakdown($drafts),
                'value' => $draftTotal,
                'severity' => 'attention',
                'url' => null,
            ];
        }
        if ($publishedWithoutPrimary > 0) {
            $attention[] = [
                'label' => 'Published artworks missing primary media',
                'detail' => 'Public works should have a primary image assignment.',
                'value' => $publishedWithoutPrimary,
                'severity' => 'danger',
                'url' => ArtworkResource::getUrl('index'),
            ];
        }
        if ($missingAlt > 0) {
            $attention[] = [
                'label' => 'Media missing ALT text',
                'detail' => 'Available media is missing accessibility metadata.',
                'value' => $missingAlt,
                'severity' => 'attention',
                'url' => MediaAssetResource::getUrl('index'),
            ];
        }
        if ($missingPreview > 0) {
            $attention[] = [
                'label' => 'Media missing current preview',
                'detail' => 'The public-v1 thumbnail variant is not ready.',
                'value' => $missingPreview,
                'severity' => 'attention',
                'url' => MediaAssetResource::getUrl('index'),
            ];
        }
        if ($quarantinedMedia > 0) {
            $attention[] = [
                'label' => 'Quarantined media',
                'detail' => 'Review media that did not complete the normal ingest path.',
                'value' => $quarantinedMedia,
                'severity' => 'danger',
                'url' => MediaAssetResource::getUrl('index'),
            ];
        }
        if ($failedJobs > 0) {
            $attention[] = [
                'label' => 'Failed background jobs',
                'detail' => 'A background task failed and needs operator review.',
                'value' => $failedJobs,
                'severity' => 'danger',
                'url' => null,
            ];
        }
        if (! $contactRouteConfigured) {
            $attention[] = [
                'label' => 'Contact routing is not configured',
                'detail' => 'The contact form has no recipient configured. No address is exposed here.',
                'value' => null,
                'severity' => 'attention',
                'url' => PublicContentSettingResource::getNavigationUrl(),
            ];
        }
        if (in_array($storage['status'], ['near_capacity', 'full', 'unavailable'], true)) {
            $attention[] = [
                'label' => match ($storage['status']) {
                    'full' => 'Media storage allowance is full',
                    'near_capacity' => 'Media storage is nearing its allowance',
                    default => 'Storage measurement is unavailable',
                },
                'detail' => $storage['detail'],
                'value' => null,
                'severity' => $storage['status'] === 'full' ? 'danger' : 'attention',
                'url' => StorageCapacity::getUrl(),
            ];
        }

        $activity = app(AdminActivityFeed::class)->recent(7);
        $actor = app(AdminAuditService::class)->requireActor();
        $quickActions = app(AdminQuickActionService::class)->forUser($actor);

        return [
            'health' => $health,
            'attention' => $attention,
            'activity' => $activity,
            'activityUrl' => Activity::getUrl(),
            'analyticsUrl' => Analytics::getUrl(),
            'quickActions' => $quickActions,
            'publicSiteUrl' => route('home'),
        ];
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
                'loaded' => true,
                'status' => $status,
                'status_label' => $status === 'disabled' ? 'Disabled' : 'Unavailable',
                'message' => $message ?? 'Analytics reporting is unavailable.',
                'metrics' => [],
                'chart' => [],
                'series_state' => 'unavailable',
                'content_state' => 'unavailable',
                'top_content' => [],
            ];
        }

        $metrics = is_array($report['metrics'] ?? null) ? $report['metrics'] : [];
        $comparison = is_array($report['comparison'] ?? null) ? $report['comparison'] : [];
        $series = is_array($report['series'] ?? null) ? $report['series'] : [];
        $content = is_array($report['content'] ?? null) ? $report['content'] : [];
        $availability = AnalyticsReportAvailability::fromReport($report);
        $chart = $availability->isAvailable('series') ? $this->trendChart($series) : [];
        $topContent = $availability->isAvailable('content') ? $this->topContent($content) : [];

        return [
            'loaded' => true,
            'status' => $status,
            'status_label' => $status === 'stale' ? 'Cached' : 'Live',
            'message' => $message,
            'metrics' => [
                $this->metric('Visits', $metrics, $comparison, 'nb_visits', 'integer'),
                $this->metric('Actions / visit', $metrics, $comparison, 'nb_actions_per_visit', 'decimal'),
                $this->metric('Average visit', $metrics, $comparison, 'avg_time_on_site', 'duration'),
            ],
            'chart' => $chart,
            'series_state' => ! $availability->isAvailable('series')
                ? 'unavailable'
                : ($chart === [] ? 'no_data' : 'available'),
            'content_state' => ! $availability->isAvailable('content')
                ? 'unavailable'
                : ($topContent === [] ? 'no_data' : 'available'),
            'top_content' => $topContent,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $comparison
     * @return array{label:string,value:string,comparison:string,delta:float|null}
     */
    private function metric(string $label, array $metrics, array $comparison, string $key, string $format): array
    {
        $raw = $metrics[$key] ?? null;
        $value = is_numeric($raw) ? (float) $raw : null;
        $rawDelta = $comparison[$key] ?? null;
        $delta = is_numeric($rawDelta) ? (float) $rawDelta : null;

        return [
            'label' => $label,
            'value' => match ($format) {
                'duration' => $value === null ? '—' : $this->formatDuration($value),
                'decimal' => $value === null ? '—' : number_format($value, 1),
                default => $value === null ? '—' : number_format((int) round($value)),
            },
            'comparison' => $value === null
                ? 'Metric unavailable'
                : ($delta === null ? 'No comparable previous period' : sprintf('%+.1f%% vs previous period', $delta)),
            'delta' => $delta,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $series
     * @return array<string, string|int>
     */
    private function trendChart(array $series): array
    {
        $rows = [];
        foreach ($series as $point) {
            if (! is_array($point) || ! is_string($point['date'] ?? null)) {
                continue;
            }

            $rows[] = [
                'date' => $point['date'],
                'visits' => is_numeric($point['visits'] ?? null) ? max(0.0, (float) $point['visits']) : 0.0,
                'actions' => is_numeric($point['actions'] ?? null) ? max(0.0, (float) $point['actions']) : 0.0,
            ];
        }

        if ($rows === []) {
            return [];
        }

        $width = 720.0;
        $height = 180.0;
        $padding = 8.0;
        $max = 1.0;
        foreach ($rows as $row) {
            $max = max($max, $row['visits'], $row['actions']);
        }

        $count = count($rows);
        $visits = [];
        $actions = [];
        foreach ($rows as $index => $row) {
            $x = $count === 1
                ? $width / 2
                : $padding + (($width - 2 * $padding) * ($index / ($count - 1)));
            $visitsY = $height - $padding - (($height - 2 * $padding) * ($row['visits'] / $max));
            $actionsY = $height - $padding - (($height - 2 * $padding) * ($row['actions'] / $max));
            $visits[] = round($x, 2).','.round($visitsY, 2);
            $actions[] = round($x, 2).','.round($actionsY, 2);
        }

        return [
            'visits_points' => implode(' ', $visits),
            'actions_points' => implode(' ', $actions),
            'start' => (string) $rows[0]['date'],
            'end' => (string) $rows[$count - 1]['date'],
            'points' => $count,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $content
     * @return array<int, array{label:string,value:int|null}>
     */
    private function topContent(array $content): array
    {
        $rows = [];
        foreach ($content as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $raw = $row['nb_hits'] ?? null;
            $rows[] = [
                'label' => $label,
                'value' => is_numeric($raw) ? (int) round((float) $raw) : null,
            ];

            if (count($rows) === 4) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array{configured:bool,measurement_available:bool,status:'unconfigured'|'healthy'|'near_capacity'|'full'|'unavailable',quota_bytes:int|null,authoritative_bytes:int|null,generated_bytes:int|null,managed_bytes:int|null,remaining_bytes:int|null,authoritative_ratio:float|null,original_files:int|null,generated_files:int|null,authoritative_file_bytes:array<string,int>|null}|null  $snapshot
     * @return array{label:string,value:string,detail:string,state:string,status:string}
     */
    private function storageOverview(?array $snapshot): array
    {
        if ($snapshot === null) {
            return [
                'label' => 'Storage',
                'value' => '—',
                'detail' => 'No cached capacity measurement',
                'state' => 'unknown',
                'status' => 'not_measured',
            ];
        }

        if (! $snapshot['measurement_available']) {
            return [
                'label' => 'Storage',
                'value' => '—',
                'detail' => 'The last capacity measurement was unavailable',
                'state' => 'unknown',
                'status' => 'unavailable',
            ];
        }

        if (! $snapshot['configured']) {
            return [
                'label' => 'Storage',
                'value' => 'No quota',
                'detail' => 'No media storage allowance is configured',
                'state' => 'unknown',
                'status' => 'unconfigured',
            ];
        }

        $remaining = $snapshot['remaining_bytes'];
        $status = $snapshot['status'];

        return [
            'label' => 'Storage',
            'value' => is_int($remaining) ? $this->formatBytes($remaining).' left' : '—',
            'detail' => match ($status) {
                'full' => 'The configured media storage allowance is exhausted',
                'near_capacity' => 'Less than 15% of the media storage allowance remains',
                default => 'Authoritative originals are within the configured allowance',
            },
            'state' => match ($status) {
                'full' => 'danger',
                'near_capacity' => 'attention',
                default => 'clear',
            },
            'status' => $status,
        ];
    }

    /** @param array<string, int> $drafts */
    private function draftBreakdown(array $drafts): string
    {
        $parts = [];
        foreach ($drafts as $label => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.strtolower($label);
            }
        }

        return $parts === [] ? 'No drafts' : implode(' · ', $parts);
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $minutes.'m '.$remaining.'s';
    }

    private function formatBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return ($index === 0 ? number_format($value, 0) : number_format($value, 1)).' '.$units[$index];
    }
}
