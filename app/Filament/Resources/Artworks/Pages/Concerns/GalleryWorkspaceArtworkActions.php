<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceArtworkActions
{
    public function removeArtworkAction(): Action
    {
        return Action::make('removeArtwork')
            ->label('Remove')
            ->requiresConfirmation()
            ->modalHeading('Remove artwork from Gallery?')
            ->modalDescription('The artwork becomes unassigned. Its Media Files stay intact and reusable.')
            ->action(function (array $arguments): void {
                try {
                    app(ArtworkGalleryAssignmentService::class)->detach($this->actionArtwork($arguments));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Artwork could not be removed', $exception);

                    return;
                }

                $this->loadArtworks();
                Notification::make()->title('Artwork removed from Gallery')->success()->send();
            });
    }

    public function deleteArtworkAction(): Action
    {
        return Action::make('deleteArtwork')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete artwork?')
            ->modalDescription('This deletes the artwork record and its media usages. Media Files themselves are not deleted.')
            ->action(function (array $arguments): void {
                try {
                    app(ArtworkDraftService::class)->delete($this->actionArtwork($arguments));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Artwork could not be deleted', $exception);

                    return;
                }

                $this->loadArtworks();
                Notification::make()->title('Artwork deleted')->success()->send();
            });
    }

    public function publishArtworkAction(): Action
    {
        return Action::make('publishArtwork')
            ->label('Publish')
            ->action(function (array $arguments): void {
                try {
                    app(ArtworkPublicationService::class)->publish($this->actionArtwork($arguments));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Artwork cannot be published', $exception);

                    return;
                }

                $this->loadArtworks();
                Notification::make()->title('Artwork published')->success()->send();
            });
    }

    public function unpublishArtworkAction(): Action
    {
        return Action::make('unpublishArtwork')
            ->label('Unpublish')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                app(ArtworkPublicationService::class)->unpublish($this->actionArtwork($arguments));
                $this->loadArtworks();
                Notification::make()->title('Artwork unpublished')->success()->send();
            });
    }

}
