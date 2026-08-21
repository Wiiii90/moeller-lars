<?php

namespace App\Filament\Resources\CustomPageSettings\Pages;

use App\Filament\Pages\SitePages;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

final class EditCustomPageSetting extends EditRecord
{
    protected static string $resource = CustomPageSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }
}
