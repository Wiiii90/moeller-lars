<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\ArtworkEditDialog;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceArtworkDialogs
{

    public function addArtworkAction(): Action
    {
        $action = Action::make('addArtwork')
            ->label('Add artwork')
            ->fillForm(fn (): array => [
                'restore_artwork_id' => null,
                'primary_media_asset_id' => $this->pendingPrimaryMediaAssetId,
                'dimension_unit' => 'cm',
                'work_date' => null,
            ])
            ->schema($this->artworkCreateFormSchema())
            ->modalHeading('Add artwork')
            ->action(function (array $data): void {
                $restoreId = filter_var(
                    $data['restore_artwork_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]],
                );

                if ($restoreId !== false) {
                    $artwork = Artwork::query()
                        ->whereKey($restoreId)
                        ->whereNull('artwork_category_id')
                        ->first();

                    if (! $artwork instanceof Artwork) {
                        throw ValidationException::withMessages([
                            'restore_artwork_id' => 'This artwork is no longer unassigned.',
                        ]);
                    }

                    /** @var ArtworkCategory $gallery */
                    $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                    app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $gallery);

                    $this->refreshWorkspaceAfterMutation();
                    app(AdminNotifier::class)->notification(
                        title: 'Artwork restored to Gallery',
                        status: 'success',
                    );

                    return;
                }

                $assetId = (int) ($data['primary_media_asset_id'] ?? 0);
                $asset = $assetId > 0
                    ? MediaAsset::query()->whereKey($assetId)->where('state', 'available')->first()
                    : null;
                if ($assetId > 0 && (! $asset instanceof MediaAsset
                    || ! in_array((string) $asset->getAttribute('mime_type'), $this->primaryMimeTypes(), true))) {
                    throw ValidationException::withMessages([
                        'primary_media_asset_id' => 'Choose an available image or video from Storage.',
                    ]);
                }
                if ($asset === null && filled($data['primary_alt_text'] ?? null)) {
                    throw ValidationException::withMessages([
                        'primary_alt_text' => 'Choose a Media file before entering an ALT text override.',
                    ]);
                }

                // The optional visual slug suggestion can still be pending if
                // a title blur and modal submission arrive together. Persist a
                // deterministic slug from the submitted title in that case.
                if (blank($data['slug'] ?? null)) {
                    $data['slug'] = Str::slug((string) ($data['title'] ?? ''));
                }
                $payload = $this->normalizeArtworkFormData($data);
                DB::transaction(function () use ($payload, $asset, $data): void {
                    $artwork = app(ArtworkDraftService::class)->create($payload);
                    if ($asset instanceof MediaAsset) {
                        app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
                        if (filled($data['primary_alt_text'] ?? null)) {
                            app(MediaAssetEditorialService::class)->updatePrimaryAltOverride(
                                $artwork,
                                (string) $data['primary_alt_text'],
                            );
                        }
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
