<?php

namespace App\Filament\Resources\ArtworkCategories;

use App\Domain\Artwork\ArtworkCategoryOrderService;
use App\Filament\Resources\ArtworkCategories\Pages\CreateArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\EditArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\ListArtworkCategories;
use App\Models\ArtworkCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
            Section::make('Gallery details')
                ->description('Name, stable public URL and description belong to the Gallery itself. Publication, menu placement, submenu parent and site order are managed from Pages.')
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
            Section::make('Homepage')
                ->description('Homepage eligibility is Gallery content behavior, independent from public navigation placement.')
                ->schema([
                    Hidden::make('position')
                        ->default(fn (): int => ((int) (ArtworkCategory::query()->max('position') ?? -1)) + 1),
                    Toggle::make('show_on_home')
                        ->label('Eligible for homepage')
                        ->helperText('Allows this Gallery to participate in homepage presentation while it is published.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Parent')->placeholder('Top level')->sortable(),
                TextColumn::make('state')->badge()->sortable(),
                IconColumn::make('show_in_navigation')->label('In navigation')->boolean(),
                IconColumn::make('show_on_home')->label('On home')->boolean(),
                TextColumn::make('artworks_count')->label('Artworks')->counts('artworks')->sortable(),
                TextColumn::make('slug')->label('Public URL')->formatStateUsing(fn (string $state): string => '/'.$state),
            ])
            ->defaultSort('position', 'asc')
            ->filters([
                SelectFilter::make('state')->options([
                    'published' => 'Published',
                    'hidden' => 'Hidden',
                ]),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon('heroicon-o-chevron-up')
                    ->visible(fn (ArtworkCategory $record): bool => app(ArtworkCategoryOrderService::class)->canMove($record, 'up'))
                    ->action(function (ArtworkCategory $record): void {
                        app(ArtworkCategoryOrderService::class)->move($record, 'up');
                        Notification::make()->title('Gallery moved up within its group')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (ArtworkCategory $record): bool => app(ArtworkCategoryOrderService::class)->canMove($record, 'down'))
                    ->action(function (ArtworkCategory $record): void {
                        app(ArtworkCategoryOrderService::class)->move($record, 'down');
                        Notification::make()->title('Gallery moved down within its group')->success()->send();
                    }),
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
