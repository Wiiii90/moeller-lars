<?php

namespace App\Domain\Artwork;

use App\Domain\Content\SitePreviewContext;
use App\Models\Artwork;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            throw new LogicException('Visible artwork positions must be unique within a category.');
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
}
