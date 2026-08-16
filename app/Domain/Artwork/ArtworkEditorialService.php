<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaIngestService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArtworkEditorialService
{
    public function __construct(
        private readonly MediaIngestService $mediaIngestService,
        private readonly AdminAuditService $adminAuditService,
    ) {}

    public function publish(Artwork $artwork): Artwork
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->with(['category', 'artworkMedia.mediaAsset'])->findOrFail($artwork->getKey());
        /** @var Collection<int, ArtworkMedia> $primaries */
        $primaries = $fresh->artworkMedia()->where('role', 'primary')->with('mediaAsset')->get();
        $primary = $primaries->count() === 1 ? $primaries->first() : null;
        $category = $fresh->category()->first();
        $mediaAsset = $primary?->getRelation('mediaAsset');

        if (! $category || $category->getAttribute('state') !== 'published' || ! $primary || ! $mediaAsset || $mediaAsset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['state' => 'This artwork is not ready to publish.']);
        }

        $wasPublished = $fresh->getAttribute('state') === 'published';
        DB::transaction(function () use ($fresh, $actor, $wasPublished): void {
            $fresh->setAttribute('state', 'published');
            $fresh->setAttribute('published_at', $fresh->getAttribute('published_at') ?? now());
            $fresh->save();

            if (! $wasPublished) {
                $this->adminAuditService->record($actor, 'artwork.published', 'artwork', $fresh->getKey());
            }
        });

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
    }

    public function unpublish(Artwork $artwork): Artwork
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        $wasPublished = $fresh->getAttribute('state') === 'published';
        DB::transaction(function () use ($fresh, $actor, $wasPublished): void {
            $fresh->setAttribute('state', 'draft');
            $fresh->save();

            if ($wasPublished) {
                $this->adminAuditService->record($actor, 'artwork.unpublished', 'artwork', $fresh->getKey());
            }
        });

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
    }

    public function attachPrimaryMedia(Artwork $artwork, UploadedFile $upload): Artwork
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        if ($fresh->artworkMedia()->where('role', 'primary')->exists()) {
            throw ValidationException::withMessages(['media' => 'This artwork already has primary media.']);
        }

        $asset = $this->mediaIngestService->ingest($upload);

        try {
            DB::transaction(function () use ($fresh, $asset, $actor): void {
                $artworkMedia = new ArtworkMedia;
                $artworkMedia->fill([
                    'artwork_id' => $fresh->getKey(),
                    'media_asset_id' => $asset->getKey(),
                    'role' => 'primary',
                    'position' => 0,
                    'alt_text_override' => null,
                ]);
                $artworkMedia->save();

                $this->adminAuditService->record($actor, 'media.ingested', 'media_asset', $asset->getKey(), [
                    'artwork_id' => $fresh->getKey(),
                ]);
                $this->adminAuditService->record($actor, 'artwork.primary_media_attached', 'artwork', $fresh->getKey(), [
                    'media_asset_id' => $asset->getKey(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->removeIngestedAsset($asset);

            throw $exception;
        }

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
    }

    private function removeIngestedAsset(MediaAsset $asset): void
    {
        $variants = $asset->variants()->get();
        $variantKeys = array_map(static fn (mixed $key): string => (string) $key, $variants->pluck('storage_key')->all());
        $keys = [(string) $asset->getAttribute('storage_key'), ...$variantKeys];

        try {
            DB::transaction(function () use ($asset): void {
                $asset->variants()->delete();
                $asset->delete();
            });
        } catch (Throwable) {
            // Cleanup is best effort and must not mask the insert exception.
        }

        try {
            Storage::disk(config('media.disk'))->delete($keys);
        } catch (Throwable) {
            // Storage cleanup is best effort and must not mask the insert exception.
        }
    }
}
