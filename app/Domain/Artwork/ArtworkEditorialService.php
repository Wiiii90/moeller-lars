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

    public function ingestAdditionalMedia(Artwork $artwork, UploadedFile $upload): ArtworkMedia
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        $asset = $this->mediaIngestService->ingest($upload);

        try {
            return DB::transaction(function () use ($fresh, $asset, $actor): ArtworkMedia {
                $usage = $this->attachAdditionalAsset($fresh, $asset);
                $this->adminAuditService->record($actor, 'media.ingested', 'media_asset', $asset->getKey(), [
                    'artwork_id' => $fresh->getKey(),
                ]);
                $this->adminAuditService->record($actor, 'artwork.additional_media_attached', 'artwork', $fresh->getKey(), [
                    'media_asset_id' => $asset->getKey(),
                    'artwork_media_id' => $usage->getKey(),
                    'position' => (int) $usage->getAttribute('position'),
                ]);

                return $usage;
            });
        } catch (Throwable $exception) {
            $this->cleanupFailedIngestAndRethrow($asset, $exception);
        }
    }

    public function attachAdditionalMedia(Artwork $artwork, MediaAsset $asset): ArtworkMedia
    {
        return $this->attachAdditionalMediaAtPosition($artwork, $asset, null);
    }

    public function restoreAdditionalMedia(Artwork $artwork, MediaAsset $asset, int $position): ArtworkMedia
    {
        if ($position < 1) {
            throw ValidationException::withMessages(['position' => 'The restored gallery image position is invalid.']);
        }

        return $this->attachAdditionalMediaAtPosition($artwork, $asset, $position);
    }

    public function detachAdditionalMedia(Artwork $artwork, ArtworkMedia $usage): void
    {
        $actor = $this->adminAuditService->requireActor();

        DB::transaction(function () use ($artwork, $usage, $actor): void {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, ArtworkMedia> $additional */
            $additional = ArtworkMedia::query()
                ->where('artwork_id', $lockedArtwork->getKey())
                ->where('role', 'additional')
                ->orderBy('position')
                ->lockForUpdate()
                ->get();
            $index = $additional->search(fn (ArtworkMedia $candidate): bool => (int) $candidate->getKey() === (int) $usage->getKey());

            if ($index === false) {
                throw ValidationException::withMessages(['media' => 'Only an additional artwork image can be detached here.']);
            }

            /** @var ArtworkMedia $lockedUsage */
            $lockedUsage = $additional->get($index);
            $assetId = (int) $lockedUsage->getAttribute('media_asset_id');
            $usageId = (int) $lockedUsage->getKey();
            $position = (int) $lockedUsage->getAttribute('position');
            $previous = $index > 0 ? $additional->get($index - 1) : null;
            $next = $index < $additional->count() - 1 ? $additional->get($index + 1) : null;

            $lockedUsage->delete();
            $this->normalizeAdditionalPositions($lockedArtwork);

            $metadata = [
                'media_asset_id' => $assetId,
                'artwork_media_id' => $usageId,
                'position' => $position,
            ];
            if ($previous instanceof ArtworkMedia) {
                $metadata['previous_artwork_media_id'] = (int) $previous->getKey();
            }
            if ($next instanceof ArtworkMedia) {
                $metadata['next_artwork_media_id'] = (int) $next->getKey();
            }

            $this->adminAuditService->record(
                $actor,
                'artwork.additional_media_detached',
                'artwork',
                $lockedArtwork->getKey(),
                $metadata,
            );
        });
    }

    public function moveAdditionalMedia(Artwork $artwork, ArtworkMedia $usage, string $direction): void
    {
        $actor = $this->adminAuditService->requireActor();
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['position' => 'The gallery move direction is invalid.']);
        }

        DB::transaction(function () use ($artwork, $usage, $direction, $actor): void {
            /** @var Artwork $lockedArtwork */
            $lockedArtwork = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, ArtworkMedia> $additional */
            $additional = ArtworkMedia::query()
                ->where('artwork_id', $lockedArtwork->getKey())
                ->where('role', 'additional')
                ->orderBy('position')
                ->lockForUpdate()
                ->get();

            $index = $additional->search(fn (ArtworkMedia $candidate): bool => (int) $candidate->getKey() === (int) $usage->getKey());
            if ($index === false) {
                throw ValidationException::withMessages(['media' => 'The gallery image no longer belongs to this artwork.']);
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($target < 0 || $target >= $additional->count()) {
                return;
            }

            /** @var ArtworkMedia $moving */
            $moving = $additional->get($index);
            /** @var ArtworkMedia $neighbor */
            $neighbor = $additional->get($target);
            $fromPosition = (int) $moving->getAttribute('position');
            $toPosition = (int) $neighbor->getAttribute('position');

            $ordered = $additional->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            /** @var Collection<int, ArtworkMedia> $reordered */
            $reordered = new Collection(array_values($ordered));
            $this->normalizeAdditionalPositions($lockedArtwork, $reordered);

            $this->adminAuditService->record($actor, 'artwork.additional_media_reordered', 'artwork', $lockedArtwork->getKey(), [
                'artwork_media_id' => (int) $moving->getKey(),
                'neighbor_artwork_media_id' => (int) $neighbor->getKey(),
                'from_position' => $fromPosition,
                'to_position' => $toPosition,
                'direction' => $direction,
            ]);
        });
    }

    /** @return list<string> */
    private function storageKeys(MediaAsset $asset): array
    {
        $variantKeys = array_map(static fn (mixed $key): string => (string) $key, $asset->variants()->pluck('storage_key')->all());

        return [(string) $asset->getAttribute('storage_key'), ...$variantKeys];
    }

    private function attachAdditionalMediaAtPosition(Artwork $artwork, MediaAsset $asset, ?int $position): ArtworkMedia
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        /** @var MediaAsset $freshAsset */
        $freshAsset = MediaAsset::query()->findOrFail($asset->getKey());

        if ($freshAsset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['media' => 'Only available media can be attached to an artwork.']);
        }

        return DB::transaction(function () use ($fresh, $freshAsset, $position, $actor): ArtworkMedia {
            $usage = $this->attachAdditionalAsset($fresh, $freshAsset, $position);
            $this->adminAuditService->record($actor, 'artwork.additional_media_attached', 'artwork', $fresh->getKey(), [
                'media_asset_id' => $freshAsset->getKey(),
                'artwork_media_id' => $usage->getKey(),
                'position' => (int) $usage->getAttribute('position'),
            ]);

            return $usage;
        });
    }

    private function attachAdditionalAsset(Artwork $artwork, MediaAsset $asset, ?int $position = null): ArtworkMedia
    {
        /** @var Artwork $lockedArtwork */
        $lockedArtwork = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
        /** @var MediaAsset $lockedAsset */
        $lockedAsset = MediaAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

        if ($lockedAsset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['media' => 'Only available media can be attached to an artwork.']);
        }
        if (ArtworkMedia::query()->where('artwork_id', $lockedArtwork->getKey())->where('media_asset_id', $lockedAsset->getKey())->exists()) {
            throw ValidationException::withMessages(['media' => 'This media is already attached to the artwork.']);
        }

        /** @var Collection<int, ArtworkMedia> $additional */
        $additional = ArtworkMedia::query()
            ->where('artwork_id', $lockedArtwork->getKey())
            ->where('role', 'additional')
            ->orderBy('position')
            ->lockForUpdate()
            ->get();
        $targetPosition = $position ?? $additional->count() + 1;

        if ($targetPosition < 1 || $targetPosition > $additional->count() + 1) {
            throw ValidationException::withMessages(['position' => 'The restored gallery image position is no longer available.']);
        }

        if ($targetPosition === $additional->count() + 1) {
            $usage = new ArtworkMedia;
            $usage->fill([
                'artwork_id' => $lockedArtwork->getKey(),
                'media_asset_id' => $lockedAsset->getKey(),
                'role' => 'additional',
                'position' => $targetPosition,
                'alt_text_override' => null,
            ]);
            $usage->save();

            return $usage->fresh(['mediaAsset.variants']);
        }

        $temporaryPosition = ((int) ArtworkMedia::query()
            ->where('artwork_id', $lockedArtwork->getKey())
            ->max('position')) + $additional->count() + 2;
        $usage = new ArtworkMedia;
        $usage->fill([
            'artwork_id' => $lockedArtwork->getKey(),
            'media_asset_id' => $lockedAsset->getKey(),
            'role' => 'additional',
            'position' => $temporaryPosition,
            'alt_text_override' => null,
        ]);
        $usage->save();

        $ordered = $additional->values()->all();
        array_splice($ordered, $targetPosition - 1, 0, [$usage]);
        /** @var Collection<int, ArtworkMedia> $reordered */
        $reordered = new Collection(array_values($ordered));
        $this->normalizeAdditionalPositions($lockedArtwork, $reordered);

        return $usage->fresh(['mediaAsset.variants']);
    }

    /** @param Collection<int, ArtworkMedia>|null $ordered */
    private function normalizeAdditionalPositions(Artwork $artwork, ?Collection $ordered = null): void
    {
        /** @var Collection<int, ArtworkMedia> $additional */
        $additional = $ordered ?? ArtworkMedia::query()
            ->where('artwork_id', $artwork->getKey())
            ->where('role', 'additional')
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        if ($additional->isEmpty()) {
            return;
        }

        $temporaryPosition = ((int) ArtworkMedia::query()
            ->where('artwork_id', $artwork->getKey())
            ->max('position')) + $additional->count() + 1;

        foreach ($additional->values() as $index => $usage) {
            $usage->forceFill(['position' => $temporaryPosition + $index])->save();
        }

        foreach ($additional->values() as $index => $usage) {
            $usage->forceFill(['position' => $index + 1])->save();
        }
    }

    private function assetHasReferences(MediaAsset $asset): bool
    {
        $id = $asset->getKey();

        return DB::table('artwork_media')->where('media_asset_id', $id)->exists()
            || DB::table('exhibition_media')->where('media_asset_id', $id)->exists()
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
