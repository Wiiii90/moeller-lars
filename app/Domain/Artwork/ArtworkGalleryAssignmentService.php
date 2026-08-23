<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\SiteNodeType;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkGalleryAssignmentService
{
    public function __construct(
        private readonly AdminAuditService $adminAuditService,
    ) {}

    public function reassign(Artwork $artwork, ArtworkCategory $destination): Artwork
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($artwork, $destination, $actor): Artwork {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()
                ->whereKey($artwork->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $sourceCategoryId = $lockedArtwork->getAttribute('artwork_category_id');
            $sourceCategoryId = $sourceCategoryId === null ? null : (int) $sourceCategoryId;
            $destinationCategoryId = (int) $destination->getKey();

            if ($sourceCategoryId === $destinationCategoryId) {
                return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset']);
            }

            $categoryIds = array_values(array_unique(array_filter([
                $sourceCategoryId,
                $destinationCategoryId,
            ], static fn (?int $id): bool => $id !== null)));
            sort($categoryIds);

            $lockedCategoryCount = ArtworkCategory::query()
                ->whereIn('id', $categoryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->count();

            if ($lockedCategoryCount !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'gallery' => 'The source or destination Gallery is no longer available.',
                ]);
            }

            if ($lockedArtwork->getAttribute('state') === 'published') {
                /** @var SiteSection|null $destinationSection */
                $destinationSection = SiteSection::query()
                    ->where('type', SiteNodeType::Gallery->value)
                    ->where('artwork_category_id', $destinationCategoryId)
                    ->lockForUpdate()
                    ->first();

                if (! $destinationSection || $destinationSection->getAttribute('state') !== 'published') {
                    throw ValidationException::withMessages([
                        'gallery' => 'Published artwork can only move to a published Gallery.',
                    ]);
                }
            }

            $destinationMaxPosition = Artwork::query()
                ->where('artwork_category_id', $destinationCategoryId)
                ->max('position');

            $lockedArtwork->forceFill([
                'artwork_category_id' => $destinationCategoryId,
                'position' => $destinationMaxPosition === null ? 0 : ((int) $destinationMaxPosition) + 1,
            ])->save();

            if ($sourceCategoryId !== null) {
                $this->normalizeGalleryPositions($sourceCategoryId);
            }
            $this->normalizeGalleryPositions($destinationCategoryId);

            $this->adminAuditService->record(
                $actor,
                'artwork.updated',
                'artwork',
                $lockedArtwork->getKey(),
            );

            return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset']);
        });
    }

    public function detach(Artwork $artwork): Artwork
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($artwork, $actor): Artwork {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()
                ->whereKey($artwork->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $sourceCategoryId = $lockedArtwork->getAttribute('artwork_category_id');
            if ($sourceCategoryId === null) {
                return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset']);
            }

            if ($lockedArtwork->getAttribute('state') === 'published') {
                throw ValidationException::withMessages([
                    'gallery' => 'Unpublish the artwork before removing it from its Gallery.',
                ]);
            }

            $sourceCategoryId = (int) $sourceCategoryId;
            ArtworkCategory::query()
                ->whereKey($sourceCategoryId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedArtwork->forceFill([
                'artwork_category_id' => null,
                'position' => 0,
            ])->save();

            $this->normalizeGalleryPositions($sourceCategoryId);

            $this->adminAuditService->record(
                $actor,
                'artwork.updated',
                'artwork',
                $lockedArtwork->getKey(),
            );

            return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset']);
        });
    }

    private function normalizeGalleryPositions(int $categoryId): void
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = Artwork::query()
            ->where('artwork_category_id', $categoryId)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($artworks->values() as $position => $artwork) {
            if ((int) $artwork->getAttribute('position') === $position) {
                continue;
            }

            $artwork->setAttribute('position', $position);
            $artwork->save();
        }
    }
}
