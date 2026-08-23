<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceBatchActions
{
    public function removeSelectedArtworksAction(): Action
    {
        return Action::make('removeSelectedArtworks')
            ->label('Remove')
            ->requiresConfirmation()
            ->modalHeading('Remove selected artworks from Gallery?')
            ->modalDescription('Artwork records and Media Files remain. Published artworks must be unpublished first.')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    DB::transaction(function () use ($artworks): void {
                        foreach ($artworks as $artwork) {
                            app(ArtworkGalleryAssignmentService::class)->detach($artwork);
                        }
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks could not be removed', $exception);

                    return;
                }

                $count = $artworks->count();
                $this->clearSelection();
                $this->loadArtworks();
                Notification::make()->title($count.' selected '.($count === 1 ? 'artwork was' : 'artworks were').' removed')->success()->send();
            });
    }

    public function deleteSelectedArtworksAction(): Action
    {
        return Action::make('deleteSelectedArtworks')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected artworks?')
            ->modalDescription('Only draft artworks can be deleted. Media Files are preserved even when they become unreferenced.')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    DB::transaction(function () use ($artworks): void {
                        foreach ($artworks as $artwork) {
                            app(ArtworkDraftService::class)->delete($artwork);
                        }
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks could not be deleted', $exception);

                    return;
                }

                $count = $artworks->count();
                $this->clearSelection();
                $this->loadArtworks();
                Notification::make()->title($count.' '.($count === 1 ? 'artwork deleted' : 'artworks deleted'))->success()->send();
            });
    }

    public function publishSelectedArtworksAction(): Action
    {
        return Action::make('publishSelectedArtworks')
            ->label('Publish')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    DB::transaction(function () use ($artworks): void {
                        foreach ($artworks as $artwork) {
                            app(ArtworkPublicationService::class)->publish($artwork);
                        }
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks cannot be published', $exception);

                    return;
                }

                $this->loadArtworks();
                Notification::make()->title('Selected artworks published')->success()->send();
            });
    }

    public function unpublishSelectedArtworksAction(): Action
    {
        return Action::make('unpublishSelectedArtworks')
            ->label('Unpublish')
            ->requiresConfirmation()
            ->action(function (): void {
                $artworks = $this->selectedArtworks();
                DB::transaction(function () use ($artworks): void {
                    foreach ($artworks as $artwork) {
                        app(ArtworkPublicationService::class)->unpublish($artwork);
                    }
                });

                $this->loadArtworks();
                Notification::make()->title('Selected artworks unpublished')->success()->send();
            });
    }
}
