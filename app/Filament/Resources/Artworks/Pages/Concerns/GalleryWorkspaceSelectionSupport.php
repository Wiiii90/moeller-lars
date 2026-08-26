<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\GalleryEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceSelectionSupport
{
    private function actionArtwork(array $arguments): Artwork
    {
        $id = $arguments['artwork'] ?? null;
        abort_unless(is_numeric($id), 404);

        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()
            ->whereKey((int) $id)
            ->where('artwork_category_id', (int) $this->galleryContext['id'])
            ->first();
        abort_unless($artwork instanceof Artwork, 404);

        return $artwork;
    }

    /** @return EloquentCollection<int, Artwork> */
    private function selectedArtworks(): EloquentCollection
    {
        $ids = array_keys($this->selectedArtworkIdSet());
        if ($ids === []) {
            throw ValidationException::withMessages(['artworks' => 'Select at least one artwork.']);
        }

        /** @var EloquentCollection<int, Artwork> $artworks */
        $artworks = Artwork::query()
            ->where('artwork_category_id', (int) $this->galleryContext['id'])
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($artworks->count() !== count($ids)) {
            throw ValidationException::withMessages(['artworks' => 'The selection changed. Review it and try again.']);
        }

        return $artworks;
    }

    /** @return array<int, true> */
    private function selectedArtworkIdSet(): array
    {
        $ids = collect($this->selectedArtworkIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return array_fill_keys($ids, true);
    }

    /** @return list<int> */
    private function orderedArtworkIds(): array
    {
        return Artwork::query()
            ->where('artwork_category_id', (int) $this->galleryContext['id'])
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $orderedIds */
    private function saveArtworkOrder(array $orderedIds): void
    {
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        app(GalleryEditorialService::class)->reorderArtworks($gallery, $orderedIds);
    }

    private function clearSelection(): void
    {
        $this->selectedArtworkIds = [];
    }

    private function loadGallery(int $galleryId): void
    {
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail($galleryId);
        /** @var SiteSection $section */
        $section = $gallery->siteSection()->firstOrFail();

        $this->galleryContext = [
            'id' => (int) $gallery->getKey(),
            'name' => (string) $gallery->getAttribute('name'),
            'slug' => (string) $gallery->getAttribute('slug'),
            'state' => (string) $section->getAttribute('state'),
            'path' => '/'.ltrim((string) $gallery->getAttribute('slug'), '/'),
            'public_url' => $section->getAttribute('state') === 'published'
                ? route('site.section', ['section' => $gallery->getAttribute('slug')])
                : null,
        ];
    }

    private function loadMoveTargets(): void
    {
        /** @var EloquentCollection<int, ArtworkCategory> $galleries */
        $galleries = ArtworkCategory::query()
            ->where('id', '<>', (int) $this->galleryContext['id'])
            ->whereHas('siteSection')
            ->with('siteSection')
            ->orderBy('name')
            ->get();

        $this->moveTargets = $galleries->map(static function (ArtworkCategory $gallery): array {
            /** @var SiteSection|null $section */
            $section = $gallery->getRelationValue('siteSection');

            return [
                'id' => (int) $gallery->getKey(),
                'name' => (string) $gallery->getAttribute('name'),
                'state' => (string) ($section?->getAttribute('state') ?? 'hidden'),
            ];
        })->values()->all();
    }
}
