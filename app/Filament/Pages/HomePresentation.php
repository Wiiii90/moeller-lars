<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\SitePreviewContext;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use DateTimeInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

final class HomePresentation extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Home';

    protected static ?string $slug = 'home';

    protected string $view = 'filament.pages.home-presentation';

    /** @var array<string, mixed>|null */
    public ?array $currentArtwork = null;

    /** @var list<array<string, mixed>> */
    public array $galleries = [];

    /** @var list<array<string, mixed>> */
    public array $newestEligibleArtworks = [];

    public ?string $selectionIssue = null;

    public string $previewUrl = '';

    public function mount(): void
    {
        $this->previewUrl = app(SitePreviewContext::class)->previewSiteUrl();
        $this->loadWorkspace();
    }

    public function toggleGalleryEligibility(int $galleryId): void
    {
        /** @var ArtworkCategory $category */
        $category = ArtworkCategory::query()->findOrFail($galleryId);

        app(ArtworkCategoryEditorialService::class)->update($category, [
            'name' => (string) $category->getAttribute('name'),
            'description' => $category->getAttribute('description'),
            'show_on_home' => ! (bool) $category->getAttribute('show_on_home'),
        ]);

        $this->loadWorkspace();
        Notification::make()->title('Homepage source updated')->success()->send();
    }

    private function loadWorkspace(): void
    {
        $this->selectionIssue = null;
        $this->currentArtwork = null;

        try {
            $current = app(PublicArtworkQuery::class)->latestForHome();
            if ($current instanceof Artwork) {
                $this->currentArtwork = $this->artworkRow($current);
            }
        } catch (LogicException $exception) {
            $this->selectionIssue = $exception->getMessage();
        }

        /** @var EloquentCollection<int, ArtworkCategory> $categories */
        $categories = ArtworkCategory::query()
            ->whereHas('siteSection')
            ->with('siteSection')
            ->withCount([
                'artworks as published_artworks_count' => static fn (Builder $query): Builder => $query->where('state', 'published'),
            ])
            ->withMax([
                'artworks as newest_published_year' => static fn (Builder $query): Builder => $query
                    ->where('state', 'published')
                    ->whereNotNull('work_year'),
            ], 'work_year')
            ->orderBy('name')
            ->get();

        $this->galleries = $categories
            ->map(function (ArtworkCategory $category): array {
                /** @var SiteSection|null $section */
                $section = $category->getRelationValue('siteSection');

                return [
                    'id' => (int) $category->getKey(),
                    'name' => (string) $category->getAttribute('name'),
                    'eligible' => (bool) $category->getAttribute('show_on_home'),
                    'state' => (string) ($section?->getAttribute('state') ?? 'hidden'),
                    'published_artworks' => (int) $category->getAttribute('published_artworks_count'),
                    'newest_year' => $category->getAttribute('newest_published_year'),
                    'workspace_url' => ArtworkResource::getUrl('gallery', ['gallery' => $category->getKey()]),
                ];
            })
            ->values()
            ->all();

        /** @var EloquentCollection<int, Artwork> $eligible */
        $eligible = Artwork::query()
            ->where('state', 'published')
            ->whereNotNull('work_year')
            ->whereHas('category', static function (Builder $query): void {
                $query->where('show_on_home', true)
                    ->whereHas('siteSection', static fn (Builder $section): Builder => $section->where('state', 'published'));
            })
            ->with(['category', 'artworkMedia.mediaAsset.variants'])
            ->orderByDesc('work_year')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $newestYear = $eligible->max('work_year');
        $this->newestEligibleArtworks = $eligible
            ->filter(static fn (Artwork $artwork): bool => (int) $artwork->getAttribute('work_year') === (int) $newestYear)
            ->map(fn (Artwork $artwork): array => $this->artworkRow($artwork))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function artworkRow(Artwork $artwork): array
    {
        $category = $artwork->getRelationValue('category');
        if (! $category instanceof ArtworkCategory) {
            $artwork->loadMissing('category');
            $category = $artwork->getRelationValue('category');
        }

        $workDate = $artwork->getAttribute('work_date');

        return [
            'id' => (int) $artwork->getKey(),
            'title' => (string) $artwork->getAttribute('title'),
            'year' => $artwork->getAttribute('work_year'),
            'date' => $workDate instanceof DateTimeInterface ? $workDate->format('Y-m-d') : null,
            'featured' => (bool) $artwork->getAttribute('featured_on_home'),
            'gallery' => $category instanceof ArtworkCategory ? (string) $category->getAttribute('name') : null,
            'thumbnail_url' => ArtworkResource::thumbnailUrl($artwork),
            'edit_url' => ArtworkResource::getUrl('edit', ['record' => $artwork]),
            'gallery_url' => $category instanceof ArtworkCategory
                ? ArtworkResource::getUrl('gallery', ['gallery' => $category->getKey()])
                : null,
        ];
    }
}
