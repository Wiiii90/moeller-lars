<?php

namespace App\Filament\Widgets;

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminQuickActionService;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaStorageUnits;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\SitePages;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ArtistDashboard extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.artist-dashboard';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function addArtworkAction(): Action
    {
        return Action::make('addArtwork')
            ->label('Add artwork')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('gallery_id')
                    ->label('Gallery')
                    ->placeholder('Choose Gallery')
                    ->options(fn (): array => ArtworkCategory::query()
                        ->orderBy('position')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->helperText('Galleries and their public placement are managed from Pages.')
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->redirect(ArtworkResource::getUrl('create', ['gallery' => (int) $data['gallery_id']]));
            });
    }

    public function managePagesAction(): Action
    {
        return Action::make('managePages')
            ->label('Pages')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('gray')
            ->url(SitePages::getUrl());
    }

    public function filesAction(): Action
    {
        return Action::make('files')
            ->label('Files')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->url(MediaAssetResource::getUrl('index'));
    }

    public function generalAction(): Action
    {
        return Action::make('general')
            ->label('General')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('gray')
            ->url(PublicContentSettingResource::getNavigationUrl());
    }

    public function openSiteAction(): Action
    {
        return Action::make('openSite')
            ->label('Open public site')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(route('home'))
            ->openUrlInNewTab();
    }

    protected function getViewData(): array
    {
        $artworkStates = $this->stateCounts(Artwork::class);
        $exhibitionStates = $this->stateCounts(Exhibition::class);
        $blogStates = $this->stateCounts(BlogPost::class);
        $mediaStates = $this->stateCounts(MediaAsset::class);
        $editorialGroups = [$artworkStates, $exhibitionStates, $blogStates];
        $drafts = $this->sumStates($editorialGroups, 'draft');
        $hiddenPages = SiteSection::query()
            ->where('type', '<>', SiteSection::TYPE_NAVIGATION_GROUP)
            ->where('state', 'hidden')
            ->count();

        $editorialStatus = [
            ['label' => 'Published content', 'value' => $this->sumStates($editorialGroups, 'published'), 'tone' => 'positive'],
            ['label' => 'Draft content', 'value' => $drafts, 'tone' => 'attention'],
            ['label' => 'Hidden pages', 'value' => $hiddenPages, 'tone' => 'muted'],
            ['label' => 'Scheduled posts', 'value' => (int) ($blogStates['scheduled'] ?? 0), 'tone' => 'muted'],
        ];

        $analytics = $this->analyticsOverview(app(ArtistReportingService::class)->dashboard('30d'));
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

        $attention = array_values(array_filter([
            $drafts > 0 ? [
                'label' => 'Draft content awaiting publication',
                'value' => $drafts,
                'detail' => 'Across artworks and Journal entries',
                'url' => SitePages::getUrl(),
            ] : null,
            $hiddenPages > 0 ? [
                'label' => 'Hidden public pages',
                'value' => $hiddenPages,
                'detail' => 'Review page visibility and menu placement from Pages',
                'url' => SitePages::getUrl(),
            ] : null,
            $missingAlt > 0 ? [
                'label' => 'Files missing ALT text',
                'value' => $missingAlt,
                'detail' => 'Available image files should carry useful accessibility metadata',
                'url' => MediaAssetResource::getUrl('index'),
            ] : null,
            $missingThumbnail > 0 ? [
                'label' => 'Files missing current preview',
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
                'label' => 'Quarantined files',
                'value' => $quarantinedMedia,
                'detail' => 'Review files that are not available for editorial reuse',
                'url' => MediaAssetResource::getUrl('index'),
            ] : null,
            in_array($analytics['status'], ['disabled', 'unavailable'], true) ? [
                'label' => $analytics['status'] === 'disabled' ? 'Analytics reporting disabled' : 'Analytics reporting unavailable',
                'value' => null,
                'detail' => $analytics['message'],
                'url' => Analytics::getUrl(),
            ] : null,
            in_array($storage['status'], ['near_capacity', 'full', 'unavailable'], true) ? [
                'label' => match ($storage['status']) {
                    'full' => 'File storage allowance full',
                    'near_capacity' => 'File storage near capacity',
                    default => 'File storage measurement unavailable',
                },
                'value' => $storage['percent'],
                'value_suffix' => $storage['percent'] === null ? '' : '%',
                'detail' => $storage['remaining'] === null ? $storage['detail'] : $storage['remaining'].' remaining',
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

    /** @param list<array<string, int>> $groups */
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
                'status_label' => $status === 'disabled' ? 'Disabled' : 'Unavailable',
                'message' => $message ?? 'Analytics data is currently unavailable.',
                'range' => 'Last 30 days',
                'visits_display' => '—',
                'visitors_display' => '—',
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
        $visits = $this->metricValue($metrics['visits'] ?? null);
        $visitors = $this->metricValue($metrics['visitors'] ?? null);
        $actionsPerVisit = $this->metricValue($metrics['actions_per_visit'] ?? null);
        $duration = $this->metricValue($metrics['duration'] ?? null);
        $bounceRate = $this->metricValue($metrics['bounce_rate'] ?? null);
        $visitsDelta = $this->metricValue($comparison['visits'] ?? null);

        $trendDataset = is_array($report['trend'] ?? null) ? $report['trend'] : [];
        $trendRows = ($trendDataset['state'] ?? null) === 'available' && is_array($trendDataset['rows'] ?? null)
            ? array_values(array_filter($trendDataset['rows'], 'is_array'))
            : [];
        $contentDataset = is_array($report['content'] ?? null) ? $report['content'] : [];
        $contentRows = ($contentDataset['state'] ?? null) === 'available' && is_array($contentDataset['rows'] ?? null)
            ? array_values(array_filter($contentDataset['rows'], 'is_array'))
            : [];
        $content = [];

        foreach ($contentRows as $row) {
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $hits = $row['nb_hits'] ?? null;
            if ($label === '' || ! is_numeric($hits)) {
                continue;
            }

            $content[] = ['label' => $label, 'value' => (int) round((float) $hits)];
            if (count($content) === 4) {
                break;
            }
        }

        return [
            'status' => $status,
            'status_label' => $status === 'stale' ? 'Cached' : 'Live',
            'message' => $message,
            'range' => 'Last 30 days',
            'visits_display' => $visits === null ? '—' : number_format((int) round($visits)),
            'visitors_display' => $visitors === null ? '—' : number_format((int) round($visitors)),
            'visits_delta' => $visitsDelta === null ? null : sprintf('%+.1f%%', $visitsDelta),
            'actions_per_visit' => $actionsPerVisit === null ? '—' : number_format($actionsPerVisit, 1),
            'average_visit' => $duration === null ? '—' : $this->formatDuration($duration),
            'bounce_rate' => $bounceRate === null ? '—' : number_format($bounceRate, 1).'%',
            'trend' => $this->visitTrend($trendRows),
            'content' => $content,
            'content_state' => match ($contentDataset['state'] ?? 'unavailable') {
                'available' => $content === [] ? 'empty' : 'available',
                'empty' => 'empty',
                default => 'unavailable',
            },
        ];
    }

    private function metricValue(mixed $metric): ?float
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return null;
        }

        return (float) $metric['value'];
    }

    /**
     * @param  list<array<mixed>>  $series
     * @return array<string, mixed>
     */
    private function visitTrend(array $series): array
    {
        if ($series === []) {
            return [];
        }

        $values = array_map(static function (array $row): float {
            $value = $row['visits'] ?? $row['nb_visits'] ?? null;

            return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
        }, $series);
        $max = max(1.0, ...$values);
        $width = 680.0;
        $height = 150.0;
        $padding = 4.0;
        $count = count($series);
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
            'start' => is_string($series[0]['date'] ?? null) ? $series[0]['date'] : '',
            'end' => is_string($series[$count - 1]['date'] ?? null) ? $series[$count - 1]['date'] : '',
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
                'detail' => 'Open Storage for a current measurement.',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        if (! ($snapshot['configuration_valid'] ?? true)) {
            return [
                'status' => 'unavailable',
                'label' => 'Allowance unavailable',
                'detail' => 'The runtime allowance configuration needs operator attention.',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        if (! ($snapshot['configured'] ?? false)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Allowance not configured',
                'detail' => null,
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        if (! ($snapshot['measurement_available'] ?? false)) {
            return [
                'status' => 'unavailable',
                'label' => 'Measurement unavailable',
                'detail' => 'Existing files remain readable; Storage can retry the authoritative measurement.',
                'percent' => null,
                'remaining' => null,
                'url' => $url,
            ];
        }

        $ratio = is_numeric($snapshot['authoritative_ratio'] ?? null)
            ? (float) $snapshot['authoritative_ratio']
            : null;
        $remainingBytes = is_int($snapshot['remaining_bytes'] ?? null) ? $snapshot['remaining_bytes'] : null;
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
            'percent' => $ratio === null ? null : (int) round(min(1, max(0, $ratio)) * 100),
            'remaining' => $remainingBytes === null ? null : MediaStorageUnits::formatBytes($remainingBytes),
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
}
