<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Single media preview reader for images and videos across Publication,
 * Admin and Public. A kind is canonical; historical transform metadata does
 * not create a second reader or allow multiple current preview assets.
 */
final class MediaPreviewVariant
{
    public static function isImageThumbnail(MediaVariant $variant): bool
    {
        return $variant->getAttribute('state') === 'available'
            && $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
            && $variant->getAttribute('mime_type') === 'image/webp';
    }

    public static function isVideoPoster(MediaVariant $variant): bool
    {
        return $variant->getAttribute('state') === 'available'
            && $variant->getAttribute('variant_kind') === MediaIngestService::VIDEO_POSTER_KIND
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

        if ($variants->count() !== 1) {
            return null;
        }

        /** @var MediaVariant $variant */
        $variant = $variants->first();
        $storageKey = (string) $variant->getAttribute('storage_key');
        $expectedBytes = (int) $variant->getAttribute('byte_size');
        if ($storageKey === '' || $expectedBytes < 1) {
            return null;
        }

        // An available registry row is not a usable thumbnail if its bytes
        // are missing or truncated. Never hide this with HTTP regeneration.
        try {
            $disk = Storage::disk((string) config('media.disk'));

            return $disk->exists($storageKey)
                && (int) $disk->size($storageKey) === $expectedBytes
                ? $variant : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
