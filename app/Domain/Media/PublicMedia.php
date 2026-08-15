<?php

namespace App\Domain\Media;

use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Collection;

class PublicMedia
{
    public const THUMBNAIL_KIND = 'thumbnail';

    public const PUBLIC_TRANSFORM_PROFILE = 'public-v1';

    public function primaryMedia(Artwork $artwork): ?ArtworkMedia
    {
        /** @var Collection<int, ArtworkMedia> $mediaRows */
        $mediaRows = $artwork->getRelationValue('artworkMedia');

        return $mediaRows->first(fn (ArtworkMedia $media) => $media->getAttribute('role') === 'primary');
    }

    public function altText(Artwork $artwork): string
    {
        $media = $this->primaryMedia($artwork);
        /** @var MediaAsset|null $asset */
        $asset = $media?->getRelationValue('mediaAsset');

        return $media?->getAttribute('alt_text_override') ?? $asset?->getAttribute('alt_text') ?? $artwork->getAttribute('title');
    }

    public function thumbnailUrl(Artwork $artwork): ?string
    {
        /** @var MediaAsset|null $asset */
        $asset = $this->primaryMedia($artwork)?->getRelationValue('mediaAsset');
        if (! $asset) {
            return null;
        }

        /** @var Collection<int, MediaVariant> $variants */
        $variants = $asset->getRelationValue('variants');
        $variant = $variants->first(fn (MediaVariant $variant) => $variant->getAttribute('variant_kind') === self::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === self::PUBLIC_TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available'
        );

        if ($variant) {
            return route('media.variant', $variant);
        }

        return $asset->getAttribute('state') === 'available' ? route('media.original', $asset) : null;
    }

    public function originalUrl(Artwork $artwork): ?string
    {
        /** @var MediaAsset|null $asset */
        $asset = $this->primaryMedia($artwork)?->getRelationValue('mediaAsset');

        return $asset?->getAttribute('state') === 'available' ? route('media.original', $asset) : null;
    }
}
