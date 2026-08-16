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

        $disk = Storage::disk(config('media.disk'));
        $issues = [];
        $originalKey = (string) $fresh->getAttribute('storage_key');
        if ($fresh->getAttribute('state') === 'deleted') {
            if ($this->exists($disk, $originalKey)) {
                $issues[] = 'deleted_original_present';
            }
        } else {
            $issues = [...$issues, ...$this->checkFile($disk, $originalKey, $fresh, 'original')];
        }

        foreach ($fresh->getRelationValue('variants') as $variant) {
            $id = (int) $variant->getKey();
            $key = 'variant:'.$id.':';
            $state = (string) $variant->getAttribute('state');
            $variantKey = (string) $variant->getAttribute('storage_key');
            if ($state === 'deleted') {
                if ($this->exists($disk, $variantKey)) {
                    $issues[] = $key.'deleted_file_present';
                }

                continue;
            }
            if ($state === 'stale' && ! $this->exists($disk, $variantKey)) {
                continue;
            }
            $issues = [...$issues, ...$this->checkFile($disk, $variantKey, $variant, $key)];
            if (
                $state === 'available'
                && $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                && $variant->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
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

        return $issues;
    }

    /** @return list<string> */
    private function checkFile(mixed $disk, string $key, mixed $record, string $prefix): array
    {
        if (! $this->exists($disk, $key)) {
            return [$prefix === 'original' ? 'original_missing' : $prefix.'missing'];
        }

        try {
            $bytes = $disk->get($key);
        } catch (Throwable) {
            return [$prefix === 'original' ? 'original_unreadable' : $prefix.'unreadable'];
        }
        if (! is_string($bytes)) {
            return [$prefix === 'original' ? 'original_unreadable' : $prefix.'unreadable'];
        }

        $issues = [];
        if (strlen($bytes) !== (int) $record->getAttribute('byte_size')) {
            $issues[] = $prefix === 'original' ? 'original_size_mismatch' : $prefix.'size_mismatch';
        }
        if (hash('sha256', $bytes) !== (string) $record->getAttribute('sha256')) {
            $issues[] = $prefix === 'original' ? 'original_checksum_mismatch' : $prefix.'checksum_mismatch';
        }
        $mime = $this->mime($bytes);
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

    private function mime(string $bytes): ?string
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        if ($info === false) {
            return null;
        }
        try {
            $mime = finfo_buffer($info, $bytes);
        } finally {
            finfo_close($info);
        }

        return is_string($mime) ? $mime : null;
    }
}
