<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\AdminIcon;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $slug = 'storage';

    protected static string|BackedEnum|null $navigationIcon = AdminIcon::Storage;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Storage';

    protected static ?int $navigationSort = 13;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'original_filename';
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return self::getUrl('index');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
        ];
    }
}
