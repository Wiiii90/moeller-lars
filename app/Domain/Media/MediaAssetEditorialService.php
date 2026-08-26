<?php

namespace App\Domain\Media;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\JournalEntryMediaService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaAssetEditorialService
{
    public function __construct(
        private readonly AdminAuditService $adminAuditService,
        private readonly MediaReferenceQuery $referenceQuery,
        private readonly JournalEntryMediaService $journalMedia,
    ) {}

    public function updateMetadata(MediaAsset $asset, array $data): MediaAsset
    {
        $actor = $this->adminAuditService->requireActor();
        $fresh = $asset->fresh();
        if (! $fresh instanceof MediaAsset || $fresh->getAttribute('state') === 'deleted') {
            throw ValidationException::withMessages(['media' => 'Deleted media cannot be edited.']);
        }

        $allowed = ['alt_text', 'credit', 'copyright_notice', 'copyright_notice_mode'];
        if (array_diff(array_keys($data), $allowed) !== []) {
            throw ValidationException::withMessages(['media' => 'Only media editorial metadata can be changed.']);
        }

        $values = [];
        if (array_key_exists('alt_text', $data)) {
            $values['alt_text'] = $this->plainText($data['alt_text'], 500, false);
        }
        if (array_key_exists('credit', $data)) {
            $values['credit'] = $this->plainText($data['credit'], 240, true);
        }
        if (array_key_exists('copyright_notice_mode', $data)) {
            $mode = $data['copyright_notice_mode'];
            if (! is_string($mode) || ! in_array($mode, MediaAsset::COPYRIGHT_MODES, true)) {
                throw ValidationException::withMessages([
                    'copyright_notice_mode' => 'Choose whether copyright is inherited, overridden, or omitted.',
                ]);
            }
            $values['copyright_notice_mode'] = $mode;
            $values['copyright_notice'] = $mode === MediaAsset::COPYRIGHT_OVERRIDE
                ? $this->plainText($data['copyright_notice'] ?? null, 500, true)
                : null;
        } elseif (array_key_exists('copyright_notice', $data)) {
            $notice = $this->plainText($data['copyright_notice'], 500, true);
            $values['copyright_notice'] = $notice;
            $values['copyright_notice_mode'] = $notice === null
                ? MediaAsset::COPYRIGHT_INHERIT
                : MediaAsset::COPYRIGHT_OVERRIDE;
        }

        return DB::transaction(function () use ($fresh, $values, $actor): MediaAsset {
            $fresh->fill($values);
            if ($fresh->isDirty()) {
                $fresh->save();
                $this->adminAuditService->record($actor, 'media.metadata_updated', 'media_asset', $fresh->getKey());
            }

            return $fresh;
        });
    }

    public function updatePrimaryAltOverride(Artwork $artwork, ?string $altTextOverride): ArtworkMedia
    {
        $actor = $this->adminAuditService->requireActor();
        $freshArtwork = $artwork->fresh();
        if (! $freshArtwork instanceof Artwork) {
            throw ValidationException::withMessages(['media' => 'Artwork media could not be found.']);
        }

        $usage = ArtworkMedia::query()
            ->where('artwork_id', $freshArtwork->getKey())
            ->where('role', 'primary')
            ->with('mediaAsset')
            ->first();
        if (! $usage instanceof ArtworkMedia) {
            throw ValidationException::withMessages(['media' => 'A primary media asset is required.']);
        }

        $mediaAsset = $usage->getRelationValue('mediaAsset');
        if (! $mediaAsset instanceof MediaAsset || $mediaAsset->getAttribute('state') === 'deleted') {
            throw ValidationException::withMessages(['media' => 'The primary media asset is unavailable.']);
        }

        $value = $this->plainText($altTextOverride, 500, false);

        return DB::transaction(function () use ($usage, $value, $freshArtwork, $mediaAsset, $actor): ArtworkMedia {
            if ($usage->getAttribute('alt_text_override') === $value) {
                return $usage;
            }

            $usage->setAttribute('alt_text_override', $value);
            $usage->save();
            $this->adminAuditService->record(
                $actor,
                'artwork.primary_media_alt_updated',
                'artwork',
                $freshArtwork->getKey(),
                ['media_asset_id' => (int) $mediaAsset->getKey()],
            );

            return $usage;
        });
    }

    public function delete(MediaAsset $asset): bool
    {
        $actor = $this->adminAuditService->requireActor();
        $assetId = (int) $asset->getKey();
        $keys = [];

        DB::transaction(function () use ($assetId, $actor, &$keys): void {
            /** @var MediaAsset|null $locked */
            $locked = MediaAsset::query()
                ->whereKey($assetId)
                ->with('variants')
                ->lockForUpdate()
                ->first();
            if (! $locked instanceof MediaAsset) {
                throw ValidationException::withMessages(['media' => 'Media could not be found.']);
            }

            $keys = $this->storageKeys($locked);
            if ($locked->getAttribute('state') === 'deleted') {
                return;
            }

            if ($this->referenceQuery->isReferenced($locked)) {
                $this->removeCanonicalReferences($locked, $actor);
            }
            $this->removeLegacyJournalReferences($locked);

            $locked->variants()->update(['state' => 'deleted']);
            $locked->setAttribute('state', 'deleted');
            $locked->save();
            $this->adminAuditService->record($actor, 'media.deleted', 'media_asset', $locked->getKey());
        });

        $this->cleanup($keys);

        return true;
    }

