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
use RuntimeException;
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
        $altText = $mediaAsset?->getAttribute('alt_text');
        $publicThumbnails = $mediaAsset?->variants()
            ->where('variant_kind', 'thumbnail')
            ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
            ->where('state', 'available')
            ->get();

        if (
            ! $category
            || $category->getAttribute('state') !== 'published'
            || ! $primary
            || ! $mediaAsset
            || $mediaAsset->getAttribute('state') !== 'available'
            || ! is_string($altText)
            || trim($altText) === ''
            || $publicThumbnails?->count() !== 1
        ) {
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
            $this->cleanupFailedIngestAndRethrow($asset, $exception);
        }

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
    }

    public function replacePrimaryMedia(Artwork $artwork, UploadedFile $upload): Artwork
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        /** @var Collection<int, ArtworkMedia> $primaries */
        $primaries = $fresh->artworkMedia()->where('role', 'primary')->with('mediaAsset')->get();
        $primary = $primaries->count() === 1 ? $primaries->first() : null;
        $oldAsset = $primary instanceof ArtworkMedia ? $primary->getRelation('mediaAsset') : null;

        if (! $primary || ! $oldAsset instanceof MediaAsset) {
            throw ValidationException::withMessages(['media' => 'This artwork does not have valid primary media.']);
        }

        $newAsset = $this->mediaIngestService->ingest($upload);
        $oldKeys = $this->storageKeys($oldAsset);
        $oldAssetDeleted = false;

        try {
            $oldAssetDeleted = DB::transaction(function () use ($fresh, $oldAsset, $newAsset, $actor): bool {
                /** @var Artwork $lockedArtwork */
                $lockedArtwork = Artwork::query()->whereKey($fresh->getKey())->lockForUpdate()->firstOrFail();
                /** @var Collection<int, ArtworkMedia> $lockedPrimaries */
                $lockedPrimaries = $lockedArtwork->artworkMedia()->where('role', 'primary')->lockForUpdate()->get();
                $lockedPrimary = $lockedPrimaries->count() === 1 ? $lockedPrimaries->first() : null;

                if (! $lockedPrimary) {
                    throw new RuntimeException('The artwork primary media changed during replacement.');
                }

                if ((int) $lockedPrimary->getAttribute('media_asset_id') !== (int) $oldAsset->getKey()) {
                    throw new RuntimeException('The artwork primary media changed during replacement.');
                }

                /** @var MediaAsset|null $lockedOldAsset */
                $lockedOldAsset = MediaAsset::query()->whereKey($oldAsset->getKey())->lockForUpdate()->first();
                if (! $lockedOldAsset) {
                    throw new RuntimeException('The previous primary media no longer exists.');
                }

                $lockedPrimary->forceFill([
                    'media_asset_id' => $newAsset->getKey(),
                    'alt_text_override' => null,
                ])->save();

                $this->adminAuditService->record($actor, 'media.ingested', 'media_asset', $newAsset->getKey(), [
                    'artwork_id' => $lockedArtwork->getKey(),
                ]);
                $this->adminAuditService->record($actor, 'artwork.primary_media_replaced', 'artwork', $lockedArtwork->getKey(), [
                    'media_asset_id' => $newAsset->getKey(),
                ]);

                if ($this->assetHasReferences($lockedOldAsset)) {
                    return false;
                }

                $lockedOldAsset->variants()->update(['state' => 'deleted']);
                $lockedOldAsset->forceFill(['state' => 'deleted'])->save();
                $this->adminAuditService->record($actor, 'media.deleted', 'media_asset', $lockedOldAsset->getKey());

                return true;
            });
        } catch (Throwable $exception) {
            $this->cleanupFailedIngestAndRethrow($newAsset, $exception);
        }

        if ($oldAssetDeleted) {
            $this->deleteStorageKeys($oldKeys);
        }

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
    }

    /** @return list<string> */
    private function storageKeys(MediaAsset $asset): array
    {
        $variantKeys = array_map(static fn (mixed $key): string => (string) $key, $asset->variants()->pluck('storage_key')->all());

        return [(string) $asset->getAttribute('storage_key'), ...$variantKeys];
    }

    private function assetHasReferences(MediaAsset $asset): bool
    {
        $id = $asset->getKey();

        return DB::table('artwork_media')->where('media_asset_id', $id)->exists()
            || DB::table('exhibitions')->where('hero_media_asset_id', $id)->exists()
            || DB::table('cv_entries')->where('image_media_asset_id', $id)->exists()
            || DB::table('blog_posts')->where('cover_media_asset_id', $id)->exists();
    }

    /** @param list<string> $keys */
    private function deleteStorageKeys(array $keys): void
    {
        $disk = Storage::disk(config('media.disk'));
        $failed = [];

        foreach ($keys as $key) {
            try {
                if ($disk->exists($key) && ! $disk->delete($key)) {
                    $failed[] = $key;

                    continue;
                }
                if ($disk->exists($key)) {
                    $failed[] = $key;
                }
            } catch (Throwable) {
                $failed[] = $key;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException('Media storage cleanup failed for: '.implode(', ', array_unique($failed)));
        }
    }

    private function removeIngestedAsset(MediaAsset $asset): void
    {
        $variants = $asset->variants()->get();
        $variantKeys = array_map(static fn (mixed $key): string => (string) $key, $variants->pluck('storage_key')->all());
        $keys = [(string) $asset->getAttribute('storage_key'), ...$variantKeys];

        DB::transaction(function () use ($asset): void {
            $asset->variants()->delete();
            $asset->delete();
        });

        $this->deleteStorageKeys($keys);
    }

    private function cleanupFailedIngestAndRethrow(MediaAsset $asset, Throwable $original): never
    {
        try {
            $this->removeIngestedAsset($asset);
        } catch (Throwable $cleanupFailure) {
            throw new RuntimeException(
                'Media operation failed and cleanup also failed. Original failure: '.$original->getMessage(),
                0,
                $cleanupFailure,
            );
        }

        throw $original;
    }
}
