<?php

namespace App\Filament\Resources\Artworks\RelationManagers;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'artworkMedia';

    protected static ?string $title = 'Gallery images';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gallery images')
            ->description('Additional images shown with this artwork. Upload a new image or reuse an available item from the media library.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('role', 'additional')
                ->with('mediaAsset.variants')
                ->orderBy('position'))
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->state(fn (ArtworkMedia $record): ?string => $this->thumbnailUrl($record))
                    ->imageHeight(72),
                TextColumn::make('mediaAsset.original_filename')
                    ->label('Image')
                    ->searchable()
                    ->description(fn (ArtworkMedia $record): ?string => $record->getRelationValue('mediaAsset')?->getAttribute('alt_text')),
                TextColumn::make('mediaAsset.width')
                    ->label('Dimensions')
                    ->formatStateUsing(fn (mixed $state, ArtworkMedia $record): string => $this->dimensions($record))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('uploadImage')
                    ->label('Upload image')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->schema([
                        FileUpload::make('upload')
                            ->label('Image')
                            ->image()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->ingestAdditionalMedia($artwork, $data['upload']);
                    }),
                Action::make('addFromLibrary')
                    ->label('Add from library')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        Select::make('media_asset_id')
                            ->label('Available media')
                            ->options(fn (): array => $this->availableMediaOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        /** @var MediaAsset $asset */
                        $asset = MediaAsset::query()->findOrFail((int) $data['media_asset_id']);
                        app(ArtworkEditorialService::class)->attachAdditionalMedia($artwork, $asset);
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedArrowsPointingOut)
                    ->modalHeading(fn (ArtworkMedia $record): string => (string) ($record->getRelationValue('mediaAsset')?->getAttribute('original_filename') ?? 'Artwork image'))
                    ->modalContent(fn (ArtworkMedia $record) => view('filament.media.gallery-preview', [
                        'imageUrl' => $this->originalUrl($record),
                        'alt' => (string) ($record->getAttribute('alt_text_override') ?: $record->getRelationValue('mediaAsset')?->getAttribute('alt_text') ?: ''),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon(Heroicon::OutlinedArrowUp)
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($artwork, $record, 'up');
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon(Heroicon::OutlinedArrowDown)
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($artwork, $record, 'down');
                    }),
                Action::make('detach')
                    ->label('Detach')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Remove this image from the artwork gallery. The media asset stays in the library and is not deleted.')
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->detachAdditionalMedia($artwork, $record);
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No additional gallery images')
            ->emptyStateDescription('The primary artwork image remains separate. Add secondary views, details or installation images here.');
    }

    /** @return array<int, string> */
    private function availableMediaOptions(): array
    {
        /** @var Artwork $artwork */
        $artwork = $this->getOwnerRecord();
        $usedIds = $artwork->artworkMedia()->pluck('media_asset_id');

        return MediaAsset::query()
            ->where('state', 'available')
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('created_at')
            ->limit(250)
            ->pluck('original_filename', 'id')
            ->all();
    }

    private function thumbnailUrl(ArtworkMedia $usage): ?string
    {
        $usage->loadMissing('mediaAsset.variants');
        /** @var MediaAsset|null $asset */
        $asset = $usage->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            return null;
        }

        /** @var MediaVariant|null $variant */
        $variant = $asset->getRelationValue('variants')->first(
            fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                && $candidate->getAttribute('state') === 'available',
        );

        return $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null;
    }

    private function originalUrl(ArtworkMedia $usage): ?string
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->getRelationValue('mediaAsset');

        return $asset instanceof MediaAsset && $asset->getAttribute('state') === 'available'
            ? route('admin.media.original', $asset)
            : null;
    }

    private function dimensions(ArtworkMedia $usage): string
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            return '—';
        }

        return sprintf('%s × %s px', $asset->getAttribute('width') ?? '—', $asset->getAttribute('height') ?? '—');
    }
}
