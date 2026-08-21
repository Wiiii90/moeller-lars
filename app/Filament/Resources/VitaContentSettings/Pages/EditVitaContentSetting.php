<?php

namespace App\Filament\Resources\VitaContentSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\VitaContentSettings\VitaContentSettingResource;
use App\Models\PublicContentSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditVitaContentSetting extends EditRecord
{
    protected static string $resource = VitaContentSettingResource::class;

    public function getBreadcrumbs(): array
    {
        return ['Pages', 'Vita'];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PublicContentSetting $record */
        return app(AdminSettingsService::class)->updatePublicContent($record, $data);
    }
}
