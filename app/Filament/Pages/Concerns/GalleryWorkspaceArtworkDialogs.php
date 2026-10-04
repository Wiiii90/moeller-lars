<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\ArtworkEditDialog;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait GalleryWorkspaceArtworkDialogs
{
    use InteractsWithAdminEditDialogAutosave;

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

                $payload = $this->normalizeArtworkFormData($data);
                $artwork = app(ArtworkDraftService::class)->create($payload);

                if ($upload instanceof TemporaryUploadedFile) {
                    app(ArtworkPrimaryMediaService::class)->attachUpload($artwork, $upload);
                } elseif ($assetId > 0) {
                    /** @var MediaAsset $asset */
                    $asset = MediaAsset::query()->findOrFail($assetId);
                    app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
                }

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
            ->modalHeading(fn (array $arguments): string => 'Edit '.$this->actionArtwork($arguments)->getAttribute('title'))
            ->fillForm(fn (array $arguments): array => $dialog->fill($this->actionArtwork($arguments)))
            ->schema($dialog->schema())
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

}
