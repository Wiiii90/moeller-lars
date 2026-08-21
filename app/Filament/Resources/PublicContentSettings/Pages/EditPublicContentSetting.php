<?php

namespace App\Filament\Resources\PublicContentSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditPublicContentSetting extends EditRecord
{
    protected static string $resource = PublicContentSettingResource::class;

    public function getHeading(): string|Htmlable|null
    {
        return new HtmlString('<span class="artist-general-heading__kicker">Site settings</span><span class="artist-general-heading__title">General</span>');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PublicContentSetting $record */
        return app(AdminSettingsService::class)->updatePublicContent($record, $data);
    }
}
