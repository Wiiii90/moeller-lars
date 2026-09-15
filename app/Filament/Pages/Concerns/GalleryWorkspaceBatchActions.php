<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceBatchActions
{
    public function removeSelectedArtworksAction(): Action
    {
        $action = Action::make('removeSelectedArtworks')
            ->label('Remove')
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
                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title($count.' selected '.($count === 1 ? 'artwork was' : 'artworks were').' removed')->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            'Remove selected artworks from Gallery?',
            'Artwork records and Media Files remain. Published artworks must be unpublished first.',
            'Remove',
            icon: AdminIcon::Detach,
        );
    }

    public function deleteSelectedArtworksAction(): Action
    {
        $action = Action::make('deleteSelectedArtworks')
            ->label('Delete')
            ->color('danger')
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
                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title($count.' '.($count === 1 ? 'artwork deleted' : 'artworks deleted'))->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            'Delete selected artworks?',
            'Only draft artworks can be deleted. Media Files are preserved even when they become unreferenced.',
            'Delete',
            danger: true,
        );
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

                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('Selected artworks published')->success()->send();
            });
    }

    public function unpublishSelectedArtworksAction(): Action
    {
        $action = Action::make('unpublishSelectedArtworks')
            ->label('Unpublish')
            ->action(function (): void {
                $artworks = $this->selectedArtworks();
                DB::transaction(function () use ($artworks): void {
                    foreach ($artworks as $artwork) {
                        app(ArtworkPublicationService::class)->unpublish($artwork);
                    }
                });

                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('Selected artworks unpublished')->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            'Unpublish selected artworks?',
            submitLabel: 'Unpublish',
            icon: AdminIcon::Unpublish,
        );
    }
}
