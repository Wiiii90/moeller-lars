<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Media';

    protected static ?int $navigationSort = 12;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'original_filename';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt_text')->label('Default ALT text')->maxLength(500)->nullable(),
            TextInput::make('credit')->maxLength(240)->nullable(),
            Textarea::make('copyright_notice')->maxLength(500)->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')->searchable()->sortable(),
                TextColumn::make('mime_type')->sortable(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('byte_size')->sortable(),
                TextColumn::make('width')->sortable(),
                TextColumn::make('height')->sortable(),
                TextColumn::make('sha256')->fontFamily('mono'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')->options([
                    'available' => 'Available',
                    'quarantined' => 'Quarantined',
                    'deleted' => 'Deleted',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
