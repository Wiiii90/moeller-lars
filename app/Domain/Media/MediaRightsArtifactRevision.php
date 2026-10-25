<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use InvalidArgumentException;

/**
 * Immutable public-file identity. Any change in source, effective file rights,
 * ExifTool writing policy, or thumbnail transform creates a new revision.
 */
final class MediaRightsArtifactRevision
{
    public const CONTRACT = 'media-iptc-xmp-v1/exiftool-13.55';

    public function __construct(private readonly MediaIptcXmpWriter $writer) {}

    public function forSource(
        MediaAsset $asset,
        MediaAsset|MediaVariant $source,
        bool $mayReserveDataMining = false,
    ): string {
        $assetId = $asset->getKey();
        $sha = $source->getAttribute('sha256');
        $mime = $source->getAttribute('mime_type');
        $assetMime = $asset->getAttribute('mime_type');
        if (! is_string($assetMime) || ! MediaTypePolicy::isImage($assetMime)) {
            throw new InvalidArgumentException('File rights artifacts require an image media asset.');
        }

        if (! is_numeric($assetId) || (int) $assetId <= 0
            || ! is_string($sha) || ! preg_match('/^[a-f0-9]{64}$/D', $sha)
            || ! is_string($mime) || ! MediaTypePolicy::isImage($mime)) {
            throw new InvalidArgumentException('A persisted image and recorded source checksum are required.');
        }

        $isVariant = $source instanceof MediaVariant;
        if ($isVariant && (
            (int) $source->getAttribute('media_asset_id') !== (int) $assetId
            || $source->getAttribute('variant_kind') !== PublicMedia::THUMBNAIL_KIND
            || $source->getAttribute('transform_profile') !== PublicMedia::PUBLIC_TRANSFORM_PROFILE
            || $mime !== 'image/webp'
        )) {
            throw new InvalidArgumentException('The rights-bearing thumbnail must match its image and canonical transform.');
        }

        return hash('sha256', json_encode([
            'contract' => self::CONTRACT,
            'asset_id' => (int) $assetId,
            'role' => $isVariant ? 'thumbnail' : 'original',
            'transform' => $isVariant ? PublicMedia::PUBLIC_TRANSFORM_PROFILE : null,
            'source_sha256' => $sha,
            'source_mime' => $mime,
            'tags' => $this->writer->tagsForAsset($asset, $mayReserveDataMining),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
