<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
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
        $action = Action::make('removeArtwork')
            ->label('Remove')
            ->action(function (array $arguments): void {
                try {
                    app(ArtworkGalleryAssignmentService::class)->detach($this->actionArtwork($arguments));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Artwork could not be removed', $exception);

                    return;
                }

                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('Artwork removed from Gallery')->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            'Remove artwork from Gallery?',
            'The artwork becomes unassigned. Its Media Files stay intact and reusable.',
            'Remove',
            icon: AdminIcon::Detach,
        );
    }

    public function previewArtworkAction(): Action
    {
        $action = Action::make('previewArtwork')
            ->label('Preview artwork')
            ->modalHeading(fn (array $arguments): string => (string) $this->actionArtwork($arguments)->getAttribute('title'))
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.artworks.partials.preview-dialog',
                $this->artworkPreviewDialogData($arguments),
            ))
            ->extraModalFooterActions(fn (array $arguments): array => $this->artworkPreviewHeaderActions($arguments));

        return AdminDialog::viewer($action, AdminDialogSize::Large);
    }

    public function deletePrimaryMediaAction(): Action
    {
        $action = Action::make('deletePrimaryMedia')
            ->label('Delete media file')
            ->color('danger')
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.media-assets.partials.delete-dialog',
                ['references' => $this->primaryMediaReferences($this->primaryMediaAsset($arguments))],
            ))
            ->action(function (array $arguments): void {
                $asset = $this->primaryMediaAsset($arguments);
                $affectedArtworkIds = ArtworkMedia::query()
                    ->where('media_asset_id', $asset->getKey())
                    ->where('role', 'primary')
                    ->pluck('artwork_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                try {
                    app(MediaAssetEditorialService::class)->delete($asset);
                } catch (Throwable $exception) {
                    if (! $exception instanceof ValidationException) {
                        report($exception);
                    }

                    $fresh = $asset->fresh();
                    if ($fresh instanceof MediaAsset && $fresh->getAttribute('state') === 'deleted') {
                        $this->detachGalleryArtworksAfterPrimaryMediaDelete($affectedArtworkIds);
                        $this->refreshWorkspaceAfterMutation();
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

                $this->detachGalleryArtworksAfterPrimaryMediaDelete($affectedArtworkIds);
                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('File deleted')->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            fn (array $arguments): string => 'Delete '.(string) $this->primaryMediaAsset($arguments)->getAttribute('original_filename').'?',
            submitLabel: 'Delete media file',
            danger: true,
            size: AdminDialogSize::Default,
        );
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

                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('Artwork published')->success()->send();
            });
    }

    public function unpublishArtworkAction(): Action
    {
        $action = Action::make('unpublishArtwork')
            ->label('Unpublish')
            ->action(function (array $arguments): void {
                app(ArtworkPublicationService::class)->unpublish($this->actionArtwork($arguments));
                $this->refreshWorkspaceAfterMutation();
                Notification::make()->title('Artwork unpublished')->success()->send();
            });

        return AdminDialog::confirm(
            $action,
            'Unpublish artwork?',
            submitLabel: 'Unpublish',
            icon: AdminIcon::Unpublish,
        );
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
            static fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === (int) $artwork->getKey(),
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
            $altOverride = trim((string) ($primary->getAttribute('alt_text_override') ?? ''));
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
    private function artworkPreviewHeaderActions(array $arguments): array
    {
        $data = $this->artworkPreviewDialogData($arguments);
        $artwork = $data['artwork'];
        $artworkId = (int) $artwork['id'];
        $actions = [];

        if (is_string($artwork['public_url']) && $artwork['public_url'] !== '') {
            $actions[] = Action::make('previewViewPublic')
                ->label('View public')
                ->icon(AdminIcon::OpenPublic->value)
                ->iconButton()
                ->color('gray')
                ->url($artwork['public_url'])
                ->openUrlInNewTab()
                ->extraAttributes(['class' => 'admin-dialog__header-action']);
        }

        $actions[] = Action::make('previewEditArtwork')
            ->label('Edit artwork')
            ->icon(AdminIcon::Edit->value)
            ->iconButton()
            ->color('gray')
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($artworkId): void {
                $this->replaceMountedAction('editArtwork', ['artwork' => $artworkId]);
            });

        $published = $artwork['state'] === 'published';
        $actions[] = Action::make('previewLifecycle')
            ->label($published ? 'Unpublish' : 'Publish')
            ->icon(($published ? AdminIcon::Unpublish : AdminIcon::Publish)->value)
            ->iconButton()
            ->color('gray')
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($published, $artworkId): void {
                $this->replaceMountedAction(
                    $published ? 'unpublishArtwork' : 'publishArtwork',
                    ['artwork' => $artworkId],
                );
            });

        if (is_array($data['primaryMedia'])) {
            $actions[] = Action::make('previewDeletePrimaryMedia')
                ->label('Delete media file')
                ->icon(AdminIcon::Delete->value)
                ->iconButton()
                ->color('danger')
                ->extraAttributes(['class' => 'admin-dialog__header-action is-danger'])
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

    /** @param list<int> $artworkIds */
    private function detachGalleryArtworksAfterPrimaryMediaDelete(array $artworkIds): void
    {
        if ($artworkIds === []) {
            return;
        }

        $artworks = Artwork::query()
            ->whereIn('id', $artworkIds)
            ->whereNotNull('artwork_category_id')
            ->orderBy('artwork_category_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($artworks as $artwork) {
            app(ArtworkGalleryAssignmentService::class)->detach($artwork);
        }
    }

    /** @return list<array{type:string,label:string,url:?string}> */
    private function primaryMediaReferences(MediaAsset $asset): array
    {
        $catalog = app(MediaReferenceCatalog::class);
        $catalog->loadAssetReferences($asset);

        return $catalog->references($asset);
    }
}
