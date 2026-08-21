<?php

namespace App\Filament\Resources\ContactContentSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\ContactContentSettings\ContactContentSettingResource;
use App\Models\PublicContentSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditContactContentSetting extends EditRecord
{
    protected static string $resource = ContactContentSettingResource::class;

    public function getBreadcrumbs(): array
    {
        return ['Pages', 'Contact'];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PublicContentSetting $record */
        return app(AdminSettingsService::class)->updatePublicContent($record, $data);
    }
}
