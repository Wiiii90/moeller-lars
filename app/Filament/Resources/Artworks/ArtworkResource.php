<?php

namespace App\Filament\Resources\Artworks;

use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Filament\Resources\Artworks\Pages\ViewArtwork;
use App\Filament\Resources\Artworks\RelationManagers\GalleryImagesRelationManager;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use UnitEnum;

class ArtworkResource extends Resource
{
    protected static ?string $model = Artwork::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = AdminIcon::Artwork;

    protected static string|UnitEnum|null $navigationGroup = 'Artwork';

    protected static ?string $navigationLabel = 'All artworks';

    protected static ?int $navigationSort = 10;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    public static function form(Schema $schema): Schema
    {
        /** @var Artwork|null $record */
        $record = $schema->getRecord();

        return $schema->components(app(ArtworkEditDialog::class)->schema(
            includeGalleryAssignment: true,
            primaryReadOnly: $record instanceof Artwork && (string) $record->getAttribute('state') === 'published',
        ));
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
        $variant = \App\Domain\Media\MediaPreviewVariant::preview($asset);

        return $variant === null ? null : route('admin.media.variant', $variant);
    }
}
