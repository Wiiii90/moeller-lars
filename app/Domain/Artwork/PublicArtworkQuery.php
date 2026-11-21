<?php

namespace App\Domain\Artwork;

use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublicArtworkQuery
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    /** Six complete Artwork cards per request; only those rows load media relationships. */
    public const GALLERY_BATCH_SIZE = 6;

    /** @return Collection<int, Artwork> */
    public function category(string $slug, ?\App\Models\SiteSection $section = null): Collection
    {
        $query = $this->categoryQuery($slug, $section);
        if ($query === null) return new Collection;

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $query->get();
        $this->assertUniquePositions($artworks);

        return $artworks;
    }

    /**
     * Keep only compact identity/position data for deterministic Gallery
     * composition; expensive media relationships are loaded per batch.
     *
     * @return array{artworks:Collection<int, Artwork>, outline:Collection<int, Artwork>, hasMore:bool}
     */
    public function categoryBatch(string $slug, ?\App\Models\SiteSection $section = null, int $page = 1): array
    {
        $query = $this->categoryQuery($slug, $section);
        if ($query === null) {
            return ['artworks' => new Collection, 'outline' => new Collection, 'hasMore' => false];
        }

        /** @var Collection<int, Artwork> $outline */
        $outline = (clone $query)->withoutEagerLoads()->get(['artworks.id', 'artworks.position']);
        $this->assertUniquePositions($outline);

        /** @var Collection<int, Artwork> $rows */
        $rows = (clone $query)->offset((max(1, $page) - 1) * self::GALLERY_BATCH_SIZE)
            ->limit(self::GALLERY_BATCH_SIZE + 1)->get();

        return [
            'artworks' => $rows->take(self::GALLERY_BATCH_SIZE)->values(),
            'outline' => $outline,
            'hasMore' => $rows->count() > self::GALLERY_BATCH_SIZE,
        ];
    }

    /** @return Builder<Artwork>|null */
    private function categoryQuery(string $slug, ?\App\Models\SiteSection $section): ?Builder
    {
        /** @var ArtworkCategory|null $gallery */
        $gallery = ArtworkCategory::query()->where('slug', $slug)->first();
        if (! $gallery instanceof ArtworkCategory) return null;

        return GalleryPresentation::orderArtworks($this->publicQuery()
            ->whereHas('category.siteSection', function (Builder $query) use ($slug, $section): void {
                if ($section instanceof \App\Models\SiteSection) {
                    $query->whereKey($section->getKey());
                } else {
                    $query->where('slug', $slug);
                }
                if (! $this->preview->active()) {
                    $query->where('state', 'published');
                }
            }), $gallery);
    }

    /** @param Collection<int, Artwork> $artworks */
    private function assertUniquePositions(Collection $artworks): void
    {
        $positions = $artworks
            ->map(static fn (Artwork $artwork): int => (int) $artwork->getAttribute('position'))
            ->all();

        if (count($positions) !== count(array_unique($positions))) {
            throw new LogicException('Visible artwork positions must be unique within a Gallery.');
        }
    }

    /** @return Collection<int, Artwork> */
    public function heroCandidates(array $enabledGalleryIds, int $limit = 12): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->heroQuery($enabledGalleryIds)
            ->orderByRaw(ArtworkSort::DATE_DESC_SQL)
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /**
     * Return the bounded automatic Hero candidate group from the canonical Hero eligibility query.
     * Artwork rules sort by the Artwork's year, month and optional day.
     * Media Added rules sort by the primary MediaAsset upload timestamp.
     * Both use the same eligible-gallery query and deterministic tie-breakers.
     *
     * @return Collection<int, Artwork>
     */
    public function configuredHeroCandidates(
        array $enabledGalleryIds,
        int $groupSize = 12,
        string $sourceRule = 'newest_artwork',
        array $excludeIds = [],
    ): Collection {
        $query = $this->heroQuery($enabledGalleryIds);
        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }
        ArtworkSort::apply($query, $sourceRule);

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $query
            ->limit(max(1, min($groupSize, 50)))
            ->get();

        return $artworks;
    }

    /**
     * Up to the requested number of candidates from each eligible Gallery, using precisely the same
     * ArtworkSort ordering as Global sourcing. The SQL window operates on
     * the canonical eligible set, before the overall group cap, so a Gallery
     * with an excluded newest Artwork can still contribute its next newest.
     *
     * @return Collection<int, Artwork>
     */
    public function heroGalleryRepresentatives(
        array $enabledGalleryIds,
        string $sourceRule = 'newest_artwork',
        array $excludeIds = [],
        int $limit = 50,
        int $perGallery = 1,
    ): Collection {
        $ranked = $this->heroQuery($enabledGalleryIds)->select('artworks.id')
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY artworks.artwork_category_id ORDER BY '
                .ArtworkSort::orderClause($sourceRule).') AS gallery_rank',
            );
        if ($excludeIds !== []) {
            $ranked->whereNotIn('artworks.id', $excludeIds);
        }

        $representativeIds = DB::query()->fromSub($ranked->toBase(), 'ranked_hero_artworks')
            ->where('gallery_rank', '<=', max(1, min($perGallery, 50)))
            ->select('id');

        $query = $this->heroQuery($enabledGalleryIds)->whereIn('artworks.id', $representativeIds);
        ArtworkSort::apply($query, $sourceRule);

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $query->limit(max(1, min($limit, 12500)))->get();

        return $artworks;
    }

    /**
     * Number of eligible Galleries after exclusions, independent of the
     * candidate cap. Used to surface overflow rather than silently hiding it.
     */
    public function eligibleHeroGalleryCount(array $enabledGalleryIds, array $excludeIds = []): int
    {
        $query = $this->heroQuery($enabledGalleryIds);
        if ($excludeIds !== []) {
            $query->whereNotIn('artworks.id', $excludeIds);
        }

        return (int) $query->distinct()->count('artworks.artwork_category_id');
    }

    /** Count actual eligible per-Gallery slots before the public 50-item cap. */
    public function eligibleHeroCandidateSlotsPerGallery(array $enabledGalleryIds, int $perGallery, array $excludeIds = []): int
    {
        $query = $this->heroQuery($enabledGalleryIds)->withoutEagerLoads();
        if ($excludeIds !== []) {
            $query->whereNotIn('artworks.id', $excludeIds);
        }

        return (int) $query
            ->selectRaw('artworks.artwork_category_id, COUNT(*) AS gallery_candidates')
            ->groupBy('artworks.artwork_category_id')
            ->toBase()
            ->get()
            ->sum(static fn (object $row): int => min(max(1, min($perGallery, 50)), (int) $row->gallery_candidates));
    }

    /** @return Collection<int, Artwork> */
    public function newestHeroCandidates(array $enabledGalleryIds, ?int $latestYear = null): Collection
    {
        $latestYear ??= $this->heroQuery($enabledGalleryIds)->max('year');
        if ($latestYear === null) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->heroQuery($enabledGalleryIds)
            ->where('year', $latestYear)
            ->orderByRaw(ArtworkSort::DATE_DESC_SQL)
            ->get();

        return $artworks;
    }

    public function heroCandidateById(array $enabledGalleryIds, int $id): ?Artwork
    {
        if ($id <= 0) {
            return null;
        }

        return $this->heroQuery($enabledGalleryIds)->whereKey($id)->first();
    }

    /** @param list<int> $ids
     * @return Collection<int, Artwork>
     */
    public function heroCandidatesByIds(array $ids): Collection
    {
        $ids = $this->normalizedIds($ids);
        if ($ids === []) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->publicQuery()
            ->whereIn('id', $ids)
            ->get();

        return $artworks;
    }

    /**
     * Hero's public candidate search respects publication. The Dummy Gallery
     * editor has its own explicit Working selection, without altering public
     * visibility, Hero automatic eligibility or the published Hero resolver.
     *
     * @return Collection<int, Artwork>
     */
    public function searchHeroCandidates(array $enabledGalleryIds, string $search, int $limit = 30, bool $requireHeroGallery = true): Collection
    {
        return $this->searchCandidates(
            $requireHeroGallery ? $this->heroQuery($enabledGalleryIds) : $this->publicQuery(), $search, $limit,
        );
    }

    /** @return Collection<int, Artwork> */
    public function searchEditorialHeroCandidates(string $search, int $limit = 30): Collection
    {
        return $this->searchCandidates($this->editorialQuery(), $search, $limit);
    }

    /** True count of editorial Hero candidates, not a preloaded search window. */
    public function countEditorialHeroCandidates(string $search): int
    {
        return $this->candidateSearchQuery($this->editorialQuery()->withoutEagerLoads(), $search)->count();
    }

    /** @return Collection<int, Artwork> */
    public function pageEditorialHeroCandidates(string $search, int $offset, int $limit): Collection
    {
        return $this->searchCandidates($this->editorialQuery(), $search, $limit, $offset);
    }

    /** @return Collection<int, Artwork> */
    public function editorialHeroCandidatesByIds(array $ids): Collection
    {
        $ids = $this->normalizedIds($ids);
        if ($ids === []) {
            return new Collection;
        }

        return $this->editorialQuery()->whereIn('id', $ids)->get();
    }

    /** @return Collection<int, Artwork> */
    private function searchCandidates(Builder $query, string $search, int $limit, int $offset = 0): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->candidateSearchQuery($query, $search)
            ->orderByRaw(ArtworkSort::DATE_DESC_SQL)
            ->offset(max(0, $offset))
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /** One filter for initial results, exact total count and later pages. */
    private function candidateSearchQuery(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $needle = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $candidate) use ($needle): void {
            $candidate
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereHas('category', fn (Builder $gallery): Builder => $gallery->whereRaw('LOWER(name) LIKE ?', [$needle]));
        });
    }

    /** @return array{eligible:int,newest_year:?int,newest_year_candidates:int} */
    public function heroCandidateStatistics(array $enabledGalleryIds): array
    {
        $query = $this->heroQuery($enabledGalleryIds)->withoutEagerLoads();
        /** @var Artwork|null $summary */
        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS eligible_count')
            ->selectRaw('MAX(year) AS newest_year')
            ->first();
        $newestYear = $summary?->getAttribute('newest_year');
        $newestYear = is_numeric($newestYear) ? (int) $newestYear : null;

        return [
            'eligible' => (int) ($summary?->getAttribute('eligible_count') ?? 0),
            'newest_year' => $newestYear,
            'newest_year_candidates' => $newestYear === null
                ? 0
                : (clone $query)->where('year', $newestYear)->count(),
        ];
    }

    public function heroCandidateCount(array $enabledGalleryIds): int
    {
        return $this->heroQuery($enabledGalleryIds)->count();
    }

    public function publishedBySlug(string $slug): ?Artwork
    {
        return $this->detailQuery()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Resolve genuine LIVE Artwork detail links, not only publication flags.
     * Call under CommittedRead when used from an Admin Working context.
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    public function publicDetailSlugsForIds(array $ids): array
    {
        $ids = $this->normalizedIds($ids);
        if ($ids === []) {
            return [];
        }

        return $this->detailQuery()
            ->withoutEagerLoads()
            ->whereIn('artworks.id', $ids)
            ->where('artworks.state', 'published')
            ->whereHas('category.siteSection', static fn (Builder $section): Builder => $section->where('state', 'published'))
            // When detail pages are disabled, the old URL redirects to the Gallery.
            // That is not an independently openable public Artwork page.
            ->whereHas('category', static fn (Builder $category): Builder => $category
                ->where(static fn (Builder $pages): Builder => $pages
                    ->where('artwork_pages_enabled', true)
                    ->orWhereNull('artwork_pages_enabled')))
            ->pluck('artworks.slug', 'artworks.id')
            ->all();
    }

    /** @return Builder<Artwork> */
    private function detailQuery(): Builder
    {
        return $this->publicQuery()
            ->whereHas('category.siteSection', function (Builder $query): void {
                if (! $this->preview->active()) {
                    $query->where('state', 'published');
                }
            });
    }

    /** @param list<int> $enabledGalleryIds
     * @return Builder<Artwork>
     */
    private function heroQuery(array $enabledGalleryIds): Builder
    {
        return $this->publicQuery()
            ->whereIn('artworks.artwork_category_id', $enabledGalleryIds)
            ->whereHas('category.siteSection',
                static fn (Builder $section): Builder => $section->where('state', 'published'))
            ->whereNotNull('year');
    }

    /** @return Builder<Artwork> */
    private function editorialQuery(): Builder
    {
        return $this->availablePresentationQuery()->where('state', '<>', 'archived');
    }

    /** @return Builder<Artwork> */
    private function publicQuery(): Builder
    {
        $query = $this->availablePresentationQuery();
        if ($this->preview->active()) {
            $query->where('state', '<>', 'archived');
        } else {
            $query->where('state', 'published');
        }

        return $query;
    }

    /** @return Builder<Artwork> */
    private function availablePresentationQuery(): Builder
    {
        return Artwork::query()
            ->with(['category.siteSection', 'artworkMedia.mediaAsset.variants'])
            // This read model drives public AND preview artwork cards. Neither
            // can render an incomplete draft without one viable primary asset.
            // Keep such drafts editable in the Gallery workspace instead.
            ->whereHas('artworkMedia', fn (Builder $usages) => $usages->where('role', 'primary'), '=', 1)
            ->whereHas('artworkMedia', function (Builder $usages): void {
                $usages->where('role', 'primary')
                    ->where(function (Builder $text): void {
                        $text->whereRaw("NULLIF(BTRIM(alt_text_override), '') IS NOT NULL")
                            ->orWhereHas('mediaAsset', fn (Builder $asset) => $asset
                                ->whereRaw("NULLIF(BTRIM(alt_text), '') IS NOT NULL"));
                    })
                    ->whereHas('mediaAsset', function (Builder $asset): void {
                        $asset->where('state', 'available')
                            ->where(function (Builder $media): void {
                                $media->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES)
                                    ->orWhere(function (Builder $images): void {
                                        $images->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES)
                                            ->whereHas('variants', fn (Builder $variants) => $variants
                                                ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
                                                ->where('mime_type', 'image/webp')
                                                ->where('state', 'available'), '=', 1);
                                    });
                            });
                    });
            });
    }

    /** @param list<mixed> $ids
     * @return list<int>
     */
    private function normalizedIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_int($id) && $id > 0) {
                $normalized[] = $id;
            } elseif (is_numeric($id) && (int) $id > 0) {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }
}
