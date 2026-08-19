<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Analytics;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use DateTimeInterface;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

final class ArtistDashboard extends Widget
{
    protected string $view = 'filament.widgets.artist-dashboard';

    protected static ?int $sort = 1;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

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

        $attention = array_values(array_filter([
            $missingAlt > 0 ? ['label' => 'Media missing ALT text', 'value' => $missingAlt, 'url' => MediaAssetResource::getUrl('index')] : null,
            $missingThumbnail > 0 ? ['label' => 'Media missing current preview', 'value' => $missingThumbnail, 'url' => MediaAssetResource::getUrl('index')] : null,
            $publishedWithoutPrimary > 0 ? ['label' => 'Published artworks without primary image', 'value' => $publishedWithoutPrimary, 'url' => ArtworkResource::getUrl('index')] : null,
            ! (bool) config('analytics.matomo.reporting_enabled') ? ['label' => 'Analytics reporting disabled', 'value' => null, 'url' => Analytics::getUrl()] : null,
        ]));

        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = AuditEvent::query()
            ->orderByDesc('occurred_at')
            ->limit(7)
            ->get();
        $activity = $events
            ->map(static function (AuditEvent $event): array {
                $occurredAt = $event->getAttribute('occurred_at');

                return [
                    'action' => str_replace(['.', '_'], [' · ', ' '], (string) $event->getAttribute('action')),
                    'area' => str_replace('_', ' ', (string) $event->getAttribute('entity_type')),
                    'when' => $occurredAt instanceof DateTimeInterface ? $occurredAt->format('M j, H:i') : '—',
                ];
            })
            ->all();

        return compact('sections', 'attention', 'activity');
    }

    /**
     * @param class-string<Model> $model
     * @param list<string> $states
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
