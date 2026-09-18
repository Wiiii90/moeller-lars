<?php

namespace App\Filament\Resources\CustomPageSettings\Pages;

use App\Filament\Pages\CustomPageWorkspace;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Models\CustomPageSetting;
use Filament\Resources\Pages\EditRecord;

final class EditCustomPageSetting extends EditRecord
{
    protected static string $resource = CustomPageSettingResource::class;

    public function mount(int|string $record): void
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($record);

        $this->redirect(CustomPageWorkspace::getUrl([
            'section' => (int) $settings->getAttribute('site_section_id'),
        ]), navigate: false);
    }
}
