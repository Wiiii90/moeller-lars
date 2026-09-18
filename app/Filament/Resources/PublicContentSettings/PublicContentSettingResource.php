<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Models\PublicContentSetting;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationUrl(): string
    {
        return General::getUrl();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', PublicContentSetting::SCOPE_GENERAL);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditPublicContentSetting::route('/{record}/edit'),
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
