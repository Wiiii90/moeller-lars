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
use App\Models\MediaAsset;
use Filament\Actions\Action;
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
