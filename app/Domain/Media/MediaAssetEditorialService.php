<?php

namespace App\Domain\Media;

use App\Domain\Admin\AdminAuditService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaAssetEditorialService
{
    public function __construct(private readonly AdminAuditService $adminAuditService) {}

    public function updateMetadata(MediaAsset $asset, array $data): MediaAsset
    {
        $actor = $this->adminAuditService->requireActor();
        $fresh = $asset->fresh();
        if (! $fresh instanceof MediaAsset || $fresh->getAttribute('state') === 'deleted') {
            throw ValidationException::withMessages(['media' => 'Deleted media cannot be edited.']);
        }

        $allowed = ['alt_text', 'credit', 'copyright_notice'];
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
        if (array_key_exists('copyright_notice', $data)) {
            $values['copyright_notice'] = $this->plainText($data['copyright_notice'], 500, true);
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
        $fresh = $asset->fresh(['variants']);
        if (! $fresh instanceof MediaAsset) {
            throw ValidationException::withMessages(['media' => 'Media could not be found.']);
        }

        $keys = $this->storageKeys($fresh);
        if ($fresh->getAttribute('state') !== 'deleted') {
            $this->ensureUnreferenced($fresh);
            DB::transaction(function () use ($fresh, $actor): void {
                $fresh->variants()->update(['state' => 'deleted']);
                $fresh->setAttribute('state', 'deleted');
                $fresh->save();
                $this->adminAuditService->record($actor, 'media.deleted', 'media_asset', $fresh->getKey());
            });
        }

        $this->cleanup($keys);

        return true;
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

    private function ensureUnreferenced(MediaAsset $asset): void
    {
        $id = $asset->getKey();
        $references = [
            ['artwork_media', 'media_asset_id'],
            ['exhibition_media', 'media_asset_id'],
            ['cv_entries', 'image_media_asset_id'],
            ['blog_posts', 'cover_media_asset_id'],
            ['public_content_settings', 'favicon_media_asset_id'],
        ];
        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where($column, $id)->exists()) {
                throw ValidationException::withMessages(['media' => 'Referenced media cannot be deleted.']);
            }
        }

        $referencedByCustomPage = CustomPageSetting::query()
            ->get(['id', 'blocks'])
            ->contains(function (CustomPageSetting $settings) use ($id): bool {
                foreach ($settings->components() as $component) {
                    if (($component['type'] ?? null) === 'image'
                        && is_numeric($component['media_asset_id'] ?? null)
                        && (int) $component['media_asset_id'] === (int) $id) {
                        return true;
                    }
                }

                return false;
            });

        if ($referencedByCustomPage) {
            throw ValidationException::withMessages(['media' => 'Referenced media cannot be deleted.']);
        }
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
