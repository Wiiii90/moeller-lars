<?php

namespace App\Domain\Media;

use App\Domain\Publication\MediaRightsPublicationSelections;
use App\Domain\Publication\PublicationSnapshot;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prepares one Working image through the canonical writer and publication
 * lock. Invoked by offline commands or the existing application queue.
 */
final class MediaRightsWorkingPreparation
{
    public function __construct(
        private readonly MediaRightsArtifactService $artifacts,
        private readonly MediaRightsPublicationSelections $selections,
    ) {}

    public function prepare(int $assetId): bool
    {
        if ($assetId <= 0 || ! $this->selections->enabled()) {
            throw new RuntimeException('Offline rights preparation must be authorized separately from public delivery.');
        }

        return DB::transaction(function () use ($assetId): bool {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $asset = MediaAsset::query()->whereKey($assetId)->lockForUpdate()->first();

            if (! $asset instanceof MediaAsset
                || $asset->getAttribute('state') !== 'available'
                || ! MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))) {
                return false;
            }

            $thumbnail = MediaPreviewVariant::imageThumbnail($asset);
            if ($thumbnail === null) {
                return false;
            }
            $originalArtifact = $this->artifacts->prepareOriginal($asset);
            $thumbnailArtifact = $this->artifacts->prepareThumbnail($asset, $thumbnail);

            $this->selections->selectWorking($originalArtifact, 'original', $assetId);
            $this->selections->selectWorking($thumbnailArtifact, 'thumbnail', (int) $thumbnail->getKey());

            return true;
        }, attempts: 1);
    }
}
