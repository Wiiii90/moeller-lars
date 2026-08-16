<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArtworkCategories extends ListRecords
{
    protected static string $resource = ArtworkCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
