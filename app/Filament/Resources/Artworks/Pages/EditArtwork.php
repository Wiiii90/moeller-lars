<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
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
            $record->fill($data);

            if ($record->isDirty()) {
                $record->save();
                app(AdminAuditService::class)->record($actor, 'artwork.updated', 'artwork', $record->getKey());
            }

            return $record;
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
        ];
    }

    private function artworkRecord(): Artwork
    {
        /** @var Artwork $record */
        $record = $this->getRecord();

        return $record;
    }
}
