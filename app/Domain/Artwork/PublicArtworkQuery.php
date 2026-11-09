<?php

namespace App\Domain\Artwork;

use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PublicArtworkQuery
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    /** @return Collection<int, Artwork> */
    public function category(string $slug): Collection
    {
        /** @var ArtworkCategory|null $gallery */
        $gallery = ArtworkCategory::query()->where('slug', $slug)->first();
        if (! $gallery instanceof ArtworkCategory) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = GalleryPresentation::orderArtworks($this->publicQuery()
            ->whereHas('category.siteSection', function (Builder $query) use ($slug): void {
                $query->where('slug', $slug);
                if (! $this->preview->active()) {
                    $query->where('state', 'published');
                }
            }), $gallery)
            ->get();

        $positions = $artworks
            ->map(static fn (Artwork $artwork): int => (int) $artwork->getAttribute('position'))
            ->all();

        if (count($positions) !== count(array_unique($positions))) {
            throw new LogicException('Visible artwork positions must be unique within a Gallery.');
        }

        return $artworks;
    }

    /** @return Collection<int, Artwork> */
    public function homeCandidates(int $limit = 12): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->orderByRaw(GalleryPresentation::DATE_ORDER_SQL)
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /**
     * Return the bounded automatic Home candidate group from the canonical Home eligibility query.
     * Artwork date ordering uses the Artwork's year, month and optional day; Added ordering uses Artwork created_at.
     * MediaAsset timestamps never participate in either ordering.
     *
     * @return Collection<int, Artwork>
     */
    public function configuredHomeCandidates(
        int $groupSize = 12,
        string $newestBy = 'artwork_date',
    ): Collection {
        $query = $this->homeQuery();
        $this->applyConfiguredHomeOrdering($query, $newestBy);

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $query
            ->limit(max(1, min($groupSize, 50)))
            ->get();

        return $artworks;
    }

    /** @return Collection<int, Artwork> */
    public function newestHomeCandidates(?int $latestYear = null): Collection
    {
        $latestYear ??= $this->homeQuery()->max('year');
        if ($latestYear === null) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->where('year', $latestYear)
            ->orderByRaw(GalleryPresentation::DATE_ORDER_SQL)
            ->get();

        return $artworks;
    }

    public function homeCandidateById(int $id): ?Artwork
    {
        if ($id <= 0) {
            return null;
        }

        return $this->homeQuery()->whereKey($id)->first();
    }

    /** @param list<int> $ids
     * @return Collection<int, Artwork>
     */
    public function homeCandidatesByIds(array $ids): Collection
    {
        $ids = $this->normalizedIds($ids);
        if ($ids === []) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->whereIn('id', $ids)
            ->get();

        return $artworks;
    }

    /** @return Collection<int, Artwork> */
    public function searchHomeCandidates(string $search, int $limit = 30): Collection
    {
        $query = $this->homeQuery();
        $term = trim($search);

        if ($term !== '') {
            $needle = '%'.mb_strtolower($term).'%';
            $query->where(function (Builder $candidate) use ($needle): void {
                $candidate
                    ->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereHas('category', fn (Builder $gallery): Builder => $gallery->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $query
            ->orderByRaw(GalleryPresentation::DATE_ORDER_SQL)
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /** @return array{eligible:int,newest_year:?int,newest_year_candidates:int} */
    public function homeCandidateStatistics(): array
    {
        $query = $this->homeQuery()->withoutEagerLoads();
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

    public function homeCandidateCount(): int
    {
        return $this->homeQuery()->count();
    }

    public function publishedBySlug(string $slug): ?Artwork
    {
        return $this->publicQuery()
            ->where('slug', $slug)
            ->whereHas('category.siteSection', function (Builder $query): void {
                if (! $this->preview->active()) {
                    $query->where('state', 'published');
                }
            })
            ->first();
    }

    /** @param Builder<Artwork> $query */
    private function applyConfiguredHomeOrdering(Builder $query, string $newestBy): void
    {
        if ($newestBy === 'added') {
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            return;
        }

        GalleryPresentation::orderArtworksByDate($query);
    }

    /** @return Builder<Artwork> */
    private function homeQuery(): Builder
    {
        /** @var Builder<Artwork> $query */
        $query = $this->publicQuery();

        /** @var Builder<Artwork> $result */
        $result = $query
            ->where('exclude_from_home', false)
            ->whereHas('category', function (Builder $query): void {
                $query->where('show_on_home', true)
                    ->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'));
            })
            ->whereNotNull('year');

        return $result;
    }

    /** @return Builder<Artwork> */
    private function publicQuery(): Builder
    {
        $query = Artwork::query()
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

        if ($this->preview->active()) {
            $query->where('state', '<>', 'archived');
        } else {
            $query->where('state', 'published');
        }

        return $query;
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
