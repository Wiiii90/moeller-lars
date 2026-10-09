<?php

namespace App\Filament\Resources\Artworks\RelationManagers;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\MediaAssetSelect;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GalleryImagesRelationManager extends RelationManager
{
    /** @var list<int>|null */
    private ?array $usedMediaAssetIdsCache = null;

    protected static string $relationship = 'artworkMedia';

    protected static ?string $title = 'Gallery images';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gallery images')
            ->description('Additional images shown with this artwork. Upload a new image or reuse an available item from the media library.')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->where('role', 'additional');
                $query->with('mediaAsset.variants');
                $query->orderBy('position');

                return $query;
            })
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
                AdminDialog::create(
                    Action::make('uploadImage')
                        ->label('Upload image')
                        ->icon(AdminIcon::Upload)
                        ->schema([
                            FileUpload::make('upload')
                                ->label('Image')
                                ->image()
                                ->storeFiles(false)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize((int) ceil(MediaTypePolicy::imageMaxBytes() / 1024))
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            app(ArtworkEditorialService::class)->ingestAdditionalMedia($artwork, $data['upload']);
                        }),
                    'Upload image',
                    AdminDialogSize::Small,
                ),
                AdminDialog::command(
                    Action::make('addFromLibrary')
                        ->label('Add from Storage')
                        ->icon(AdminIcon::AddFromLibrary)
                        ->schema([
                            MediaAssetSelect::makeId(
                                'media_asset_id',
                                'Available media',
                                imagesOnly: true,
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereNotIn(
                                    'id',
                                    $this->usedMediaAssetIds(),
                                ),
                            )->required(),
                        ])
                        ->action(function (array $data): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            /** @var MediaAsset $asset */
                            $asset = MediaAsset::query()->findOrFail((int) $data['media_asset_id']);
                            app(ArtworkEditorialService::class)->attachAdditionalMedia($artwork, $asset);
                        }),
                    'Add to gallery',
                    AdminDialogSize::Small,
                ),
            ])
            ->recordActions([
                Action::make('storage')
                    ->label('Open Storage')
                    ->icon(AdminIcon::Storage)
                    ->url(MediaAssetResource::getUrl('index'))
                    ->visible(fn (ArtworkMedia $record): bool => $this->asset($record)?->getAttribute('state') === 'available'),
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon(AdminIcon::MoveUp)
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($artwork, $record, 'up');
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon(AdminIcon::MoveDown)
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($artwork, $record, 'down');
                    }),
                AdminDialog::confirm(
                    Action::make('detach')
                        ->label('Detach')
                        ->icon(AdminIcon::Detach)
                        ->color('danger')
                        ->action(function (ArtworkMedia $record): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            app(ArtworkEditorialService::class)->detachAdditionalMedia($artwork, $record);
                        }),
                    heading: 'Detach image',
                    description: 'Removes this image from the artwork. The file remains in Storage.',
                    submitLabel: 'Detach',
                    danger: true,
                    icon: AdminIcon::Detach,
                ),
            ])
            ->paginated(false)
            ->emptyStateHeading('No additional gallery images')
            ->emptyStateDescription('The primary artwork image remains separate. Add secondary views, details or installation images here.');
    }

    /** @return list<int> */
    private function usedMediaAssetIds(): array
    {
        if ($this->usedMediaAssetIdsCache !== null) {
            return $this->usedMediaAssetIdsCache;
        }

        /** @var Artwork $artwork */
        $artwork = $this->getOwnerRecord();

        return $this->usedMediaAssetIdsCache = $artwork->artworkMedia()
            ->pluck('media_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function asset(ArtworkMedia $usage): ?MediaAsset
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->getRelationValue('mediaAsset');

        return $asset instanceof MediaAsset ? $asset : null;
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