    private function removeCanonicalReferences(MediaAsset $asset, User $actor): void
    {
        $assetId = (int) $asset->getKey();

        /** @var EloquentCollection<int, ArtworkMedia> $artworkUsages */
        $artworkUsages = ArtworkMedia::query()
            ->where('media_asset_id', $assetId)
            ->orderBy('artwork_id')
            ->orderBy('position')
            ->lockForUpdate()
            ->get();
        $publishedPrimaryArtworkIds = $artworkUsages
            ->filter(static fn (ArtworkMedia $usage): bool => $usage->getAttribute('role') === 'primary')
            ->pluck('artwork_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $artworkIds = $artworkUsages
            ->pluck('artwork_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($publishedPrimaryArtworkIds !== []) {
            /** @var EloquentCollection<int, Artwork> $artworks */
            $artworks = Artwork::query()
                ->whereIn('id', $publishedPrimaryArtworkIds)
                ->where('state', 'published')
                ->lockForUpdate()
                ->get();
            foreach ($artworks as $artwork) {
                $artwork->forceFill(['state' => 'draft'])->save();
                $this->adminAuditService->record($actor, 'artwork.unpublished', 'artwork', $artwork->getKey());
            }
        }

        if ($artworkUsages->isNotEmpty()) {
            ArtworkMedia::query()->where('media_asset_id', $assetId)->delete();
            foreach ($artworkIds as $artworkId) {
                $this->normalizeArtworkAdditionalPositions($artworkId);
            }
        }

        $this->journalMedia->detachAsset($asset);

        /** @var EloquentCollection<int, PublicContentSetting> $settings */
        $settings = PublicContentSetting::query()
            ->where('favicon_media_asset_id', $assetId)
            ->lockForUpdate()
            ->get();
        foreach ($settings as $setting) {
            $setting->setAttribute('favicon_media_asset_id', null);
            $setting->save();
        }

        /** @var EloquentCollection<int, CustomPageSetting> $customPages */
        $customPages = CustomPageSetting::query()->lockForUpdate()->get();
        foreach ($customPages as $customPage) {
            $components = $customPage->components();
            $filtered = array_values(array_filter(
                $components,
                static fn (array $component): bool => ! (
                    ($component['type'] ?? null) === 'image'
                    && is_numeric($component['media_asset_id'] ?? null)
                    && (int) $component['media_asset_id'] === $assetId
                ),
            ));
            if (count($filtered) === count($components)) {
                continue;
            }

            $customPage->setAttribute('blocks', $filtered);
            $customPage->save();
        }
    }

    private function removeLegacyJournalReferences(MediaAsset $asset): void
    {
        $assetId = (int) $asset->getKey();

        /** @var EloquentCollection<int, BlogPost> $posts */
        $posts = BlogPost::query()
            ->where('cover_media_asset_id', $assetId)
            ->lockForUpdate()
            ->get();
        foreach ($posts as $post) {
            $post->forceFill(['cover_media_asset_id' => null])->save();
        }

        /** @var EloquentCollection<int, ExhibitionMedia> $legacy */
        $legacy = ExhibitionMedia::query()
            ->where('media_asset_id', $assetId)
            ->orderBy('exhibition_id')
            ->orderBy('position')
            ->lockForUpdate()
            ->get();
        $exhibitionIds = $legacy
            ->pluck('exhibition_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($legacy->isNotEmpty()) {
            ExhibitionMedia::query()->where('media_asset_id', $assetId)->delete();
            foreach ($exhibitionIds as $exhibitionId) {
                $this->normalizeLegacyExhibitionPositions($exhibitionId);
            }
        }
    }

    private function normalizeArtworkAdditionalPositions(int $artworkId): void
    {
        /** @var EloquentCollection<int, ArtworkMedia> $rows */
        $rows = ArtworkMedia::query()
            ->where('artwork_id', $artworkId)
            ->where('role', 'additional')
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($rows as $index => $usage) {
            $position = $index + 1;
            if ((int) $usage->getAttribute('position') !== $position) {
                $usage->setAttribute('position', $position);
                $usage->save();
            }
        }
    }

    private function normalizeLegacyExhibitionPositions(int $exhibitionId): void
    {
        /** @var EloquentCollection<int, ExhibitionMedia> $rows */
        $rows = ExhibitionMedia::query()
            ->where('exhibition_id', $exhibitionId)
            ->where('role', 'additional')
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($rows as $index => $usage) {
            $position = $index + 1;
            if ((int) $usage->getAttribute('position') !== $position) {
                $usage->setAttribute('position', $position);
                $usage->save();
            }
        }
    }

    /** @return array<string> */
    private function storageKeys(MediaAsset $asset): array
    {
        $keys = [(string) $asset->getAttribute('storage_key')];
        foreach ($asset->getRelationValue('variants') as $variant) {
            $keys[] = (string) $variant->getAttribute('storage_key');
        }

        return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
    }

    /** @param array<string> $keys */
    private function cleanup(array $keys): void
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

    private function plainText(mixed $value, int $maxLength, bool $emptyToNull): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw ValidationException::withMessages(['media' => 'Media metadata must be plain text.']);
        }
        if ($value === null) {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw ValidationException::withMessages(['media' => 'Media metadata exceeds its maximum length.']);
        }

        $value = trim($value);

        return $emptyToNull && $value === '' ? null : $value;
    }
}
