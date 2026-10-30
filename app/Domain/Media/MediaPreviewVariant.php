<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Collection;

/**
 * One source of truth for current libvips image thumbnails
 * and optional video-poster-v1 variants across Publication, Admin and Public.
 */
final class MediaPreviewVariant
{
    public static function isImageThumbnail(MediaVariant $variant): bool
    {
        return $variant->getAttribute('state') === 'available'
            && $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE
            && $variant->getAttribute('mime_type') === 'image/webp';
    }

    public static function isVideoPoster(MediaVariant $variant): bool
    {
        return $variant->getAttribute('state') === 'available'
            && $variant->getAttribute('variant_kind') === MediaIngestService::VIDEO_POSTER_KIND
            && $variant->getAttribute('transform_profile') === MediaIngestService::VIDEO_POSTER_PROFILE
            && $variant->getAttribute('mime_type') === 'image/webp';
    }

    public static function imageThumbnail(MediaAsset $asset): ?MediaVariant
    {
        return MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))
            ? self::findUnique($asset, self::isImageThumbnail(...))
            : null;
    }

    public static function videoPoster(MediaAsset $asset): ?MediaVariant
    {
        return MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'))
            ? self::findUnique($asset, self::isVideoPoster(...))
            : null;
    }

    public static function preview(MediaAsset $asset): ?MediaVariant
    {
        return self::imageThumbnail($asset) ?? self::videoPoster($asset);
    }

    /** @param callable(MediaVariant):bool $accept */
    private static function findUnique(MediaAsset $asset, callable $accept): ?MediaVariant
    {
        if ($asset->getAttribute('state') !== 'available') {
            return null;
        }

        $asset->loadMissing('variants');
        /** @var Collection<int, MediaVariant> $variants */
        $variants = $asset->getRelation('variants')->filter($accept)->values();

        return $variants->count() === 1 ? $variants->first() : null;
    }
}
