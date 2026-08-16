<?php

namespace App\Filament\Resources\CvEntries;

use App\Filament\Resources\CvEntries\Pages\CreateCvEntry;
use App\Filament\Resources\CvEntries\Pages\EditCvEntry;
use App\Filament\Resources\CvEntries\Pages\ListCvEntries;
use App\Models\CvEntry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CvEntryResource extends Resource
{
    protected static ?string $model = CvEntry::class;

    protected static ?string $navigationLabel = 'CV';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            Textarea::make('body')->maxLength(10000)->nullable(),
            TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
            Select::make('image_media_asset_id')
                ->relationship('imageMediaAsset', 'original_filename')
                ->searchable()
                ->preload()
                ->nullable(),
            TextInput::make('position')->integer()->required()->minValue(0)->default(0),
            Select::make('state')->options([
                'draft' => 'Draft',
                'published' => 'Published',
                'hidden' => 'Hidden',
                'archived' => 'Archived',
            ])->required()->default('draft'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section')->searchable()->sortable(),
                TextColumn::make('year_text')->label('Date')->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('position')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('state')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'hidden' => 'Hidden',
                    'archived' => 'Archived',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
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
