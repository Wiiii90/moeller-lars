<?php

namespace App\Filament\Resources\CvEntries;

use App\Filament\Resources\CvEntries\Pages\CreateCvEntry;
use App\Filament\Resources\CvEntries\Pages\EditCvEntry;
use App\Filament\Resources\CvEntries\Pages\ListCvEntries;
use App\Filament\Support\AdminForm;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\CvEntry;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CvEntryResource extends Resource
{
    protected static ?string $model = CvEntry::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = AdminIcon::CvEntry;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Vita / CV';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Vita / CV entry')
                ->schema([
                    TextInput::make('section')->required()->maxLength(120),
                    TextInput::make('title')->required()->maxLength(240),
                    TextInput::make('year_text')->label('Displayed date/year')->required()->maxLength(80),
                    Select::make('date_precision')->options([
                        'unknown' => 'Unknown',
                        'year' => 'Year',
                        'month' => 'Month',
                        'day' => 'Day',
                    ])->required()->default('unknown'),
                    DatePicker::make('starts_on')->nullable(),
                    DatePicker::make('ends_on')->nullable(),
                    TextInput::make('organisation')->maxLength(240)->nullable(),
                    TextInput::make('location')->maxLength(240)->nullable(),
                    ...AdminRichText::schema('body', 'Details', 10000),
                    MediaAssetSelect::makeId('image_media_asset_id', 'Image from Media Files', imagesOnly: true)
                        ->nullable()
                        ->columnSpanFull(),
                    TextInput::make('external_url')->url()->maxLength(2048)->nullable()->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCvEntries::route('/'),
            'create' => CreateCvEntry::route('/create'),
            'edit' => EditCvEntry::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
