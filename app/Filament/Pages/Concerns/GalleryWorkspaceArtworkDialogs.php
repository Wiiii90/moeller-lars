<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\ArtworkEditDialog;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceArtworkDialogs
{

    public function addArtworkAction(): Action
    {
        $action = Action::make('addArtwork')
            ->label('Add artwork')
            ->fillForm(fn (): array => [
                'primary_media_asset_id' => $this->pendingPrimaryMediaAssetId,
                'dimension_unit' => 'cm',
                'work_date' => null,
            ])
            ->schema($this->artworkCreateFormSchema())
            ->modalHeading(__('Add artwork'))
            ->action(function (array $data): void {
                $upload = $data['primary_upload'] ?? null;
                $assetId = (int) ($data['primary_media_asset_id'] ?? 0);
                $uploadedId = filter_var($upload, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($upload !== null && $uploadedId === false) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose an available Media File.']);
                }
                if ($uploadedId !== false && $uploadedId !== null && $assetId > 0) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose either a new upload or an existing Media File, not both.']);
                }

                if (filled($data['primary_alt_text'] ?? null) && ! $upload && $assetId <= 0) {
                    throw ValidationException::withMessages([
                        'primary_alt_text' => 'Choose or upload primary media before entering ALT text.',
                    ]);
                }

                $selectedId = $uploadedId !== false && $uploadedId !== null ? $uploadedId : $assetId;
                $asset = $selectedId > 0
                    ? MediaAsset::query()->whereKey($selectedId)->where('state', 'available')->first()
                    : null;
                if ($selectedId > 0 && (! $asset instanceof MediaAsset
                    || ! in_array((string) $asset->getAttribute('mime_type'), $this->primaryMimeTypes(), true))) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose an available image or video from Storage.']);
                }
                $payload = $this->normalizeArtworkFormData($data);

                DB::transaction(function () use ($payload, $asset, $data): void {
                    $artwork = app(ArtworkDraftService::class)->create($payload);
                    if ($asset instanceof MediaAsset) {
                        app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
                    }

                    if (filled($data['primary_alt_text'] ?? null)) {
                        app(MediaAssetEditorialService::class)->updatePrimaryAltOverride(
                            $artwork,
                            (string) $data['primary_alt_text'],
                        );
                    }
                });

                $this->pendingPrimaryMediaAssetId = null;
                $this->refreshWorkspaceAfterMutation();
                app(AdminNotifier::class)->notification(
                    title: 'Artwork draft created',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Create draft', AdminDialogSize::Large);
    }

    public function editArtworkAction(): Action
    {
        $dialog = app(ArtworkEditDialog::class);
        $action = Action::make('editArtwork')
            ->label('Edit')
            ->modalHeading(__('Edit artwork'))
            ->fillForm(function (array $arguments) use ($dialog): array {
                $artwork = $this->actionArtwork($arguments)->fresh();
                $orderedIds = $this->orderedArtworkIds();
                $index = array_search((int) $artwork->getKey(), $orderedIds, true);

                return [
                    ...$dialog->fill($artwork),
                    'position' => $index === false ? null : $index + 1,
                ];
            })
            ->schema(fn (array $arguments): array => $dialog->schema(
                autosave: true,
                positionCount: count($this->artworkDataset),
                positionEditable: ($this->galleryContext['sort_mode'] ?? GalleryPresentation::SORT_MANUAL) === GalleryPresentation::SORT_MANUAL,
                primaryReadOnly: (string) $this->actionArtwork($arguments)->fresh()->getAttribute('state') === 'published',
                onPrimaryMediaChanged: function (?int $assetId) use ($dialog, $arguments): void {
                    if (! $dialog->changePrimaryMedia($this->actionArtwork($arguments), $assetId)) {
                        return;
                    }

                    $this->refreshWorkspaceAfterMutation();
                    app(AdminNotifier::class)->notification(
                        title: 'Artwork primary media saved',
                        status: 'success',
                    );
                },
            ))
            ->extraModalFooterActions(fn (array $arguments): array => $this->artworkEditHeaderActions($arguments))
            ->action(function (array $data, array $arguments) use ($dialog): void {
                // Artwork metadata and the Gallery ranking share one transaction,
                // while the existing Gallery order service owns the ranking.
                $changed = DB::transaction(function () use ($dialog, $data, $arguments): bool {
                    $artwork = $this->actionArtwork($arguments);
                    $metadataChanged = $dialog->save($artwork, $data, savePrimaryMedia: false);
                    $orderChanged = array_key_exists('position', $data)
                        && $this->moveArtworkToPosition((int) $artwork->getKey(), $data['position']);

                    return $metadataChanged || $orderChanged;
                });
                $this->refreshWorkspaceAfterMutation();
                if ($changed) {
                    $this->forceRender();
                }

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Artwork saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * Uploads have a committed mutation boundary, independent of the
     * text/select Edit dialog's wire:change autosave lifecycle.
     */
    public function uploadArtworkPrimaryMediaAction(): Action
    {
        $action = Action::make('uploadArtworkPrimaryMedia')
            ->label('Upload primary media')
            ->modalHeading(__('Upload primary media'))
            ->schema([
                \App\Filament\Support\ResumableMediaField::make('primary_upload', 'New image or video')
                    ->required()
                    ->helperText(__('Replaces the draft artwork’s primary media. The previous file remains in Storage.')),
                TextInput::make('primary_alt_text')
                    ->label('ALT text')
                    ->maxLength(500)
                    ->nullable()
                    ->helperText(__('Describe this media for screen readers, or inherit its Storage ALT.')),
            ])
            ->action(function (array $data, array $arguments): void {
                $artwork = $this->actionArtwork($arguments)->fresh();
                if ((string) $artwork->getAttribute('state') === 'published') {
                    throw ValidationException::withMessages([
                        'primary_upload' => 'Unpublish the artwork before replacing primary media.',
                    ]);
                }

                $upload = $data['primary_upload'] ?? null;
                $uploadedId = filter_var($upload, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $asset = $uploadedId === false || $uploadedId === null
                    ? null
                    : MediaAsset::query()->whereKey($uploadedId)->where('state', 'available')->first();
                if (! $asset instanceof MediaAsset || ! in_array(
                    (string) $asset->getAttribute('mime_type'),
                    $this->primaryMimeTypes(),
                    true,
                )) {
                    throw ValidationException::withMessages(['primary_upload' => 'Select a valid image or video before saving.']);
                }
                $current = $artwork->artworkMedia()
                    ->where('role', 'primary')->first();
                $oldId = $current === null ? null : (int) $current->getAttribute('media_asset_id');
                $newAlt = filled($data['primary_alt_text'] ?? null)
                    ? (string) $data['primary_alt_text'] : null;
                $oldAlt = $current?->getAttribute('alt_text_override');

                $mediaChanged = $oldId !== (int) $asset->getKey();
                if (! $mediaChanged && $oldAlt === $newAlt) {
                    app(AdminNotifier::class)->notification(
                        title: 'Primary media unchanged',
                        status: 'info',
                    );
                    return;
                }

                DB::transaction(function () use ($artwork, $asset, $newAlt, $current): void {
                    $primary = app(ArtworkPrimaryMediaService::class);
                    if ($current === null) {
                        $primary->attachAsset($artwork, $asset);
                    } else {
                        $primary->replaceAsset($artwork, $asset);
                    }
                    app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, $newAlt);
                });

                // Persisted state, not completion of the temporary upload,
                // determines whether the editor may announce replacement.
                $savedId = (int) $artwork->artworkMedia()
                    ->where('role', 'primary')
                    ->value('media_asset_id');
                if ($savedId !== (int) $asset->getKey()) {
                    throw ValidationException::withMessages([
                        'primary_upload' => 'The new file was ingested, but the Artwork still points to its previous primary media.',
                    ]);
                }

                $this->refreshWorkspaceAfterMutation();
                $shown = collect($this->artworkDataset)->firstWhere('id', (int) $artwork->getKey());
                if (! is_array($shown)
                    || (int) ($shown['primary_media_asset_id'] ?? 0) !== $savedId) {
                    throw ValidationException::withMessages([
                        'primary_upload' => 'The primary media changed, but the Gallery preview did not update. Reload the Gallery before continuing.',
                    ]);
                }

                app(AdminNotifier::class)->notification(
                    title: ! $mediaChanged
                        ? 'Primary media ALT text saved'
                        : ($oldId === null ? 'Draft primary media added' : 'Draft primary media replaced'),
                    body: ! $mediaChanged
                        ? 'The media file was not changed.'
                        : 'The previous original remains in Storage. Public Gallery and Home change only after the Artwork is published and the publication is committed.',
                    status: 'success',
                );
            });

        return AdminDialog::command($action, 'Use media', AdminDialogSize::Default);
    }

    /** @param array<string, mixed> $arguments
     * @return list<Action>
     */
    private function artworkEditHeaderActions(array $arguments): array
    {
        $artwork = $this->actionArtwork($arguments)->fresh();
        $id = (int) $artwork->getKey();
        $target = ['artwork' => $id];
        $ids = $this->orderedArtworkIds();
        $index = array_search($id, $ids, true);
        $reorderable = $this->artworkReorderingAvailable() && $index !== false;
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $enabled = $reorderable && ($direction === 'up' ? $index > 0 : $index < count($ids) - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($id, $direction, $target): void {
                $this->moveArtwork($id, $direction);
                $this->replaceMountedAction('editArtwork', $target);
            })->disabled(! $enabled);
        }

        // The workspace caches Artwork lookups within a Livewire request.
        // Header icons and disabled states must read the committed state after
        // a nested Publish/Unpublish without closing and reopening the dialog.
        $isPublished = fn (): bool => (string) $this->actionArtwork($target)->fresh()->getAttribute('state') === 'published';
        $publicationRole = fn (): AdminRowAction => $isPublished()
            ? AdminRowAction::Unpublish
            : AdminRowAction::Publish;
        $actions[] = Action::make('dialogUploadPrimaryMedia')
            ->label('Upload primary media')
            ->icon(AdminIcon::Upload->value)
            ->iconButton()
            ->color('gray')
            ->disabled($isPublished)
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($target): void {
                $this->replaceMountedAction('uploadArtworkPrimaryMedia', $target);
            });

        $actions[] = AdminDialog::headerAction(
            'dialogArtworkPublication',
            $publicationRole(),
            function () use ($isPublished, $id, $target): void {
                if ($isPublished()) {
                    $this->unpublishArtworkInGallery($id);
                    $this->replaceMountedAction('editArtwork', $target);

                    return;
                }
                if ($this->publishArtworkInGallery($id)) {
                    $this->replaceMountedAction('editArtwork', $target);
                }
            },
        )
            ->label(fn (): string => $publicationRole()->label())
            ->icon(fn (): string => $publicationRole()->icon()->value);

        // The Gallery action detaches, rather than deletes, an artwork. Use
        // its existing confirmation and semantic icon, never fake Delete.
        $actions[] = Action::make('dialogRemoveArtwork')
            ->label('Remove')
            ->icon(AdminIcon::Detach->value)
            ->iconButton()
            ->color('gray')
            ->disabled($isPublished)
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($target): void {
                $this->replaceMountedAction('removeArtwork', $target);
            });

        return $actions;
    }

}
