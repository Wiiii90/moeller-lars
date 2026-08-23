<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\MediaAssets\Pages\ViewMediaAsset;
use App\Filament\Support\AdminForm;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $slug = 'media-files';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Media Files';

    protected static ?int $navigationSort = 2;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'original_filename';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Accessibility and credit')
                ->schema([
                    TextInput::make('alt_text')
                        ->label('Default ALT text')
                        ->helperText('For images, describe the content and function. Individual usages may override this text.')
                        ->maxLength(500)
                        ->nullable(),
                    TextInput::make('credit')
                        ->maxLength(240)
                        ->nullable(),
                    Textarea::make('copyright_notice')
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('variants')
                ->withCount(['artworks', 'exhibitions', 'blogPosts', 'siteIdentitySettings']))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (MediaAsset $record): ?string => self::thumbnailUrl($record))
                    ->imageHeight(56),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('alt_status')
                    ->label('Accessibility')
                    ->state(fn (MediaAsset $record): string => blank($record->getAttribute('alt_text')) ? 'ALT missing' : 'ALT set')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ALT set' ? 'success' : 'warning'),
                TextColumn::make('usage')
                    ->label('Used by')
                    ->state(fn (MediaAsset $record): string => sprintf(
                        '%d artworks · %d exhibitions · %d journal posts · %d site identity',
                        (int) $record->getAttribute('artworks_count'),
                        (int) $record->getAttribute('exhibitions_count'),
                        (int) $record->getAttribute('blog_posts_count'),
                        (int) $record->getAttribute('site_identity_settings_count'),
                    )),
                TextColumn::make('dimensions')
                    ->label('Dimensions')
                    ->state(fn (MediaAsset $record): string => $record->getAttribute('width') && $record->getAttribute('height')
                        ? $record->getAttribute('width').'×'.$record->getAttribute('height')
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('byte_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state): string => self::formatBytes((int) $state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sha256')
                    ->label('Checksum')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')->options([
                    'available' => 'Available',
                    'quarantined' => 'Quarantined',
                    'deleted' => 'Deleted',
                ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Inspect file')
                    ->icon(Heroicon::OutlinedMagnifyingGlassPlus)
                    ->url(fn (MediaAsset $record): string => self::getUrl('view', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No files yet')
            ->emptyStateDescription('Upload reusable files here or attach existing files from a consuming workspace.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'view' => ViewMediaAsset::route('/{record}'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }

    private static function thumbnailUrl(MediaAsset $asset): ?string
    {
        if ($asset->getAttribute('state') !== 'available') {
            return null;
        }

        $asset->loadMissing('variants');

        /** @var MediaVariant|null $variant */
        $variant = $asset->getRelationValue('variants')
            ->first(fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                && $candidate->getAttribute('transform_profile') === 'public-v1'
                && $candidate->getAttribute('state') === 'available');

        return $variant === null ? null : route('admin.media.variant', $variant);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
