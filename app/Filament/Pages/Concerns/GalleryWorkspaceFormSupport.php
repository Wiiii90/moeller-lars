<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Media\MediaTypePolicy;

trait GalleryWorkspaceFormSupport
{
    /** @return list<mixed> */
    private function artworkCreateFormSchema(): array
    {
        // Create and Edit share one semantic field schema. Create alone checks
        // slug uniqueness before committing a new artwork.
        return app(\App\Filament\Support\ArtworkEditDialog::class)->schema(creating: true);
    }

    /** @return array<string, mixed> */
    private function normalizeArtworkFormData(array $data): array
    {
        $data['artwork_category_id'] = (int) $this->galleryContext['id'];
        $data['work_date'] = $data['work_date'] ?? null;
        $data['medium'] = filled($data['medium'] ?? null) ? trim((string) $data['medium']) : null;
        $data['dimensions'] = ArtworkDimensions::compose(
            $data['dimension_height'] ?? null,
            $data['dimension_width'] ?? null,
            $data['dimension_depth'] ?? null,
            $data['dimension_unit'] ?? 'cm',
            $data['dimension_custom'] ?? null,
        );

        foreach ([
            'dimension_height',
            'dimension_width',
            'dimension_depth',
            'dimension_unit',
            'dimension_custom',
            'primary_media_asset_id',
            'primary_upload',
            'primary_alt_text',
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /** @return list<string> */
    private static function primaryMimeTypes(): array
    {
        return [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES];
    }

}
