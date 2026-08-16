<?php

namespace App\Filament\Resources\PublicContentSettings\Pages;

use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListPublicContentSettings extends ListRecords
{
    protected static string $resource = PublicContentSettingResource::class;
}
