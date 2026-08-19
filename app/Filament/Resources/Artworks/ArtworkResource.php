<?php

namespace App\Filament\Resources\Artworks;

use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\Pages\CreateArtwork;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Filament\Resources\Artworks\Pages\ListArtworks;
use App\Filament\Resources\Artworks\Pages\ManageGalleryArtworks;
use App\Filament\Resources\Artworks\Pages\ViewArtwork;
use App\Filament\Resources\Artworks\RelationManagers\GalleryImagesRelationManager;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class ArtworkResource extends Resource
{
    protected static ?string $model = Artwork::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Artwork';

    protected static ?string $navigationLabel = 'All artworks';

    protected static ?int $navigationSort = 10;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Artwork')
                ->schema([
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
                        ->label('Public URL slug')
                        ->required()
                        ->maxLength(180)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique('artworks', 'slug', ignoreRecord: true)
                        ->disabled(fn (?Model $record): bool => $record?->getAttribute('published_at') !== null),
                    Select::make('artwork_category_id')
                        ->label('Gallery')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(function (): ?int {
                            $galleryId = request()->integer('gallery');
                            if ($galleryId <= 0) {
                                return null;
                            }

                            return ArtworkCategory::query()->whereKey($galleryId)->exists() ? $galleryId : null;
                        }),
                    TextInput::make('medium')->nullable()->maxLength(240),
                    TextInput::make('dimensions')->nullable()->maxLength(240),
                    Textarea::make('description')->nullable()->maxLength(10000)->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Primary image')
                ->description('Attach the primary artwork image while creating the draft. You can replace it and edit its ALT text from the artwork edit page later.')
                ->schema([
                    FileUpload::make('primary_media')
                        ->label('Primary image')
                        ->image()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024))
                        ->helperText('Optional while drafting, but required before publication.'),
                ])
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Section::make('Date and homepage')
                ->schema([
                    TextInput::make('work_year')
                        ->label('Year')
                        ->numeric()
                        ->minValue(1000)
                        ->maxValue(9999)
                        ->nullable(),
                    DatePicker::make('work_date')
                        ->label('Exact date')
                        ->helperText('If set, the year is derived from this date.')
                        ->nullable(),
                    Toggle::make('featured_on_home')
                        ->label('Feature on home when newest year is shared')
                        ->default(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'category',
                'artworkMedia.mediaAsset.variants',
            ]))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Artwork $record): ?string => self::thumbnailUrl($record))
                    ->imageHeight(56),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Gallery')->sortable(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('work_year')->label('Year')->sortable(),
                TextColumn::make('position')
                    ->label('Gallery order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('state')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
                SelectFilter::make('category')->label('Gallery')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('viewPublic')
                    ->label('View on site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Artwork $record): string => route('artworks.show', ['slug' => $record->getAttribute('slug')]))
                    ->openUrlInNewTab()
                    ->visible(fn (Artwork $record): bool => $record->getAttribute('state') === 'published'
                        && $record->getRelationValue('category')?->getAttribute('state') === 'published'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No artworks yet')
            ->emptyStateDescription('Add an artwork, attach its primary media and publish it when it is ready.');
    }

    public static function getRelations(): array
    {
        return [
            'gallery-images' => GalleryImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtworks::route('/'),
            'create' => CreateArtwork::route('/create'),
            'gallery' => ManageGalleryArtworks::route('/gallery/{gallery}'),
            'view' => ViewArtwork::route('/{record}'),
            'edit' => EditArtwork::route('/{record}/edit'),
        ];
    }

    public static function thumbnailUrl(Artwork $artwork): ?string
    {
        $artwork->loadMissing('artworkMedia.mediaAsset.variants');

        /** @var ArtworkMedia|null $usage */
        $usage = $artwork->getRelationValue('artworkMedia')
            ->first(fn (ArtworkMedia $media): bool => $media->getAttribute('role') === 'primary');
        if ($usage === null) {
            return null;
        }

        /** @var MediaAsset|null $asset */
        $asset = $usage->getRelationValue('mediaAsset');
        if ($asset === null || $asset->getAttribute('state') !== 'available') {
            return null;
        }

        /** @var MediaVariant|null $variant */
        $variant = $asset->getRelationValue('variants')
            ->first(fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                && $candidate->getAttribute('state') === 'available');

        return $variant === null ? null : route('admin.media.variant', $variant);
    }
}
