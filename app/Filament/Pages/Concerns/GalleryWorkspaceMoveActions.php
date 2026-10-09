<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkSelectionOrder;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
            app(AdminNotifier::class)->notification(
                title: 'Clear filters to reorder',
                status: 'warning',
            );

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
        app(AdminNotifier::class)->notification(
            title: 'Gallery order updated',
            status: 'success',
        );
    }

    public function sortArtwork(int|string $artworkId, int|string $position): void
    {
        if (! $this->artworkReorderingAvailable()) {
            return;
        }

        if (
            (! is_int($artworkId) && ! ctype_digit($artworkId))
            || (! is_int($position) && ! ctype_digit($position))
        ) {
            return;
        }

        $artworkId = (int) $artworkId;
        $position = (int) $position;
        $orderedIds = $this->orderedArtworkIds();
        $from = array_search($artworkId, $orderedIds, true);

        if ($from === false || $position < 0 || $position >= count($orderedIds) || $from === $position) {
            return;
        }

        $next = $orderedIds;
        array_splice($next, $from, 1);
        array_splice($next, $position, 0, [$artworkId]);

        try {
            $this->saveArtworkOrder($next);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Gallery order was not updated', $exception);

            return;
        }

        $this->refreshWorkspaceAfterMutation();
        app(AdminNotifier::class)->notification(
            title: 'Gallery order updated',
            status: 'success',
        );
    }

    public function moveSelectedArtworks(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        if (! $this->artworkReorderingAvailable()) {
            app(AdminNotifier::class)->notification(
                title: 'Clear filters to reorder',
                status: 'warning',
            );

            return;
        }

        $selectedIds = array_keys($this->selectedArtworkIdSet());
        if ($selectedIds === []) {
            app(AdminNotifier::class)->notification(
                title: 'Select artworks first',
                status: 'warning',
            );

            return;
        }

        $currentIds = $this->orderedArtworkIds();
        $orderedIds = ArtworkSelectionOrder::moveOneSlot($currentIds, $selectedIds, $direction);
        if ($orderedIds === $currentIds) {
            return;
        }

        $this->saveArtworkOrder($orderedIds);
        $this->refreshWorkspaceAfterMutation();
        app(AdminNotifier::class)->notification(
            title: 'Selected artworks reordered',
            status: 'success',
        );
    }

    public function moveArtworkToGalleryAction(): Action
    {
        $action = Action::make('moveArtworkToGallery')
            ->label('Move to Gallery')
            ->modalHeading('Move artwork')
            ->schema([
                Select::make('target_gallery_id')
                    ->label('Destination Gallery')
                    ->options(fn (): array => collect($this->moveTargets)->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->reassignArtworkTo((int) ($arguments['artwork'] ?? 0), (int) ($data['target_gallery_id'] ?? 0));
            });

        return AdminDialog::command($action, 'Move artwork');
    }

    public function moveSelectedToGalleryAction(): Action
    {
        $action = Action::make('moveSelectedToGallery')
            ->label('Move to Gallery')
            ->modalHeading('Move artworks')
            ->schema([
                Select::make('target_gallery_id')
                    ->label('Destination Gallery')
                    ->options(fn (): array => collect($this->moveTargets)->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->reassignSelectedArtworksTo((int) ($data['target_gallery_id'] ?? 0));
            });

        return AdminDialog::command($action, 'Move artworks');
    }

    private function reassignArtworkTo(int $artworkId, int $targetGalleryId): void
    {
        $galleryId = (int) $this->galleryContext['id'];
        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()->whereKey($artworkId)->where('artwork_category_id', $galleryId)->first();
        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()->whereKey($targetGalleryId)->where('id', '<>', $galleryId)->first();

        if (! $artwork || ! $destination) {
            app(AdminNotifier::class)->notification(
                title: 'Artwork could not be moved',
                status: 'danger',
            );

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
        app(AdminNotifier::class)->notification(
            title: 'Artwork moved',
            body: 'Media references were preserved.',
            status: 'success',
        );
    }

    private function reassignSelectedArtworksTo(int $targetGalleryId): void
    {
        $galleryId = (int) $this->galleryContext['id'];
        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()->whereKey($targetGalleryId)->where('id', '<>', $galleryId)->first();
        if (! $destination) {
            app(AdminNotifier::class)->notification(
                title: 'Choose a destination Gallery',
                status: 'warning',
            );

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
        app(AdminNotifier::class)->notification(
            title: $count === 1 ? 'Artwork moved' : $count.' artworks moved',
            body: 'Media references remain shared and unchanged.',
            status: 'success',
        );
    }

    private function artworkReorderingAvailable(): bool
    {
        return trim($this->search) === ''
            && $this->statusFilter === 'any'
            && $this->readinessFilter === 'any';
    }
}
