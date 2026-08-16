<?php

namespace App\Filament\Resources\BlogSettings;

use App\Filament\Resources\BlogSettings\Pages\EditBlogSetting;
use App\Filament\Resources\BlogSettings\Pages\ListBlogSettings;
use App\Models\BlogSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class BlogSettingResource extends Resource
{
    protected static ?string $model = BlogSetting::class;

    protected static ?string $navigationLabel = 'Blog settings';

    protected static ?int $navigationSort = 23;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('public_enabled')->label('Blog public'),
            TextInput::make('navigation_label')->required()->maxLength(120),
            TextInput::make('navigation_position')->integer()->required()->minValue(0),
            TextInput::make('listing_title')->maxLength(240)->nullable(),
            Textarea::make('listing_intro')->maxLength(10000)->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_enabled')->formatStateUsing(fn ($state): string => (bool) $state ? 'Enabled' : 'Hidden'),
            TextColumn::make('navigation_label'),
            TextColumn::make('navigation_position'),
        ])->recordActions([EditAction::make()])->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogSettings::route('/'),
            'edit' => EditBlogSetting::route('/{record}/edit'),
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
