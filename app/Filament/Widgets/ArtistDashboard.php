<?php

namespace App\Filament\Widgets;

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminQuickActionService;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
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

    public function addExhibitionAction(): Action
    {
        return Action::make('addExhibition')
            ->label('Add exhibition')
            ->icon(Heroicon::OutlinedPlus)
            ->url(ExhibitionResource::getUrl('create'));
    }

    public function addCvEntryAction(): Action
    {
        return Action::make('addCvEntry')
            ->label('Add Vita / CV entry')
            ->icon(Heroicon::OutlinedPlus)
            ->url(CvEntryResource::getUrl('create'));
    }

    public function addBlogPostAction(): Action
    {
        return Action::make('addBlogPost')
            ->label('Add blog post')
            ->icon(Heroicon::OutlinedPlus)
            ->url(BlogPostResource::getUrl('create'));
    }

    public function managePagesAction(): Action
    {
        return Action::make('managePages')
            ->label('Manage pages')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('gray')
            ->url(SitePages::getUrl());
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
        $sections = [
            $this->summary('Artworks', Artwork::class, ['published', 'draft', 'archived'], ArtworkResource::getUrl('index')),
            $this->gallerySummary(),
            $this->summary('Exhibitions', Exhibition::class, ['published', 'draft', 'hidden'], ExhibitionResource::getUrl('index')),
            $this->summary('Vita / CV', CvEntry::class, ['published', 'draft', 'hidden'], CvEntryResource::getUrl('index')),
            $this->summary('Blog', BlogPost::class, ['published', 'scheduled', 'draft'], BlogPostResource::getUrl('index')),
            $this->summary('Media', MediaAsset::class, ['available', 'quarantined', 'deleted'], MediaAssetResource::getUrl('index')),
        ];

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
        $analyticsReportingDisabled = (bool) config('analytics.matomo.reporting_enabled') === false;

        $attention = array_values(array_filter([
            $missingAlt > 0 ? ['label' => 'Media missing ALT text', 'value' => $missingAlt, 'url' => MediaAssetResource::getUrl('index')] : null,
            $missingThumbnail > 0 ? ['label' => 'Media missing current preview', 'value' => $missingThumbnail, 'url' => MediaAssetResource::getUrl('index')] : null,
            $publishedWithoutPrimary > 0 ? ['label' => 'Published artworks without primary image', 'value' => $publishedWithoutPrimary, 'url' => ArtworkResource::getUrl('index')] : null,
            $analyticsReportingDisabled ? ['label' => 'Analytics reporting disabled', 'value' => null, 'url' => Analytics::getUrl()] : null,
        ]));

        $activity = app(AdminActivityFeed::class)->recent(7);
        $activityUrl = Activity::getUrl();
        $actor = app(AdminAuditService::class)->requireActor();
        $quickActions = app(AdminQuickActionService::class)->forUser($actor);

        return compact('sections', 'attention', 'activity', 'activityUrl', 'quickActions');
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $states
     * @return array{label:string,total:int,detail:string,url:string}
     */
    private function summary(string $label, string $model, array $states, string $url): array
    {
        $counts = $model::query()
            ->selectRaw('state, COUNT(*) AS aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $parts = [];
        foreach ($states as $state) {
            $count = (int) ($counts->get($state) ?? 0);
            if ($count > 0) {
                $parts[] = $count.' '.$state;
            }
        }

        return [
            'label' => $label,
            'total' => (int) $counts->sum(),
            'detail' => $parts === [] ? 'No records yet' : implode(' · ', $parts),
            'url' => $url,
        ];
    }

    /** @return array{label:string,total:int,detail:string,url:string} */
    private function gallerySummary(): array
    {
        $counts = SiteSection::query()
            ->where('type', SiteSection::TYPE_GALLERY)
            ->selectRaw('state, COUNT(*) AS aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');
        $published = (int) ($counts->get('published') ?? 0);
        $hidden = (int) ($counts->get('hidden') ?? 0);

        return [
            'label' => 'Galleries',
            'total' => (int) $counts->sum(),
            'detail' => $published.' published · '.$hidden.' hidden',
            'url' => SitePages::getUrl(),
        ];
    }
}
