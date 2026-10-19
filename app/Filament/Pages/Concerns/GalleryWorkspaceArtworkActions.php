<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceArtworkActions
{
    /** @var array<int, array<string, mixed>> */
    private array $artworkPreviewDialogDataCache = [];

    public function deleteArtworkAction(): Action
    {
        $action = Action::make('deleteArtwork')
            ->label('Delete artwork')
            ->color('danger')
            ->fillForm(fn (array $arguments): array => [
                'delete_media_file' => false,
                'reference_snapshot' => $this->primaryMediaReferenceSnapshot($arguments),
            ])
            ->schema([
                Checkbox::make('delete_media_file')
                    ->label('Also delete the original media file from Storage')
                    ->disabled(fn (array $arguments): bool => $this->availablePrimaryMediaAsset($arguments) === null)
                    ->helperText('Unchecked: only this Artwork and its Gallery slot are deleted. Checked: the file and all its other references are removed too.'),
                Hidden::make('reference_snapshot'),
            ])
            ->modalContent(function (array $arguments): View {
                $asset = $this->availablePrimaryMediaAsset($arguments);

                return view('filament.resources.artworks.partials.delete-dialog', [
                    'asset' => $asset,
                    'references' => $asset instanceof MediaAsset ? $this->primaryMediaReferences($asset) : [],
                ]);
            })
            ->action(function (array $data, array $arguments): void {
                $artwork = $this->actionArtwork($arguments)->fresh();
                $deleteFile = ($data['delete_media_file'] ?? false) === true;
                $asset = $this->availablePrimaryMediaAsset($arguments);

                if ($deleteFile && (! $asset instanceof MediaAsset
                    || ! hash_equals($this->primaryMediaReferenceSnapshot($arguments), (string) ($data['reference_snapshot'] ?? '')))) {
                    throw ValidationException::withMessages([
                        'delete_media_file' => 'The file or its references changed. Reopen Delete to review all affected content.',
                    ]);
                }

                try {
                    if ($deleteFile && $asset instanceof MediaAsset) {
                        // One media-domain transaction owns both the optional
                        // original deletion and the Gallery Artwork removal.
                        app(MediaAssetEditorialService::class)->delete($asset, $artwork);
                    } else {
                        app(ArtworkDraftService::class)->delete($artwork);
                    }
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Artwork was not deleted', $exception);

                    return;
                } catch (\Throwable $exception) {
                    // Physical file cleanup can fail *after* a successful DB
                    // commit; distinguish that from an earlier failed mutation.
                    if ($deleteFile && $asset instanceof MediaAsset
                        && ! Artwork::query()->whereKey($artwork->getKey())->exists()
                        && (string) $asset->fresh()?->getAttribute('state') === 'deleted') {
                        report($exception);
                        $this->refreshWorkspaceAfterMutation();
                        app(AdminNotifier::class)->notification(
                            title: 'Artwork deleted; file cleanup incomplete',
                            body: 'The file is marked deleted and its references were removed, but physical Storage cleanup needs attention.',
                            status: 'danger',
                        );

                        return;
                    }

                    throw $exception;
                }

                $this->refreshWorkspaceAfterMutation();
                app(AdminNotifier::class)->notification(
                    title: $deleteFile ? 'Artwork and file deleted' : 'Artwork deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete artwork?',
            'Delete the Artwork slot. Optionally delete its independent Storage file and every remaining reference.',
            'Delete artwork',
            danger: true,
        );
    }

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
                app(AdminNotifier::class)->notification(
                    title: 'Artwork removed from Gallery',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Remove artwork?',
            'The artwork becomes unassigned. Its files in Storage stay intact and reusable.',
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

    public function publishArtworkAction(): Action
    {
        return Action::make('publishArtwork')
            ->label('Publish')
            ->action(function (array $arguments): void {
                $this->publishArtworkInGallery((int) ($arguments['artwork'] ?? 0));
            });
    }

    /** Reused by the Gallery row Action and the Edit header Action. */
    private function publishArtworkInGallery(int $id): bool
    {
        try {
            $artwork = $this->actionArtwork(['artwork' => $id])->fresh();
            $previousState = (string) $artwork->getAttribute('state');
            $updated = app(ArtworkPublicationService::class)->publish($artwork);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Artwork cannot be published', $exception);

            return false;
        }

        $this->refreshWorkspaceAfterMutation();
        if ($previousState !== (string) $updated->getAttribute('state')) {
            app(AdminNotifier::class)->notification(
                title: 'Artwork published',
                status: 'success',
            );
        }

        return true;
    }

    public function unpublishArtworkAction(): Action
    {
        $action = Action::make('unpublishArtwork')
            ->label('Unpublish')
            ->action(function (array $arguments): void {
                $artwork = $this->actionArtwork($arguments);
                $previousState = (string) $artwork->getAttribute('state');
                $updated = app(ArtworkPublicationService::class)->unpublish($artwork);
                $this->refreshWorkspaceAfterMutation();
                if ($previousState !== (string) $updated->getAttribute('state')) {
                    app(AdminNotifier::class)->notification(
                        title: 'Artwork unpublished',
                        status: 'success',
                    );
                }
                // A confirmation opened from the Edit header is a state
                // mutation, not a request to close the editing session.
                // Standalone table Unpublish still completes normally.
                if (($arguments['returnToEditor'] ?? false) === true) {
                    $this->replaceMountedAction('editArtwork', ['artwork' => (int) $artwork->getKey()]);
                }
            });

        // Unpublish is a reversible editorial state change, performed in one click.
        return $action;
    }

    /** @return array<string, mixed> */
    private function artworkPreviewDialogData(array $arguments): array
    {
        $artwork = $this->actionArtwork($arguments);
        $artworkId = (int) $artwork->getKey();

        if (isset($this->artworkPreviewDialogDataCache[$artworkId])) {
            return $this->artworkPreviewDialogDataCache[$artworkId];
        }
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

        return $this->artworkPreviewDialogDataCache[$artworkId] = [
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

        $actions[] = Action::make('previewDeleteArtwork')
            ->label('Delete artwork')
            ->icon(AdminIcon::Delete->value)
            ->iconButton()
            ->color('danger')
            ->disabled($published)
            ->extraAttributes(['class' => 'admin-dialog__header-action is-danger'])
            ->action(function () use ($artworkId): void {
                $this->replaceMountedAction('deleteArtwork', ['artwork' => $artworkId]);
            });

        return $actions;
    }

    private function availablePrimaryMediaAsset(array $arguments): ?MediaAsset
    {
        $usage = $this->actionArtwork($arguments)->artworkMedia()
            ->where('role', 'primary')
            ->with('mediaAsset')
            ->first();
        $asset = $usage?->getRelationValue('mediaAsset');

        return $asset instanceof MediaAsset && $asset->getAttribute('state') === 'available' ? $asset : null;
    }

    private function primaryMediaReferenceSnapshot(array $arguments): string
    {
        $asset = $this->availablePrimaryMediaAsset($arguments);
        $references = $asset instanceof MediaAsset ? $this->primaryMediaReferences($asset) : [];

        return hash('sha256', json_encode($references, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{type:string,label:string,url:?string}> */
    private function primaryMediaReferences(MediaAsset $asset): array
    {
        $catalog = app(MediaReferenceCatalog::class);
        $catalog->loadAssetReferences($asset);

        return $catalog->references($asset);
    }
}
