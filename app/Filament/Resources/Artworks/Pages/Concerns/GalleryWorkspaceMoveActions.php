<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkSelectionOrder;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

trait GalleryWorkspaceMoveActions
{
    public function moveArtwork(int $artworkId, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        $orderedIds = $this->orderedArtworkIds();
        $index = array_search($artworkId, $orderedIds, true);
        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($targetIndex, $orderedIds)) {
            return;
        }

        [$orderedIds[$index], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$index]];
        $this->saveArtworkOrder($orderedIds);
        Notification::make()->title('Gallery order updated')->success()->send();
        $this->loadArtworks();
    }

    public function moveSelectedArtworks(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        $selectedIds = array_keys($this->selectedArtworkIdSet());
        if ($selectedIds === []) {
            Notification::make()->title('Select artworks first')->warning()->send();

            return;
        }

        $orderedIds = ArtworkSelectionOrder::moveOneSlot($this->orderedArtworkIds(), $selectedIds, $direction);
        $this->saveArtworkOrder($orderedIds);
        $this->loadArtworks();
        Notification::make()->title('Selected artworks reordered')->success()->send();
    }

    public function reassignArtwork(int $artworkId): void
    {
        $targetGalleryId = (int) ($this->moveTargetGalleryIds[$artworkId] ?? 0);
        if ($targetGalleryId <= 0) {
            Notification::make()->title('Choose a destination Gallery')->warning()->send();

            return;
        }

        $galleryId = (int) $this->galleryContext['id'];
        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()->whereKey($artworkId)->where('artwork_category_id', $galleryId)->first();
        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()->whereKey($targetGalleryId)->where('id', '<>', $galleryId)->first();

        if (! $artwork || ! $destination) {
            Notification::make()->title('Artwork could not be moved')->danger()->send();

            return;
        }

        try {
            app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Artwork could not be moved', $exception);

            return;
        }

        unset($this->moveTargetGalleryIds[$artworkId]);
        $this->selectedArtworkIds = array_values(array_filter(
            $this->selectedArtworkIds,
            static fn (int|string $id): bool => (int) $id !== $artworkId,
        ));

        $this->loadArtworks();
        Notification::make()->title('Artwork moved')->body('Media references were preserved.')->success()->send();
    }

    public function reassignSelectedArtworks(): void
    {
        $targetGalleryId = (int) $this->batchTargetGalleryId;
        $galleryId = (int) $this->galleryContext['id'];
        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()->whereKey($targetGalleryId)->where('id', '<>', $galleryId)->first();
        if (! $destination) {
            Notification::make()->title('Choose a destination Gallery')->warning()->send();

            return;
        }

        try {
            $artworks = $this->selectedArtworks();
            DB::transaction(function () use ($artworks, $destination): void {
                foreach ($artworks as $artwork) {
                    app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination);
                }
            });
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Selected artworks could not be moved', $exception);

            return;
        }

        $count = $artworks->count();
        $this->clearSelection();
        $this->loadArtworks();
        Notification::make()->title($count === 1 ? 'Artwork moved' : $count.' artworks moved')->body('Media references remain shared and unchanged.')->success()->send();
    }
}
