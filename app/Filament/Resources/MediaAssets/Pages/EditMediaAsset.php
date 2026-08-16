<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIntegrityService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaAsset $asset */
        $asset = $record;

        return app(MediaAssetEditorialService::class)->updateMetadata($asset, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyIntegrity')
                ->label('Verify integrity')
                ->action(function (): void {
                    try {
                        $issues = app(MediaIntegrityService::class)->issues($this->mediaAssetRecord());
                    } catch (Throwable) {
                        Notification::make()->title('Media integrity check failed')->danger()->send();

                        return;
                    }

                    if ($issues === []) {
                        Notification::make()->title('Media integrity verified')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Media integrity check failed')
                        ->body(implode(', ', array_slice($issues, 0, 10)))
                        ->danger()
                        ->send();
                }),
            Action::make('deleteMedia')
                ->label('Delete media')
                ->visible(fn (): bool => $this->mediaAssetRecord()->getAttribute('state') !== 'deleted')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $complete = app(MediaAssetEditorialService::class)->delete($this->mediaAssetRecord());
                    } catch (ValidationException) {
                        Notification::make()->title('Media cannot be deleted')->danger()->send();

                        return;
                    } catch (Throwable) {
                        Notification::make()->title('Media cannot be deleted')->danger()->send();

                        return;
                    }

                    $this->mediaAssetRecord()->refresh();
                    $notification = Notification::make()->title($complete ? 'Media deleted' : 'Media deleted; storage cleanup incomplete');
                    if ($complete) {
                        $notification->success();
                    } else {
                        $notification->warning();
                    }
                    $notification->send();
                }),
        ];
    }

    private function mediaAssetRecord(): MediaAsset
    {
        /** @var MediaAsset $record */
        $record = $this->getRecord();

        return $record;
    }
}
