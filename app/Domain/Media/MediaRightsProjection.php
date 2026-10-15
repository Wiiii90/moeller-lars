<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;

/**
 * Canonical rights facts for image-file metadata and search metadata.
 * Neither the file creator nor its rights holder is guessed from the artwork.
 */
final class MediaRightsProjection
{
    /** @return array{creator:?string,rights_holder:?string,credit:?string,copyright_notice:?string,usage_terms:?string,license_inquiry_url:?string} */
    public function forAsset(MediaAsset $asset): array
    {
        return [
            'creator' => $this->clean($asset->getAttribute('file_creator')),
            'rights_holder' => $this->clean($asset->getAttribute('file_rights_holder')),
            'credit' => $this->clean($asset->getAttribute('credit')),
            'copyright_notice' => $asset->effectiveCopyrightNotice(),
            'usage_terms' => $this->clean($asset->getAttribute('rights_usage_terms')),
            'license_inquiry_url' => $this->clean($asset->getAttribute('license_inquiry_url')),
        ];
    }

    private function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
