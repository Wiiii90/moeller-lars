<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Filament\Resources\CvEntries\CvEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCvEntries extends ListRecords
{
    protected static string $resource = CvEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
