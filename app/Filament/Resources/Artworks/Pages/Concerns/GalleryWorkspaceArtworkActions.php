<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function deletePrimaryMediaAction(): Action
    {
        return Action::make('deletePrimaryMedia')
            ->label('Delete media file')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Delete '.(string) $this->primaryMediaAsset($arguments)->getAttribute('original_filename').'?')
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.media-assets.partials.delete-dialog',
                ['references' => $this->primaryMediaReferences($this->primaryMediaAsset($arguments))],
            ))
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete media file'))
            ->action(function (array $arguments): void {
                $asset = $this->primaryMediaAsset($arguments);

                try {
                    app(MediaAssetEditorialService::class)->delete($asset);
                } catch (Throwable $exception) {
                    if (! $exception instanceof ValidationException) {
                        report($exception);
                    }

                    $fresh = $asset->fresh();
                    if ($fresh instanceof MediaAsset && $fresh->getAttribute('state') === 'deleted') {
                        $this->loadArtworks();
                        Notification::make()
                            ->title('File cleanup failed')
                            ->body('The file was removed from Media Files, but stored file cleanup could not be completed.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($exception instanceof ValidationException) {
                        $this->notifyValidationFailure('Media file was not deleted', $exception);

                        return;
                    }

                    Notification::make()
                        ->title('Media file was not deleted')
                        ->body('The file could not be deleted.')
                        ->danger()
                        ->send();

                    return;
                }

                $this->loadArtworks();
                Notification::make()->title('File deleted')->success()->send();
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

    private function primaryMediaAsset(array $arguments): MediaAsset
    {
        $artwork = $this->actionArtwork($arguments);
        $usage = $artwork->artworkMedia()
            ->where('role', 'primary')
            ->with('mediaAsset')
            ->first();
        $asset = $usage?->getRelationValue('mediaAsset');

        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['media' => 'This artwork has no available primary Media File to delete.']);
        }

        return $asset;
    }

    /** @return list<array{type:string,label:string,url:?string}> */
    private function primaryMediaReferences(MediaAsset $asset): array
    {
        $catalog = app(MediaReferenceCatalog::class);
        $catalog->loadAssetReferences($asset);

        return $catalog->references($asset);
    }
}
