<?php

namespace App\Filament\Resources\ArtworkCategories;

use App\Filament\Resources\ArtworkCategories\Pages\CreateArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\EditArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\ListArtworkCategories;
use App\Models\ArtworkCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class ArtworkCategoryResource extends Resource
{
    protected static ?string $model = ArtworkCategory::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Galleries';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make('Gallery details')
                ->contained(false)
                ->extraAttributes(['class' => 'artist-editor-form-section'])
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(160)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->helperText('Locked after creation. Use “Change public slug” so redirects are preserved.')
                        ->required()
                        ->maxLength(80)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->disabled(fn (?Model $record): bool => $record !== null)
                        ->dehydrated(fn (?Model $record): bool => $record === null),
                    Textarea::make('description')
                        ->nullable()
                        ->maxLength(10000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Fieldset::make('Homepage')
                ->contained(false)
                ->extraAttributes(['class' => 'artist-editor-form-section'])
                ->schema([
                    Toggle::make('show_on_home')
                        ->label('Eligible for homepage'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('siteSection.parent.title')->label('Parent')->placeholder('Top level'),
                TextColumn::make('siteSection.state')->label('State')->badge(),
                IconColumn::make('siteSection.show_in_navigation')->label('In navigation')->boolean(),
                IconColumn::make('show_on_home')->label('On home')->boolean(),
                TextColumn::make('artworks_count')->label('Artworks')->counts('artworks')->sortable(),
                TextColumn::make('slug')->label('Public URL')->formatStateUsing(fn (string $state): string => '/'.$state),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No Galleries yet')
            ->emptyStateDescription('Create Galleries from Pages so placement and content stay in one workflow.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtworkCategories::route('/'),
            'create' => CreateArtworkCategory::route('/create'),
            'edit' => EditArtworkCategory::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
