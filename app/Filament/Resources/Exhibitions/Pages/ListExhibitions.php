<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Filament\Resources\Exhibitions\ExhibitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExhibitions extends ListRecords
{
    protected static string $resource = ExhibitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
