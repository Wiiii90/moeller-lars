<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkPrimaryMediaService
{
    public function __construct(
        private readonly MediaIngestService $ingest,
        private readonly AdminAuditService $audit,
    ) {}

    public function attachUpload(Artwork $artwork, UploadedFile $upload): Artwork
    {
        $asset = $this->ingest->ingest($upload);
        $result = $this->attachAsset($artwork, $asset, recordIngest: true);

        return $result;
    }

    public function attachAsset(Artwork $artwork, MediaAsset $asset, bool $recordIngest = false): Artwork
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($artwork, $asset, $recordIngest, $actor): Artwork {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            /** @var MediaAsset $lockedAsset */
            $lockedAsset = MediaAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $this->assertEligible($lockedAsset);

            if ($lockedArtwork->artworkMedia()->where('role', 'primary')->exists()) {
                throw ValidationException::withMessages(['primary_media' => 'This artwork already has primary media.']);
            }

            ArtworkMedia::query()->create([
                'artwork_id' => $lockedArtwork->getKey(),
                'media_asset_id' => $lockedAsset->getKey(),
                'role' => 'primary',
                'position' => 0,
                'alt_text_override' => null,
            ]);

            if ($recordIngest) {
                $this->audit->record($actor, 'media.ingested', 'media_asset', $lockedAsset->getKey(), [
                    'artwork_id' => (int) $lockedArtwork->getKey(),
                ]);
            }
            $this->audit->record($actor, 'artwork.primary_media_attached', 'artwork', $lockedArtwork->getKey(), [
                'media_asset_id' => (int) $lockedAsset->getKey(),
            ]);

            return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset.variants']);
        });
    }

    public function replaceUpload(Artwork $artwork, UploadedFile $upload): Artwork
    {
        $asset = $this->ingest->ingest($upload);

        return $this->replaceAsset($artwork, $asset, recordIngest: true);
    }

    public function replaceAsset(Artwork $artwork, MediaAsset $asset, bool $recordIngest = false): Artwork
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($artwork, $asset, $recordIngest, $actor): Artwork {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedArtwork->getAttribute('state') === 'published') {
                throw ValidationException::withMessages([
                    'primary_media' => 'Unpublish the artwork before replacing its primary media.',
                ]);
            }

            /** @var MediaAsset $lockedAsset */
            $lockedAsset = MediaAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $this->assertEligible($lockedAsset);

            $primaries = ArtworkMedia::query()
                ->where('artwork_id', $lockedArtwork->getKey())
                ->where('role', 'primary')
                ->lockForUpdate()
                ->get();
            if ($primaries->count() !== 1) {
                throw ValidationException::withMessages([
                    'primary_media' => 'This artwork does not have one replaceable primary media item.',
                ]);
            }

            /** @var ArtworkMedia $primary */
            $primary = $primaries->first();
            if ((int) $primary->getAttribute('media_asset_id') === (int) $lockedAsset->getKey()) {
                return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset.variants']);
            }

            if (ArtworkMedia::query()
                ->where('artwork_id', $lockedArtwork->getKey())
                ->where('media_asset_id', $lockedAsset->getKey())
                ->where('id', '<>', $primary->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'primary_media' => 'This media is already attached to the artwork.',
                ]);
            }

            $primary->forceFill([
                'media_asset_id' => $lockedAsset->getKey(),
                'alt_text_override' => null,
            ])->save();

            if ($recordIngest) {
                $this->audit->record($actor, 'media.ingested', 'media_asset', $lockedAsset->getKey(), [
                    'artwork_id' => (int) $lockedArtwork->getKey(),
                ]);
            }
            $this->audit->record($actor, 'artwork.primary_media_replaced', 'artwork', $lockedArtwork->getKey(), [
                'media_asset_id' => (int) $lockedAsset->getKey(),
            ]);

            // Media lifecycle is independent from Artwork linkage. The old asset is intentionally
            // left in Media Files, even when this replacement made it unreferenced.
            return $lockedArtwork->fresh(['category', 'artworkMedia.mediaAsset.variants']);
        });
    }

    private function assertEligible(MediaAsset $asset): void
    {
        $mime = (string) $asset->getAttribute('mime_type');
        if (
            $asset->getAttribute('state') !== 'available'
            || (! MediaTypePolicy::isImage($mime) && ! MediaTypePolicy::isVideo($mime))
        ) {
            throw ValidationException::withMessages([
                'primary_media' => 'Primary media must be an available image or video.',
            ]);
        }
    }
}
