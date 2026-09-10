<?php

namespace App\Filament\Resources\CustomPageSettings;

use App\Filament\Resources\CustomPageSettings\Pages\EditCustomPageSetting;
use App\Models\CustomPageSetting;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

final class CustomPageSettingResource extends Resource
{
    protected static ?string $model = CustomPageSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'custom page';

    protected static ?string $pluralModelLabel = 'custom pages';

    public static function getPages(): array
    {
        return [
            'edit' => EditCustomPageSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
