<?php

namespace App\Filament\Widgets;

use App\Models\Artwork;
use App\Models\BlogSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class EditorialStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Publication readiness';

    protected ?string $description = 'Visibility switches and issues that can block or weaken public content.';

    protected function getStats(): array
    {
        $settings = PublicContentSetting::query()->find(1);
        $blogSettings = BlogSetting::query()->find(1);

        $featureStates = [
            'Vita / CV' => (bool) $settings?->getAttribute('cv_enabled'),
            'Exhibitions' => (bool) $settings?->getAttribute('exhibitions_enabled'),
            'Blog' => (bool) $blogSettings?->getAttribute('public_enabled'),
            'Contact' => $settings?->getAttribute('contact_state') === 'enabled',
        ];
        $publicFeatures = count(array_filter($featureStates));
        $hiddenFeatures = array_keys(array_filter($featureStates, static fn (bool $enabled): bool => ! $enabled));

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

        $mediaWarnings = $missingAlt + $missingThumbnail;
        $trackingEnabled = (bool) config('analytics.matomo.tracking_enabled');
        $reportingEnabled = (bool) config('analytics.matomo.reporting_enabled');
        $analyticsState = match (true) {
            $trackingEnabled && $reportingEnabled => 'Tracking + reporting',
            $reportingEnabled => 'Reporting only',
            $trackingEnabled => 'Tracking only',
            default => 'Disabled',
        };

        return [
            Stat::make('Public features', $publicFeatures.'/4')
                ->description($hiddenFeatures === [] ? 'All public features enabled' : 'Not public: '.implode(', ', $hiddenFeatures))
                ->color($hiddenFeatures === [] ? 'success' : 'warning'),
            Stat::make('Media warnings', $mediaWarnings)
                ->description($missingAlt.' missing ALT · '.$missingThumbnail.' missing thumbnail')
                ->color($mediaWarnings === 0 ? 'success' : 'warning'),
            Stat::make('Publication blockers', $publishedWithoutPrimary)
                ->description('Published artworks without primary media')
                ->color($publishedWithoutPrimary === 0 ? 'success' : 'danger'),
            Stat::make('Analytics', $analyticsState)
                ->description($reportingEnabled ? 'Matomo dashboard can read reports' : 'Matomo Reporting API is disabled')
                ->color($reportingEnabled ? 'success' : 'warning'),
        ];
    }
}
