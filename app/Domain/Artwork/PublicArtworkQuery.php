<?php

namespace App\Domain\Artwork;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PublicArtworkQuery
{
    /** @return Collection<int, Artwork> */
    public function category(string $slug): Collection
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->publicQuery()
            ->whereHas('category', fn (Builder $query) => $query
                ->where('slug', $slug)
                ->where('state', 'published'))
            ->orderBy('position')
            ->get();

        $positions = $artworks
            ->map(static fn (Artwork $artwork): int => (int) $artwork->getAttribute('position'))
            ->all();

        if (count($positions) !== count(array_unique($positions))) {
            throw new LogicException('Published artwork positions must be unique within a category.');
        }

        return $artworks;
    }

    public function latestForHome(): ?Artwork
    {
        $latestDate = $this->homeQuery()->max('work_date');
        if ($latestDate === null) {
            return null;
        }

        /** @var Collection<int, Artwork> $candidates */
        $candidates = $this->homeQuery()->whereDate('work_date', $latestDate)->get();
        if ($candidates->count() !== 1) {
            throw new LogicException('The newest eligible home artwork is ambiguous.');
        }

        /** @var Artwork $artwork */
        $artwork = $candidates->first();

        return $artwork;
    }

    public function publishedBySlug(string $slug): ?Artwork
    {
        return $this->publicQuery()
            ->where('slug', $slug)
            ->whereHas('category', fn (Builder $query) => $query->where('state', 'published'))
            ->first();
    }

    /** @return Builder<Artwork> */
    private function homeQuery(): Builder
    {
        /** @var Builder<Artwork> $query */
        $query = $this->publicQuery();

        /** @var Builder<Artwork> $result */
        $result = $query
            ->whereHas('category', fn (Builder $query) => $query
                ->where('state', 'published')
                ->where('show_on_home', true))
            ->whereNotNull('work_date');

        return $result;
    }

    /** @return Builder<Artwork> */
    private function publicQuery(): Builder
    {
        return Artwork::query()
            ->where('state', 'published')
            ->with(['category', 'artworkMedia.mediaAsset.variants']);
    }
}
