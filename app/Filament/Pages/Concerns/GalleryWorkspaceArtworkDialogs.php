<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\ArtworkEditDialog;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Domain\Media\MediaTypePolicy;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                if ($upload !== null && ! $upload instanceof TemporaryUploadedFile) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose a valid image or video file.']);
                }
                if ($upload instanceof TemporaryUploadedFile && $assetId > 0) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose either a new upload or an existing Media File, not both.']);
                }

                if ($upload instanceof TemporaryUploadedFile) {
                    $this->assertUploadIsPrimaryMedia($upload);
                }

                if (filled($data['primary_alt_text'] ?? null) && ! $upload && $assetId <= 0) {
                    throw ValidationException::withMessages([
                        'primary_alt_text' => 'Choose or upload primary media before entering ALT text.',
                    ]);
                }

                // Uploads have an independent Storage lifecycle and must
                // remain valid, reusable files if Artwork validation fails.
                // Artwork creation, primary linkage and its ALT override are
                // one canonical DB mutation, preventing orphan Gallery drafts.
                try {
                    $asset = $upload instanceof TemporaryUploadedFile
                        ? app(MediaIngestService::class)->ingest($upload)
                        : ($assetId > 0 ? MediaAsset::query()->findOrFail($assetId) : null);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'primary_upload' => collect($exception->errors())->flatten()->first()
                            ?? 'The selected image or video could not be processed.',
                    ]);
                }
                $payload = $this->normalizeArtworkFormData($data);

                DB::transaction(function () use ($payload, $asset, $data, $upload): void {
                    $artwork = app(ArtworkDraftService::class)->create($payload);
                    if ($asset instanceof MediaAsset) {
                        app(ArtworkPrimaryMediaService::class)->attachAsset(
                            $artwork,
                            $asset,
                            recordIngest: $upload instanceof TemporaryUploadedFile,
                        );
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
                FileUpload::make('primary_upload')
                    ->label('New image or video')
                    ->storeFiles(false)
                    ->acceptedFileTypes([...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES])
                    ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
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
                if (! $upload instanceof TemporaryUploadedFile) {
                    throw ValidationException::withMessages([
                        'primary_upload' => 'Select an image or video before saving.',
                    ]);
                }
                $this->assertUploadIsPrimaryMedia($upload);

                // A pure media mutation must not submit and validate the
                // entire autosaving Artwork form. The existing ingest and
                // primary linkage services are the authoritative owners.
                // Ingest commits independently so a later editorial error
                // cannot leave an unregistered file in Storage.
                try {
                    $ingested = app(MediaIngestService::class)->ingestUnique($upload);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'primary_upload' => collect($exception->errors())->flatten()->first()
                            ?? 'The selected image or video could not be processed.',
                    ]);
                }
                $asset = $ingested['asset'];
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

                DB::transaction(function () use ($artwork, $asset, $ingested, $newAlt, $current): void {
                    $primary = app(ArtworkPrimaryMediaService::class);
                    if ($current === null) {
                        $primary->attachAsset($artwork, $asset, recordIngest: ! $ingested['duplicate']);
                    } else {
                        $primary->replaceAsset($artwork, $asset, recordIngest: ! $ingested['duplicate']);
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
