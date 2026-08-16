<?php

namespace App\Domain\Artwork;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PublicArtworkQuery
{
    /** @return Collection<int, Artwork> */
    public function category(string $slug): Collection
    {
        return $this->publicQuery()
            ->whereHas('category', fn (Builder $query) => $query
                ->where('slug', $slug)
                ->where('state', 'published'))
            ->orderByRaw('work_date DESC NULLS LAST')
            ->orderBy('position')
            ->get();
    }

    public function latestForHome(): ?Artwork
    {
        /** @var Artwork|null $artwork */
        $artwork = $this->publicQuery()
            ->whereHas('category', fn (Builder $query) => $query
                ->whereIn('slug', ['paintings', 'drawings', 'prints'])
                ->where('state', 'published'))
            ->orderByRaw('work_date DESC NULLS LAST')
            ->orderBy('position')
            ->first();

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
    private function publicQuery(): Builder
    {
        return Artwork::query()
            ->where('state', 'published')
            ->with(['category', 'artworkMedia.mediaAsset.variants']);
    }
}
