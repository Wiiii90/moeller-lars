<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkSelectionOrder;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

        if (! $this->artworkReorderingAvailable()) {
            Notification::make()->title('Clear filters to reorder')->warning()->send();

            return;
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
        $this->refreshWorkspaceAfterMutation();
        Notification::make()->title('Gallery order updated')->success()->send();
    }

    public function reorderArtworks(array $orderedIds): void
    {
        if (! $this->artworkReorderingAvailable()) {
            Notification::make()->title('Clear filters to reorder')->warning()->send();

            return;
        }

        $normalized = [];
        foreach ($orderedIds as $id) {
            if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
                $this->notifyValidationFailure(
                    'Gallery order was not updated',
                    ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']),
                );

                return;
            }

            $normalized[] = (int) $id;
        }

        $current = $this->orderedArtworkIds();
        $expected = $current;
        $actual = $normalized;
        sort($expected);
        sort($actual);

        if (
            count($normalized) !== count($current)
            || count(array_unique($normalized)) !== count($normalized)
            || $actual !== $expected
        ) {
            $this->notifyValidationFailure(
                'Gallery order was not updated',
                ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']),
            );

            return;
        }

        if ($normalized === $current) {
            return;
        }

        try {
            $this->saveArtworkOrder($normalized);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Gallery order was not updated', $exception);

            return;
        }

        $this->refreshWorkspaceAfterMutation();
        Notification::make()->title('Gallery order updated')->success()->send();
    }

    public function moveSelectedArtworks(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        if (! $this->artworkReorderingAvailable()) {
            Notification::make()->title('Clear filters to reorder')->warning()->send();

            return;
        }

        $selectedIds = array_keys($this->selectedArtworkIdSet());
        if ($selectedIds === []) {
            Notification::make()->title('Select artworks first')->warning()->send();

            return;
        }

        $orderedIds = ArtworkSelectionOrder::moveOneSlot($this->orderedArtworkIds(), $selectedIds, $direction);
        $this->saveArtworkOrder($orderedIds);
        $this->refreshWorkspaceAfterMutation();
        Notification::make()->title('Selected artworks reordered')->success()->send();
    }

    public function moveArtworkToGalleryAction(): Action
    {
        return Action::make('moveArtworkToGallery')
            ->label('Move to Gallery')
            ->modalHeading(fn (array $arguments): string => 'Move '.$this->actionArtwork($arguments)->getAttribute('title'))
            ->schema([
                Select::make('target_gallery_id')
                    ->label('Destination Gallery')
                    ->options(fn (): array => collect($this->moveTargets)->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->reassignArtworkTo((int) ($arguments['artwork'] ?? 0), (int) ($data['target_gallery_id'] ?? 0));
            });
    }

    public function moveSelectedToGalleryAction(): Action
    {
        return Action::make('moveSelectedToGallery')
            ->label('Move to Gallery')
            ->modalHeading('Move selected artworks')
            ->schema([
                Select::make('target_gallery_id')
                    ->label('Destination Gallery')
                    ->options(fn (): array => collect($this->moveTargets)->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->reassignSelectedArtworksTo((int) ($data['target_gallery_id'] ?? 0));
            });
    }

    private function reassignArtworkTo(int $artworkId, int $targetGalleryId): void
    {
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

        $this->selectedArtworkIds = array_values(array_filter(
            $this->selectedArtworkIds,
            static fn (int|string $id): bool => (int) $id !== $artworkId,
        ));

        $this->refreshWorkspaceAfterMutation();
        Notification::make()->title('Artwork moved')->body('Media references were preserved.')->success()->send();
    }

    private function reassignSelectedArtworksTo(int $targetGalleryId): void
    {
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
        $this->refreshWorkspaceAfterMutation();
        Notification::make()->title($count === 1 ? 'Artwork moved' : $count.' artworks moved')->body('Media references remain shared and unchanged.')->success()->send();
    }

    private function artworkReorderingAvailable(): bool
    {
        return trim($this->search) === ''
            && $this->statusFilter === 'any'
            && $this->readinessFilter === 'any';
    }
}
