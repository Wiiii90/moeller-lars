<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaIntegrityService
{
    /** @return list<string> */
    public function issues(MediaAsset $asset): array
    {
        $fresh = $asset->fresh(['variants']);
        if (! $fresh instanceof MediaAsset) {
            return ['original_missing'];
        }

        // Deleted media may be physically retained by Committed or retained
        // publication/Undo state. Such bytes are owned, not filesystem orphans.
        // The canonical cleanup service, not this active-media integrity
        // checker, determines when they can actually be reclaimed.
        if ($fresh->getAttribute('state') === 'deleted') {
            return [];
        }

        $disk = Storage::disk(config('media.disk'));
        $issues = [];
        $originalKey = (string) $fresh->getAttribute('storage_key');
        $issues = [...$issues, ...$this->checkFile($disk, $originalKey, $fresh, 'original')];

        foreach ($fresh->getRelationValue('variants') as $variant) {
            $id = (int) $variant->getKey();
            $key = 'variant:'.$id.':';
            $state = (string) $variant->getAttribute('state');
            $variantKey = (string) $variant->getAttribute('storage_key');
            if ($state === 'deleted') {
                // A retained published checkpoint may still own this file.
                continue;
            }
            if ($state === 'stale' && ! $this->exists($disk, $variantKey)) {
                continue;
            }
            $issues = [...$issues, ...$this->checkFile($disk, $variantKey, $variant, $key)];
            if (
                $state === 'available'
                && in_array((string) $variant->getAttribute('variant_kind'), [
                    MediaIngestService::THUMBNAIL_KIND,
                    MediaIngestService::VIDEO_POSTER_KIND,
                ], true)
            ) {
                if ($variant->getAttribute('mime_type') !== 'image/webp') {
                    $issues[] = $key.'public_thumbnail_mime_invalid';
                }
                $width = $variant->getAttribute('width');
                $height = $variant->getAttribute('height');
                if (! is_int($width) || ! is_int($height) || $width <= 0 || $height <= 0 || max($width, $height) > MediaIngestService::THUMBNAIL_MAX_EDGE) {
                    $issues[] = $key.'public_thumbnail_dimensions_invalid';
                }
            }
        }

        $mime = (string) $fresh->getAttribute('mime_type');
        if ($fresh->getAttribute('state') === 'available' && MediaTypePolicy::isImage($mime)
            && MediaPreviewVariant::imageThumbnail($fresh) === null) {
            $issues[] = 'required_image_thumbnail_unavailable';
        }
        if ($fresh->getAttribute('state') === 'available' && MediaTypePolicy::isVideo($mime)
            && MediaPreviewVariant::videoPoster($fresh) === null) {
            $issues[] = 'required_video_poster_unavailable';
        }

        return array_values(array_unique($issues));
    }

    /** @return list<string> */
    private function checkFile(mixed $disk, string $key, mixed $record, string $prefix): array
    {
        if (! $this->exists($disk, $key)) {
            return [$prefix === 'original' ? 'original_missing' : $prefix.'missing'];
        }

        $issues = [];
        $readableError = $prefix === 'original' ? 'original_unreadable' : $prefix.'unreadable';
        try {
            $size = $disk->size($key);
            $stream = $disk->readStream($key);
            if (! is_resource($stream)) {
                return [$readableError];
            }
            try {
                $hash = hash_init('sha256');
                $hashed = hash_update_stream($hash, $stream);
                if (! is_int($hashed) || $hashed !== (int) $size) {
                    return [$readableError];
                }
                $sha256 = hash_final($hash);
            } finally {
                fclose($stream);
            }
            $mime = $this->mimeFromPath($disk->path($key));
        } catch (Throwable) {
            return [$readableError];
        }

        if ($size !== (int) $record->getAttribute('byte_size')) {
            $issues[] = $prefix === 'original' ? 'original_size_mismatch' : $prefix.'size_mismatch';
        }
        if (! hash_equals((string) $record->getAttribute('sha256'), $sha256)) {
            $issues[] = $prefix === 'original' ? 'original_checksum_mismatch' : $prefix.'checksum_mismatch';
        }
        if ($mime !== (string) $record->getAttribute('mime_type')) {
            $issues[] = $prefix === 'original' ? 'original_mime_mismatch' : $prefix.'mime_mismatch';
        }

        return $issues;
    }

    private function exists(mixed $disk, string $key): bool
    {
        try {
            return $key !== '' && $disk->exists($key);
        } catch (Throwable) {
            return false;
        }
    }

    private function mimeFromPath(string $path): ?string
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        if ($info === false) {
            return null;
        }
        try {
            $mime = finfo_file($info, $path);
        } finally {
            finfo_close($info);
        }

        return is_string($mime) ? $mime : null;
    }
}
