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
use Filament\Forms\Components\Select;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Artwork';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')
                ->description('Name, public URL and optional one-level grouping. Existing category URLs stay unchanged when categories are grouped.')
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
                    Select::make('parent_id')
                        ->label('Parent category')
                        ->placeholder('Top-level category')
                        ->helperText('Optional. Child categories appear below this parent in the public navigation. Only one child level is supported.')
                        ->options(fn (?Model $record): array => ArtworkCategory::query()
                            ->whereNull('parent_id')
                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->orderBy('position')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (?Model $record): bool => $record instanceof ArtworkCategory && $record->children()->exists()),
                    Textarea::make('description')
                        ->nullable()
                        ->maxLength(10000),
                ])
                ->columns(2),
            Section::make('Public presentation')
                ->description('Publication, menu visibility and homepage visibility are separate decisions. Top-level categories and each child group are ordered independently from the Categories list.')
                ->schema([
                    Hidden::make('position')
                        ->default(fn (): int => ((int) (ArtworkCategory::query()->max('position') ?? -1)) + 1),
                    Toggle::make('show_in_navigation')
                        ->label('Show in public navigation')
                        ->helperText('Only takes effect while this category is published. Visible children require a visible published parent.'),
                    Toggle::make('show_on_home')
                        ->label('Eligible for homepage')
                        ->helperText('Allows this category to participate in homepage presentation when published.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Top level')
                    ->sortable(),
                TextColumn::make('state')->badge()->sortable(),
                IconColumn::make('show_in_navigation')
                    ->label('In navigation')
                    ->boolean(),
                IconColumn::make('show_on_home')
                    ->label('On home')
                    ->boolean(),
                TextColumn::make('artworks_count')
                    ->label('Artworks')
                    ->counts('artworks')
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Public URL')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position')
                    ->label('Sibling order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        Notification::make()->title('Category moved up within its group')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (ArtworkCategory $record): bool => app(ArtworkCategoryOrderService::class)->canMove($record, 'down'))
                    ->action(function (ArtworkCategory $record): void {
                        app(ArtworkCategoryOrderService::class)->move($record, 'down');
                        Notification::make()->title('Category moved down within its group')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No artwork categories yet')
            ->emptyStateDescription('Create the first category. It will remain hidden until it is explicitly published.');
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
