<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Media\PublicMedia;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaVariant;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

class ViewArtwork extends ViewRecord
{
    protected static string $resource = ArtworkResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title'),
            TextEntry::make('slug'),
            TextEntry::make('category.name')->label('Category'),
            TextEntry::make('state'),
            TextEntry::make('work_date')->date(),
            TextEntry::make('position'),
            TextEntry::make('medium'),
            TextEntry::make('dimensions'),
            TextEntry::make('description'),
            TextEntry::make('published_at')->dateTime(),
            ImageEntry::make('primary_preview')
                ->label('Primary preview')
                ->state(function (Artwork $record): ?string {
                    /** @var ArtworkCategory|null $category */
                    $category = $record->getRelationValue('category');
                    if ($record->getAttribute('state') !== 'published' || $category?->getAttribute('state') !== 'published') {
                        return null;
                    }

                    /** @var Collection<int, ArtworkMedia> $artworkMedia */
                    $artworkMedia = $record->getRelationValue('artworkMedia');
                    $primary = $artworkMedia->firstWhere('role', 'primary');
                    $asset = $primary?->getRelationValue('mediaAsset');
                    /** @var MediaVariant|null $variant */
                    $variant = $asset?->variants->first(fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
                        && $variant->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
                        && $variant->getAttribute('state') === 'available');

                    return $variant && app(PublicMedia::class)->isPublicVariant($variant) ? route('media.variant', $variant) : null;
                }),
            TextEntry::make('artworkMedia.mediaAsset.original_filename')->label('Primary filename'),
            TextEntry::make('artworkMedia.mediaAsset.mime_type')->label('Primary MIME type'),
            TextEntry::make('artworkMedia.mediaAsset.width')->label('Primary width'),
            TextEntry::make('artworkMedia.mediaAsset.height')->label('Primary height'),
        ]);
    }
}
