<?php

namespace App\Domain\Artwork;

use App\Domain\Content\SitePreviewContext;
use App\Models\Artwork;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublicArtworkQuery
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    /** @return Collection<int, Artwork> */
    public function category(string $slug): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->publicQuery()
            ->whereHas('category.siteSection', function (Builder $query) use ($slug): void {
                $query->where('slug', $slug);
                if (! $this->preview->active()) {
                    $query->where('state', 'published');
                }
            })
            ->orderBy('position')
            ->get();

        $positions = $artworks
            ->map(static fn (Artwork $artwork): int => (int) $artwork->getAttribute('position'))
            ->all();

        if (count($positions) !== count(array_unique($positions))) {
            throw new LogicException('Visible artwork positions must be unique within a Gallery.');
        }

        return $artworks;
    }

    public function latestForHome(): ?Artwork
    {
        $latestYear = $this->homeQuery()->max('work_year');
        if ($latestYear === null) {
            return null;
        }

        /** @var Collection<int, Artwork> $candidates */
        $candidates = $this->homeQuery()->where('work_year', $latestYear)->get();
        if ($candidates->count() === 1) {
            /** @var Artwork $artwork */
            $artwork = $candidates->first();

            return $artwork;
        }

        $exactDates = $candidates->map(
            static function (Artwork $artwork): ?string {
                $date = $artwork->getAttribute('work_date');

                return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : null;
            },
        );

        if ($exactDates->contains(null)) {
            return $this->explicitHomeSelection(
                $candidates,
                'The newest eligible home year contains artwork without an exact comparable date.',
            );
        }

        /** @var string $latestDate */
        $latestDate = $exactDates->max();
        $latest = $candidates->filter(
            static function (Artwork $artwork) use ($latestDate): bool {
                $date = $artwork->getAttribute('work_date');

                return $date instanceof DateTimeInterface && $date->format('Y-m-d') === $latestDate;
            },
        )->values();

        if ($latest->count() === 1) {
            /** @var Artwork $artwork */
            $artwork = $latest->first();

            return $artwork;
        }

        return $this->explicitHomeSelection(
            $latest,
            "The newest eligible home date {$latestDate} is ambiguous.",
        );
    }

    /** @return Collection<int, Artwork> */
    public function homeCandidates(int $limit = 12): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->orderByDesc('work_year')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /**
     * Return the bounded automatic Home candidate group from the canonical Home eligibility query.
     * Artwork date ordering uses the Artwork's work year/date; Added ordering uses Artwork created_at.
     * MediaAsset timestamps never participate in either ordering.
     *
     * @param list<int> $manualIncludeIds
     * @return Collection<int, Artwork>
     */
    public function configuredHomeCandidates(
        int $groupSize = 12,
        string $newestBy = 'artwork_date',
        string $filter = 'all',
        ?int $year = null,
        array $manualIncludeIds = [],
    ): Collection {
        $query = $this->configuredHomeCandidateQuery($filter, $year, $manualIncludeIds);
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
        $latestYear ??= $this->homeQuery()->max('work_year');
        if ($latestYear === null) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->where('work_year', $latestYear)
            ->orderByDesc('work_date')
            ->orderByDesc('id')
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
     *  @return Collection<int, Artwork>
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
            ->orderByDesc('work_year')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /**
     * The configured Hero candidate pool always starts from the canonical Home eligibility query.
     * `newest` includes the newest eligible year, `year` includes the chosen eligible year,
     * and manual IDs may add other Home-eligible artworks.
     *
     * @param list<int> $manualIncludeIds
     * @return Collection<int, Artwork>
     */
    public function homePoolCandidates(
        string $rule = 'newest',
        ?int $year = null,
        array $manualIncludeIds = [],
        int $limit = 12,
    ): Collection {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homePoolQuery($rule, $year, $manualIncludeIds)
            ->orderByDesc('work_year')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $artworks;
    }

    /** @param list<int> $manualIncludeIds */
    public function homePoolCandidateCount(
        string $rule = 'newest',
        ?int $year = null,
        array $manualIncludeIds = [],
    ): int {
        return $this->homePoolQuery($rule, $year, $manualIncludeIds)
            ->withoutEagerLoads()
            ->count();
    }

    /** @param list<int> $manualIncludeIds */
    public function randomForHomePool(
        string $rule = 'newest',
        ?int $year = null,
        array $manualIncludeIds = [],
    ): ?Artwork {
        $query = $this->homePoolQuery($rule, $year, $manualIncludeIds);
        $count = (clone $query)->withoutEagerLoads()->count();
        if ($count < 1) {
            return null;
        }

        return $query
            ->orderBy('id')
            ->offset(random_int(0, $count - 1))
            ->first();
    }

    /**
     * Candidate previews for visible Gallery rows are selected from the same configured pool
     * in one bounded partitioned query instead of one query per Gallery.
     *
     * @param list<int> $galleryIds
     * @param list<int> $manualIncludeIds
     * @return Collection<int, Artwork>
     */
    public function homePoolCandidatesForGalleries(
        array $galleryIds,
        string $rule = 'newest',
        ?int $year = null,
        array $manualIncludeIds = [],
        int $perGallery = 5,
    ): Collection {
        $galleryIds = $this->normalizedIds($galleryIds);
        $perGallery = max(1, min($perGallery, 5));
        if ($galleryIds === []) {
            return new Collection;
        }

        $ranked = $this->homePoolQuery($rule, $year, $manualIncludeIds)
            ->withoutEagerLoads()
            ->whereIn('artwork_category_id', $galleryIds)
            ->select(['artworks.id', 'artworks.artwork_category_id'])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY artwork_category_id ORDER BY work_year DESC, work_date DESC, id DESC) AS home_candidate_rank',
            );

        $ids = DB::query()
            ->fromSub($ranked, 'ranked_home_candidates')
            ->where('home_candidate_rank', '<=', $perGallery)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return new Collection;
        }

        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->homeQuery()
            ->whereIn('id', $ids)
            ->orderByDesc('work_year')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->get();

        return $artworks;
    }

    /** @return array{eligible:int,newest_year:?int,newest_year_candidates:int,explicit_tie_breakers:int} */
    public function homeCandidateStatistics(): array
    {
        $query = $this->homeQuery()->withoutEagerLoads();
        /** @var Artwork|null $summary */
        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS eligible_count')
            ->selectRaw('MAX(work_year) AS newest_year')
            ->selectRaw('SUM(CASE WHEN featured_on_home THEN 1 ELSE 0 END) AS explicit_tie_breakers')
            ->first();
        $newestYear = $summary?->getAttribute('newest_year');
        $newestYear = is_numeric($newestYear) ? (int) $newestYear : null;

        return [
            'eligible' => (int) ($summary?->getAttribute('eligible_count') ?? 0),
            'newest_year' => $newestYear,
            'newest_year_candidates' => $newestYear === null
                ? 0
                : (clone $query)->where('work_year', $newestYear)->count(),
            'explicit_tie_breakers' => (int) ($summary?->getAttribute('explicit_tie_breakers') ?? 0),
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

    /** @param Collection<int, Artwork> $candidates */
    private function explicitHomeSelection(Collection $candidates, string $message): Artwork
    {
        $featured = $candidates->filter(
            static fn (Artwork $artwork): bool => (bool) $artwork->getAttribute('featured_on_home'),
        )->values();

        if ($featured->count() !== 1) {
            throw new LogicException($message.' Exactly one explicit featured_on_home selection is required.');
        }

        /** @var Artwork $artwork */
        $artwork = $featured->first();

        return $artwork;
    }

    /**
     * @param list<int> $manualIncludeIds
     * @return Builder<Artwork>
     */
    private function configuredHomeCandidateQuery(string $filter, ?int $year, array $manualIncludeIds): Builder
    {
        $query = $this->homeQuery();
        if ($filter !== 'year') {
            return $query;
        }

        $manualIncludeIds = $this->normalizedIds($manualIncludeIds);

        return $query->where(function (Builder $candidates) use ($year, $manualIncludeIds): void {
            if ($year !== null) {
                $candidates->where('work_year', $year);
            } else {
                $candidates->whereRaw('1 = 0');
            }

            if ($manualIncludeIds !== []) {
                $candidates->orWhereIn('id', $manualIncludeIds);
            }
        });
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

        $query
            ->orderByDesc('work_year')
            ->orderByRaw('CASE WHEN work_date IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('work_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @param list<int> $manualIncludeIds
     * @return Builder<Artwork>
     */
    private function homePoolQuery(string $rule, ?int $year, array $manualIncludeIds): Builder
    {
        $manualIncludeIds = $this->normalizedIds($manualIncludeIds);
        $query = $this->homeQuery();

        if ($rule === 'year') {
            return $query->where(function (Builder $candidates) use ($year, $manualIncludeIds): void {
                if ($year !== null) {
                    $candidates->where('work_year', $year);
                } else {
                    $candidates->whereRaw('1 = 0');
                }

                if ($manualIncludeIds !== []) {
                    $candidates->orWhereIn('id', $manualIncludeIds);
                }
            });
        }

        $latestYear = (clone $query)->withoutEagerLoads()->max('work_year');

        return $query->where(function (Builder $candidates) use ($latestYear, $manualIncludeIds): void {
            if ($latestYear !== null) {
                $candidates->where('work_year', $latestYear);
            } else {
                $candidates->whereRaw('1 = 0');
            }

            if ($manualIncludeIds !== []) {
                $candidates->orWhereIn('id', $manualIncludeIds);
            }
        });
    }

    /** @return Builder<Artwork> */
    private function homeQuery(): Builder
    {
        /** @var Builder<Artwork> $query */
        $query = $this->publicQuery();

        /** @var Builder<Artwork> $result */
        $result = $query
            ->whereHas('category', function (Builder $query): void {
                $query->where('show_on_home', true)
                    ->whereHas('siteSection', function (Builder $section): void {
                        if (! $this->preview->active()) {
                            $section->where('state', 'published');
                        }
                    });
            })
            ->whereNotNull('work_year');

        return $result;
    }

    /** @return Builder<Artwork> */
    private function publicQuery(): Builder
    {
        $query = Artwork::query()
            ->with(['category.siteSection', 'artworkMedia.mediaAsset.variants']);

        if ($this->preview->active()) {
            $query->where('state', '<>', 'archived');
        } else {
            $query->where('state', 'published');
        }

        return $query;
    }

    /** @param list<mixed> $ids
     *  @return list<int>
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
