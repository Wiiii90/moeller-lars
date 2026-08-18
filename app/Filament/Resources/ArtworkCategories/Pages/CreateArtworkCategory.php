<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateArtworkCategory extends CreateRecord
{
    protected static string $resource = ArtworkCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ArtworkCategoryEditorialService::class)->create($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Category created — not public yet')
            ->body('The new category starts hidden. Enable “Show in public navigation”, save, then use Publish when it is ready for visitors.');
    }
}
