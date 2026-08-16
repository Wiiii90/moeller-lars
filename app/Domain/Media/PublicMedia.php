<?php

namespace App\Domain\Media;

use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PublicMedia
{
    public const THUMBNAIL_KIND = 'thumbnail';

    public const PUBLIC_TRANSFORM_PROFILE = 'public-v1';

    public function isPublicAsset(MediaAsset $asset): bool
    {
        if ($asset->getAttribute('state') !== 'available') {
            return false;
        }

        return ArtworkMedia::query()
            ->where('media_asset_id', $asset->getKey())
            ->where('role', 'primary')
            ->whereHas('artwork', fn ($query) => $query
                ->where('state', 'published')
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('state', 'published')))
            ->exists();
    }

    public function isPublicVariant(MediaVariant $variant): bool
    {
        if ($variant->getAttribute('state') !== 'available') {
            return false;
        }

        /** @var MediaAsset|null $asset */
        $asset = $variant->getRelationValue('mediaAsset');

        return $asset !== null && $this->isPublicAsset($asset);
    }

    public function primaryMedia(Artwork $artwork): ArtworkMedia
    {
        /** @var Collection<int, ArtworkMedia> $mediaRows */
        $mediaRows = $artwork->getRelationValue('artworkMedia');
        $primaries = $mediaRows->filter(
            static fn (ArtworkMedia $media): bool => $media->getAttribute('role') === 'primary',
        )->values();

        if ($primaries->count() !== 1) {
            throw new LogicException('Published artwork must have exactly one primary media usage.');
        }

        /** @var ArtworkMedia $primary */
        $primary = $primaries->first();

        return $primary;
    }

    public function altText(Artwork $artwork): string
    {
        $media = $this->primaryMedia($artwork);
        $override = $media->getAttribute('alt_text_override');
        if ($override !== null) {
            if (! is_string($override)) {
                throw new LogicException('Artwork ALT override must be text.');
            }

            return $override;
        }

        $asset = $this->primaryAsset($artwork);
        $altText = $asset->getAttribute('alt_text');
        if (! is_string($altText)) {
            throw new LogicException('Published artwork requires explicit ALT text.');
        }

        return $altText;
    }

    public function thumbnailUrl(Artwork $artwork): string
    {
        $asset = $this->primaryAsset($artwork);

        /** @var Collection<int, MediaVariant> $variants */
        $variants = $asset->getRelationValue('variants');
        $matching = $variants->filter(fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === self::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === self::PUBLIC_TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available'
        )->values();

        if ($matching->count() !== 1) {
            throw new LogicException('Published artwork requires exactly one available public thumbnail.');
        }

        /** @var MediaVariant $variant */
        $variant = $matching->first();

        return route('media.variant', $variant);
    }

    public function originalUrl(Artwork $artwork): string
    {
        return route('media.original', $this->primaryAsset($artwork));
    }

    private function primaryAsset(Artwork $artwork): MediaAsset
    {
        $asset = $this->primaryMedia($artwork)->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            throw new LogicException('Published artwork requires an available primary media asset.');
        }

        return $asset;
    }
}
