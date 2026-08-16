<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload media')
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

                    app(MediaIngestService::class)->ingest($data['media']);
                    Notification::make()->title('Media uploaded')->success()->send();
                }),
        ];
    }
}
