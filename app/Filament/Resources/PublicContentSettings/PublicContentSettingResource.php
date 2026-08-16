<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Filament\Resources\PublicContentSettings\Pages\ListPublicContentSettings;
use App\Models\PublicContentSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PublicContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static ?string $navigationLabel = 'Public content';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('cv_enabled')->label('CV public'),
            Toggle::make('exhibitions_enabled')->label('Exhibitions public'),
            TextInput::make('cv_navigation_label')->required()->maxLength(120),
            TextInput::make('cv_navigation_position')->integer()->required()->minValue(0),
            Select::make('contact_state')->options([
                'enabled' => 'Enabled',
                'under_construction' => 'Under construction',
                'hidden' => 'Hidden',
            ])->required(),
            Textarea::make('contact_status_text')->maxLength(500)->nullable(),
            Select::make('contact_icon')->options([
                'construction' => 'Construction',
                'mail' => 'Mail',
                'info' => 'Info',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cv_enabled')->label('CV')->formatStateUsing(fn ($state): string => (bool) $state ? 'Enabled' : 'Hidden'),
                TextColumn::make('exhibitions_enabled')->label('Exhibitions')->formatStateUsing(fn ($state): string => (bool) $state ? 'Enabled' : 'Hidden'),
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
