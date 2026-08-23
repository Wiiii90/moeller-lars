<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait GalleryWorkspaceArtworkModals
{
    public function addArtworkAction(): Action
    {
        return Action::make('addArtwork')
            ->label('Add artwork')
            ->fillForm(fn (): array => [
                'primary_media_asset_id' => $this->pendingPrimaryMediaAssetId,
                'dimension_unit' => 'cm',
                'work_date' => null,
            ])
            ->schema($this->artworkFormSchema(true))
            ->modalHeading('Add artwork')
            ->modalSubmitActionLabel('Create draft')
            ->modalWidth(Width::SevenExtraLarge)
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
                $this->directUploadMessage = null;
                $this->loadArtworks();
                Notification::make()->title('Artwork draft created')->success()->send();
            });
    }

    public function editArtworkAction(): Action
    {
        return Action::make('editArtwork')
            ->label('Edit')
            ->modalHeading(fn (array $arguments): string => 'Edit '.$this->actionArtwork($arguments)->getAttribute('title'))
            ->fillForm(function (array $arguments): array {
                $artwork = $this->actionArtwork($arguments);
                $dimensions = ArtworkDimensions::split($artwork->getAttribute('dimensions'));
                $primary = $artwork->artworkMedia()->where('role', 'primary')->first();

                return [
                    'title' => $artwork->getAttribute('title'),
                    'slug' => $artwork->getAttribute('slug'),
                    'medium' => $artwork->getAttribute('medium'),
                    'dimension_height' => $dimensions['height'],
                    'dimension_width' => $dimensions['width'],
                    'dimension_depth' => $dimensions['depth'],
                    'dimension_unit' => $dimensions['unit'],
                    'dimension_custom' => $dimensions['custom'],
                    'description' => $artwork->getAttribute('description'),
                    'primary_media_asset_id' => $primary?->getAttribute('media_asset_id'),
                    'work_year' => $artwork->getAttribute('work_year'),
                    'work_date' => $artwork->getAttribute('work_date'),
                    'featured_on_home' => (bool) $artwork->getAttribute('featured_on_home'),
                ];
            })
            ->schema($this->artworkFormSchema(false))
            ->modalSubmitActionLabel('Save artwork')
            ->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)
            ->action(function (array $data, array $arguments): void {
                $artwork = $this->actionArtwork($arguments);
                $currentPrimary = $artwork->artworkMedia()->where('role', 'primary')->first();
                $currentAssetId = $currentPrimary === null ? 0 : (int) $currentPrimary->getAttribute('media_asset_id');
                $assetId = (int) ($data['primary_media_asset_id'] ?? 0);
                $upload = $data['primary_upload'] ?? null;

                if ($upload !== null && ! $upload instanceof TemporaryUploadedFile) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose a valid image or video file.']);
                }
                if ($upload instanceof TemporaryUploadedFile && $assetId > 0 && $assetId !== $currentAssetId) {
                    throw ValidationException::withMessages(['primary_upload' => 'Choose either a new upload or an existing Media File, not both.']);
                }
                if ($upload instanceof TemporaryUploadedFile) {
                    $this->assertUploadIsPrimaryMedia($upload);
                }

                DB::transaction(function () use ($artwork, $data, $upload, $assetId, $currentAssetId, $currentPrimary): void {
                    app(ArtworkDraftService::class)->update($artwork, $this->normalizeArtworkFormData($data));

                    if ($upload instanceof TemporaryUploadedFile) {
                        $currentPrimary === null
                            ? app(ArtworkPrimaryMediaService::class)->attachUpload($artwork, $upload)
                            : app(ArtworkPrimaryMediaService::class)->replaceUpload($artwork, $upload);
                    } elseif ($assetId > 0 && $assetId !== $currentAssetId) {
                        /** @var MediaAsset $asset */
                        $asset = MediaAsset::query()->findOrFail($assetId);
                        $currentPrimary === null
                            ? app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset)
                            : app(ArtworkPrimaryMediaService::class)->replaceAsset($artwork, $asset);
                    }
                });

                $this->loadArtworks();
                Notification::make()->title('Artwork saved')->success()->send();
            });
    }
}
