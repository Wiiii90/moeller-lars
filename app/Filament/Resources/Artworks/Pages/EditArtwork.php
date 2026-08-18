<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;

class EditArtwork extends EditRecord
{
    protected static string $resource = ArtworkResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('artwork_category_id', $data) || ! array_key_exists('work_date', $data)) {
            throw ValidationException::withMessages(['artwork' => 'Required artwork form data is missing.']);
        }

        /** @var Artwork $record */
        $record = $this->getRecord();
        $categoryId = $data['artwork_category_id'];
        $category = ArtworkCategory::query()->find($categoryId);
        if (! $category instanceof ArtworkCategory) {
            throw ValidationException::withMessages([
                'artwork_category_id' => 'The artwork category is invalid.',
            ]);
        }
        if ($record->getAttribute('state') === 'published' && $category->getAttribute('state') !== 'published') {
            throw ValidationException::withMessages([
                'artwork_category_id' => 'Published artwork requires a published category.',
            ]);
        }

        $data['date_precision'] = filled($data['work_date']) ? 'day' : 'unknown';

        foreach (['state', 'published_at', 'legacy_id', 'legacy_source', 'legacy_date_raw', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }
        unset($data['position']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            if (! array_key_exists('artwork_category_id', $data)) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'The artwork category is required.',
                ]);
            }

            /** @var Artwork $artwork */
            $artwork = $record;
            unset($data['position']);
            $originalCategoryId = (int) $artwork->getRawOriginal('artwork_category_id');
            $targetCategoryId = (int) $data['artwork_category_id'];
            if ($targetCategoryId !== $originalCategoryId) {
                /** @var ArtworkCategory|null $destination */
                $destination = ArtworkCategory::query()->whereKey($targetCategoryId)->lockForUpdate()->first();
                if (! $destination) {
                    throw ValidationException::withMessages(['artwork_category_id' => 'The artwork category is invalid.']);
                }
                $maxPosition = $destination->artworks()->max('position');
                $data['position'] = $maxPosition === null ? 0 : ((int) $maxPosition) + 1;
            }
            $artwork->fill($data);

            if ($artwork->getAttribute('state') === 'published' && $artwork->category()->where('state', '!=', 'published')->exists()) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'Published artwork requires a published category.',
                ]);
            }

            if ($artwork->isDirty()) {
                $artwork->save();
                app(AdminAuditService::class)->record($actor, 'artwork.updated', 'artwork', $artwork->getKey());
            }

            return $artwork;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('uploadPrimaryMedia')
                ->label('Upload primary image')
                ->visible(fn (): bool => ! $this->artworkRecord()->artworkMedia()->where('role', 'primary')->exists())
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024)),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid uploaded image is required.']);
                    }
                    try {
                        app(ArtworkEditorialService::class)->attachPrimaryMedia($this->artworkRecord(), $data['media']);
                    } catch (ValidationException) {
                        Notification::make()->title('Primary image upload failed')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary image uploaded')->body('Add canonical ALT text before publishing.')->success()->send();
                }),
            Action::make('replacePrimaryMedia')
                ->label('Replace primary image')
                ->visible(fn (): bool => $this->primaryArtworkMedia() !== null)
                ->requiresConfirmation()
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024)),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid uploaded image is required.']);
                    }
                    try {
                        app(ArtworkEditorialService::class)->replacePrimaryMedia($this->artworkRecord(), $data['media']);
                    } catch (ValidationException) {
                        Notification::make()->title('Primary image could not be replaced')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary image replaced')->body('Add canonical ALT text for the new image before publishing.')->success()->send();
                }),
            Action::make('publish')
                ->label('Publish')
                ->visible(fn (): bool => $this->artworkRecord()->getAttribute('state') !== 'published')
                ->action(function (): void {
                    try {
                        app(ArtworkEditorialService::class)->publish($this->artworkRecord());
                    } catch (ValidationException) {
                        Notification::make()->title('Artwork cannot be published')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Artwork published')->success()->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->visible(fn (): bool => $this->artworkRecord()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(ArtworkEditorialService::class)->unpublish($this->artworkRecord());
                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Artwork unpublished')->success()->send();
                }),
            Action::make('editPrimaryAlt')
                ->label('Edit image ALT text')
                ->visible(fn (): bool => $this->primaryArtworkMedia() !== null)
                ->schema([
                    TextInput::make('alt_text')
                        ->label('Canonical media ALT text')
                        ->helperText('Required for publication. This description follows the media asset wherever it is reused.')
                        ->required()
                        ->maxLength(500)
                        ->default(fn (): ?string => $this->primaryMediaAsset()->getAttribute('alt_text')),
                    TextInput::make('alt_text_override')
                        ->label('Artwork-specific ALT override')
                        ->helperText('Optional. Use only when this artwork needs a more specific description than the canonical media ALT text.')
                        ->maxLength(500)
                        ->nullable()
                        ->default(function (): ?string {
                            $primary = $this->primaryArtworkMedia();
                            if (! $primary instanceof ArtworkMedia) {
                                throw new LogicException('Artwork has no primary media usage.');
                            }

                            return $primary->getAttribute('alt_text_override');
                        }),
                ])
                ->action(function (array $data): void {
                    try {
                        if (! array_key_exists('alt_text', $data) || ! array_key_exists('alt_text_override', $data)) {
                            throw ValidationException::withMessages(['alt_text' => 'ALT text form data is incomplete.']);
                        }

                        $service = app(MediaAssetEditorialService::class);
                        $service->updateMetadata($this->primaryMediaAsset(), ['alt_text' => $data['alt_text']]);
                        $service->updatePrimaryAltOverride($this->artworkRecord(), $data['alt_text_override']);
                    } catch (ValidationException) {
                        Notification::make()->title('Image ALT text could not be updated')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Image ALT text updated')->success()->send();
                }),
        ];
    }

    private function artworkRecord(): Artwork
    {
        /** @var Artwork $record */
        $record = $this->getRecord();

        return $record;
    }

    private function primaryArtworkMedia(): ?ArtworkMedia
    {
        $media = $this->artworkRecord()->artworkMedia()->where('role', 'primary')->get();
        if ($media->count() > 1) {
            throw new LogicException('Artwork has multiple primary media usages.');
        }

        /** @var ArtworkMedia|null $primary */
        $primary = $media->first();

        return $primary;
    }

    private function primaryMediaAsset(): MediaAsset
    {
        $primary = $this->primaryArtworkMedia();
        if (! $primary instanceof ArtworkMedia) {
            throw new LogicException('Artwork has no primary media usage.');
        }

        $asset = $primary->mediaAsset()->first();
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Artwork primary media asset is unavailable.');
        }

        return $asset;
    }
}
