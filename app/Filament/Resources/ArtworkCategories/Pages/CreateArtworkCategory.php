<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Domain\Artwork\GalleryEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateArtworkCategory extends CreateRecord
{
    use UsesAdminEditor;

    protected static string $resource = ArtworkCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(GalleryEditorialService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(SitePages::getUrl());
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Gallery created as hidden');
    }
}
