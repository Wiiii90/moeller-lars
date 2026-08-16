<?php

namespace App\Filament\Resources\BlogSettings\Pages;

use App\Filament\Resources\BlogSettings\BlogSettingResource;
use Filament\Resources\Pages\ListRecords;

final class ListBlogSettings extends ListRecords
{
    protected static string $resource = BlogSettingResource::class;
}
