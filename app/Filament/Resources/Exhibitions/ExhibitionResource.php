<?php

namespace App\Filament\Resources\Exhibitions;

use App\Filament\Resources\Exhibitions\Pages\CreateExhibition;
use App\Filament\Resources\Exhibitions\Pages\EditExhibition;
use App\Filament\Resources\Exhibitions\Pages\ListExhibitions;
use App\Models\Exhibition;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExhibitionResource extends Resource
{
    protected static ?string $model = Exhibition::class;

    protected static ?string $navigationLabel = 'Exhibitions';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(240)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                    if (blank($get('slug')) && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(180)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique('exhibitions', 'slug', ignoreRecord: true),
            TextInput::make('date_text')->label('Displayed date')->required()->maxLength(160),
            DatePicker::make('starts_on')->nullable(),
            DatePicker::make('ends_on')->nullable(),
            Select::make('kind')->options([
                'solo' => 'Solo',
                'group' => 'Group',
            ])->nullable(),
            TextInput::make('venue')->maxLength(240)->nullable(),
            TextInput::make('location_text')->label('Location / address')->maxLength(500)->nullable(),
            TextInput::make('city')->maxLength(160)->nullable(),
            TextInput::make('country')->maxLength(160)->nullable(),
            Textarea::make('description')->maxLength(10000)->nullable(),
            TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
            TextInput::make('directions_url')->label('Directions URL')->url()->maxLength(2048)->nullable(),
            Repeater::make('mediaUsages')
                ->relationship()
                ->schema([
                    Select::make('media_asset_id')
                        ->relationship('mediaAsset', 'original_filename')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('role')->options([
                        'hero' => 'Hero',
                        'additional' => 'Additional',
                    ])->required()->default('additional'),
                    TextInput::make('position')->integer()->required()->minValue(0)->default(0),
                    TextInput::make('alt_text_override')->maxLength(500)->nullable(),
                ]),
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
                TextColumn::make('date_text')->label('Date')->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('venue')->searchable(),
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
            'index' => ListExhibitions::route('/'),
            'create' => CreateExhibition::route('/create'),
            'edit' => EditExhibition::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
