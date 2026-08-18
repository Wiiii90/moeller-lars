<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCvEntries extends ListRecords
{
    protected static string $resource = CvEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('cvContactSettings')
                ->label('Vita / CV & contact settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->url(PublicContentSettingResource::getUrl('edit', ['record' => 1])),
        ];
    }
}
