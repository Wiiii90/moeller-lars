<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Filament\Resources\PublicContentSettings\Pages\ListPublicContentSettings;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PublicContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Website settings';

    protected static ?int $navigationSort = 30;

    public static function getNavigationUrl(): string
    {
        return static::getUrl('edit', ['record' => 1]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vita / CV')
                ->description('Controls whether the Vita / CV section is visible on the public site.')
                ->schema([
                    Toggle::make('cv_enabled')->label('Publish Vita / CV section'),
                    TextInput::make('cv_navigation_label')->label('Navigation label')->required()->maxLength(120),
                    TextInput::make('cv_navigation_position')->label('Navigation order')->integer()->required()->minValue(0),
                ])
                ->columns(2),
            Section::make('Exhibitions')
                ->description('Controls whether the exhibitions section is visible on the public site.')
                ->schema([
                    Toggle::make('exhibitions_enabled')->label('Publish exhibitions section'),
                    TextInput::make('exhibitions_navigation_label')->label('Navigation label')->required()->maxLength(120),
                    TextInput::make('exhibitions_navigation_position')->label('Navigation order')->integer()->required()->minValue(0),
                ])
                ->columns(2),
            Section::make('Contact and profile')
                ->schema([
                    TextInput::make('public_email')->label('Public email')->email()->maxLength(254)->nullable(),
                    TextInput::make('instagram_handle')->label('Instagram handle')->maxLength(30)->regex('/^[A-Za-z0-9._]{1,30}$/')->nullable(),
                    Select::make('contact_state')->label('Contact section')->options([
                        'enabled' => 'Enabled',
                        'under_construction' => 'Under construction',
                        'hidden' => 'Hidden',
                    ])->required(),
                    Select::make('contact_icon')->options([
                        'construction' => 'Construction',
                        'mail' => 'Mail',
                        'info' => 'Info',
                    ])->required(),
                    Textarea::make('contact_status_text')->label('Contact status text')->maxLength(500)->nullable()->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Legal')
                ->schema([
                    Textarea::make('legal_disclaimer')->label('Legal disclaimer')->rows(4)->nullable(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cv_enabled')->label('Vita / CV')->formatStateUsing(fn ($state): string => (bool) $state ? 'Public' : 'Hidden')->badge(),
                TextColumn::make('exhibitions_enabled')->label('Exhibitions')->formatStateUsing(fn ($state): string => (bool) $state ? 'Public' : 'Hidden')->badge(),
                TextColumn::make('contact_state')->label('Contact')->badge(),
                TextColumn::make('updated_at')->dateTime(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicContentSettings::route('/'),
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
