<?php

namespace App\Filament\Resources\PublicContentSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPublicContentSetting extends EditRecord
{
    protected static string $resource = PublicContentSettingResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PublicContentSetting $record */
        return app(AdminSettingsService::class)->updatePublicContent($record, $data);
    }
}
