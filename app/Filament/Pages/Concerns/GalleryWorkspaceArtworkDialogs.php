<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
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
use Filament\Forms\Components\Textarea;
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
            ->modalHeading('Add artwork')
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
                $asset = $upload instanceof TemporaryUploadedFile
                    ? app(MediaIngestService::class)->ingest($upload)
                    : ($assetId > 0 ? MediaAsset::query()->findOrFail($assetId) : null);
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
            ->modalHeading('Edit artwork')
            ->fillForm(fn (array $arguments): array => $dialog->fill($this->actionArtwork($arguments)))
            ->schema($dialog->schema(autosave: true))
            ->extraModalFooterActions(fn (array $arguments): array => $this->artworkEditHeaderActions($arguments))
            ->action(function (array $data, array $arguments) use ($dialog): void {
                $changed = $dialog->save($this->actionArtwork($arguments), $data);
                $this->refreshWorkspaceAfterMutation();

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
            ->modalHeading('Upload primary media')
            ->schema([
                FileUpload::make('primary_upload')
                    ->label('New image or video')
                    ->storeFiles(false)
                    ->acceptedFileTypes([...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES])
                    ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                    ->required()
                    ->helperText('Replaces the draft artwork’s primary media. The previous file remains in Storage.'),
                Textarea::make('primary_alt_text')
                    ->label('ALT text')
                    ->maxLength(500)
                    ->nullable()
                    ->helperText('Describe this media for screen readers, or inherit its Storage ALT.'),
            ])
            ->action(function (array $data, array $arguments): void {
                $artwork = $this->actionArtwork($arguments)->fresh();
                if ((string) $artwork->getAttribute('state') === 'published') {
                    throw ValidationException::withMessages([
                        'primary_upload' => 'Unpublish the artwork before replacing primary media.',
                    ]);
                }

                $dialog = app(ArtworkEditDialog::class);
                $changed = $dialog->save($artwork, [
                    ...$dialog->fill($artwork),
                    'primary_upload' => $data['primary_upload'] ?? null,
                    'primary_alt_text' => $data['primary_alt_text'] ?? null,
                ]);
                $this->refreshWorkspaceAfterMutation();
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Primary media updated',
                        status: 'success',
                    );
                }

                // Complete the committed upload as its own action. Replacing
                // the mounted Action stack during execution could discard
                // FileUpload state and success feedback before Filament
                // finishes its lifecycle. The refreshed Gallery owns the
                // next Edit action.
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

        $published = (string) $artwork->getAttribute('state') === 'published';
        $actions[] = Action::make('dialogUploadPrimaryMedia')
            ->label('Upload primary media')
            ->icon(AdminIcon::Upload->value)
            ->iconButton()
            ->color('gray')
            ->disabled($published)
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($target): void {
                $this->replaceMountedAction('uploadArtworkPrimaryMedia', $target);
            });

        $actions[] = AdminDialog::headerAction(
            'dialogArtworkPublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($published, $id, $target): void {
                if ($published) {
                    // Unpublication is confirmed by its existing domain action.
                    $this->replaceMountedAction('unpublishArtwork', [...$target, 'returnToEditor' => true]);

                    return;
                }
                if ($this->publishArtworkInGallery($id)) {
                    $this->replaceMountedAction('editArtwork', $target);
                }
            },
        );

        // The Gallery action detaches, rather than deletes, an artwork. Use
        // its existing confirmation and semantic icon, never fake Delete.
        $actions[] = Action::make('dialogRemoveArtwork')
            ->label('Remove')
            ->icon(AdminIcon::Detach->value)
            ->iconButton()
            ->color('gray')
            ->disabled($published)
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(function () use ($target): void {
                $this->replaceMountedAction('removeArtwork', $target);
            });

        return $actions;
    }

}
