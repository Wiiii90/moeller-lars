<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
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

    public function previewArtworkAction(): Action
    {
        return Action::make('previewArtwork')
            ->label('Preview artwork')
            ->modalHeading(fn (array $arguments): string => (string) $this->actionArtwork($arguments)->getAttribute('title'))
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.artworks.partials.preview-dialog',
                $this->artworkPreviewDialogData($arguments),
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Close')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->extraModalFooterActions(fn (array $arguments): array => $this->artworkPreviewFooterActions($arguments))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog gallery-artwork-preview-dialog']);
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
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete media file')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog'])
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

    /** @return array<string, mixed> */
    private function artworkPreviewDialogData(array $arguments): array
    {
        $artwork = $this->actionArtwork($arguments);
        $visibleRows = collect($this->artworks);
        $visibleIds = $visibleRows
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $index = array_search((int) $artwork->getKey(), $visibleIds, true);
        $position = $index === false ? null : $index + 1;
        $row = $visibleRows->first(
            static fn (mixed $candidate): bool => is_array($candidate)
                && (int) ($candidate['id'] ?? 0) === (int) $artwork->getKey(),
        );
        $row = is_array($row) ? $row : [];

        /** @var ArtworkMedia|null $primary */
        $primary = $artwork->artworkMedia()
            ->where('role', 'primary')
            ->with('mediaAsset')
            ->orderBy('position')
            ->first();
        $asset = $primary?->getRelationValue('mediaAsset');
        $primaryMedia = null;

        if ($asset instanceof MediaAsset && $asset->getAttribute('state') === 'available') {
            $mime = (string) $asset->getAttribute('mime_type');
            $altOverride = trim((string) ($primary?->getAttribute('alt_text_override') ?? ''));
            $defaultAlt = trim((string) ($asset->getAttribute('alt_text') ?? ''));
            $primaryMedia = [
                'id' => (int) $asset->getKey(),
                'filename' => (string) $asset->getAttribute('original_filename'),
                'kind' => MediaTypePolicy::kind($mime),
                'type_label' => MediaTypePolicy::label($mime),
                'mime' => $mime,
                'preview_url' => route('admin.media.original', $asset),
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : '—',
                'alt_text' => $altOverride !== '' ? $altOverride : $defaultAlt,
            ];
        }

        $state = (string) $artwork->getAttribute('state');

        return [
            'artwork' => [
                'id' => (int) $artwork->getKey(),
                'title' => (string) $artwork->getAttribute('title'),
                'medium' => (string) ($artwork->getAttribute('medium') ?? ''),
                'dimensions' => (string) ($artwork->getAttribute('dimensions') ?? ''),
                'year' => $artwork->getAttribute('work_year'),
                'state' => $state,
                'state_label' => (string) ($row['state_label'] ?? ucfirst($state)),
                'readiness_label' => (string) ($row['readiness_label'] ?? '—'),
                'public_url' => is_string($row['public_url'] ?? null) ? $row['public_url'] : null,
            ],
            'primaryMedia' => $primaryMedia,
            'previousId' => $index !== false && $index > 0 ? $visibleIds[$index - 1] : null,
            'nextId' => $index !== false && $index < count($visibleIds) - 1 ? $visibleIds[$index + 1] : null,
            'resultPosition' => $position,
            'resultTotal' => count($visibleIds),
        ];
    }

    /** @return list<Action> */
    private function artworkPreviewFooterActions(array $arguments): array
    {
        $data = $this->artworkPreviewDialogData($arguments);
        $artwork = $data['artwork'];
        $artworkId = (int) $artwork['id'];
        $actions = [];

        if (is_string($artwork['public_url']) && $artwork['public_url'] !== '') {
            $actions[] = Action::make('previewViewPublic')
                ->label('View public')
                ->url($artwork['public_url'])
                ->openUrlInNewTab()
                ->extraAttributes(['class' => 'media-dialog-footer__utility']);
        }

        $actions[] = Action::make('previewEditArtwork')
            ->label('Edit artwork')
            ->extraAttributes(['class' => 'media-dialog-footer__utility'])
            ->action(function () use ($artworkId): void {
                $this->replaceMountedAction('editArtwork', ['artwork' => $artworkId]);
            });

        $published = $artwork['state'] === 'published';
        $actions[] = Action::make('previewLifecycle')
            ->label($published ? 'Unpublish' : 'Publish')
            ->extraAttributes(['class' => 'media-dialog-footer__utility'])
            ->action(function () use ($published, $artworkId): void {
                $this->replaceMountedAction(
                    $published ? 'unpublishArtwork' : 'publishArtwork',
                    ['artwork' => $artworkId],
                );
            });

        if (is_array($data['primaryMedia'])) {
            $actions[] = Action::make('previewDeletePrimaryMedia')
                ->label('Delete media file')
                ->color('danger')
                ->extraAttributes(['class' => 'media-dialog-footer__primary'])
                ->action(function () use ($artworkId): void {
                    $this->replaceMountedAction('deletePrimaryMedia', ['artwork' => $artworkId]);
                });
        }

        return $actions;
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
