<?php

namespace App\Filament\Resources\CustomPageSettings\Pages;

use App\Filament\Concerns\UsesEditorOverlay;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

final class EditCustomPageSetting extends EditRecord
{
    use UsesEditorOverlay;

    protected static string $resource = CustomPageSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label('Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(SitePages::getUrl());
    }
}
