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

class EditArtwork extends EditRecord
{
    protected static string $resource = ArtworkResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Artwork $record */
        $record = $this->getRecord();
        $categoryId = $data['artwork_category_id'] ?? $record->getAttribute('artwork_category_id');
        $category = ArtworkCategory::query()->find($categoryId);
        if ($record->getAttribute('state') === 'published' && $category?->getAttribute('state') !== 'published') {
            throw ValidationException::withMessages([
                'artwork_category_id' => 'Published artwork requires a published category.',
            ]);
        }

        $data['date_precision'] = filled($data['work_date'] ?? null) ? 'day' : 'unknown';

        foreach (['state', 'published_at', 'legacy_id', 'legacy_source', 'legacy_date_raw', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            /** @var Artwork $artwork */
            $artwork = $record;
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
                    $upload = $data['media'];
                    try {
                        if ($upload instanceof TemporaryUploadedFile) {
                            app(ArtworkEditorialService::class)->attachPrimaryMedia($this->artworkRecord(), $upload);
                        }
                    } catch (ValidationException) {
                        Notification::make()->title('Primary image upload failed')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary image uploaded')->success()->send();
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
                    $upload = $data['media'];
                    try {
                        if ($upload instanceof TemporaryUploadedFile) {
                            app(ArtworkEditorialService::class)->replacePrimaryMedia($this->artworkRecord(), $upload);
                        }
                    } catch (ValidationException) {
                        Notification::make()->title('Primary image could not be replaced')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary image replaced')->success()->send();
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
                    TextInput::make('alt_text_override')
                        ->label('Artwork ALT override')
                        ->maxLength(500)
                        ->nullable()
                        ->default(fn (): ?string => $this->primaryArtworkMedia()?->getAttribute('alt_text_override')),
                ])
                ->action(function (array $data): void {
                    try {
                        app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($this->artworkRecord(), $data['alt_text_override'] ?? null);
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
        /** @var ArtworkMedia|null $media */
        $media = $this->artworkRecord()->artworkMedia()->where('role', 'primary')->first();

        return $media;
    }
}
