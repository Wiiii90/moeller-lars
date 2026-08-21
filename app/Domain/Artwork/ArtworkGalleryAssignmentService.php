<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
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

            $sourceCategoryId = (int) $lockedArtwork->getAttribute('artwork_category_id');
            $destinationCategoryId = (int) $destination->getKey();

            if ($sourceCategoryId === $destinationCategoryId) {
                return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset']);
            }

            $categoryIds = [$sourceCategoryId, $destinationCategoryId];
            sort($categoryIds);

            $lockedCategoryCount = ArtworkCategory::query()
                ->whereIn('id', $categoryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->count();

            if ($lockedCategoryCount !== 2) {
                throw ValidationException::withMessages([
                    'gallery' => 'The source or destination Gallery is no longer available.',
                ]);
            }

            if ($lockedArtwork->getAttribute('state') === 'published') {
                /** @var SiteSection|null $destinationSection */
                $destinationSection = SiteSection::query()
                    ->where('type', SiteSection::TYPE_GALLERY)
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

            $this->normalizeGalleryPositions($sourceCategoryId);
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
