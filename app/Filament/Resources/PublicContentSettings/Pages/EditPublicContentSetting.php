<?php

namespace App\Filament\Resources\PublicContentSettings\Pages;

use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use Filament\Resources\Pages\EditRecord;

final class EditPublicContentSetting extends EditRecord
{
    protected static string $resource = PublicContentSettingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->redirect(General::getUrl(), navigate: true);
    }
}
