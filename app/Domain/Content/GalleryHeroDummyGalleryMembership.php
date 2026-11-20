<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\ArtworkSort;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait GalleryHeroDummyGalleryMembership
{
    /**
     * Hero-only Dummy Galleries are source groups, not public Gallery models.
     * Their member identities stay canonical Artwork/Storage references.
     * The stored JSON belongs to the Gallery Hero configuration and follows
     * its publication, audit and revision semantics.
     *
     * @return list<array{id:int,name:string,enabled:bool,members:list<array<string,mixed>>}>
     */
    private function readDummyGalleries(array $artwork): array
    {
        if (array_key_exists('dummy_galleries', $artwork)) {
            return $this->normalizeDummyGalleries($artwork['dummy_galleries'], false);
        }

        // One-time persisted-data bridge: previously saved Manual and
        // Semi-Automatic members become one Dummy Gallery without duplication.
        $profiles = is_array($artwork['source_profiles'] ?? null) ? $artwork['source_profiles'] : [];
        $manual = $this->readManualGroup($profiles['manual']['manual_group'] ?? $artwork['manual_group'] ?? []);
        if ($manual === [] && is_numeric($artwork['fixed_artwork_id'] ?? null)
            && (int) $artwork['fixed_artwork_id'] > 0) {
            $manual[] = ['artwork_id' => (int) $artwork['fixed_artwork_id'], 'weight' => self::WEIGHT_TOTAL, 'enabled' => true];
        }
        $semi = $this->readManualGroup($profiles['semi_automatic']['manual_group'] ?? []);
        $members = [];
        $seen = [];
        foreach ([...$manual, ...$semi] as $member) {
            $key = self::memberKey($member);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $members[] = $member;
        }
        if ($members === []) {
            return [];
        }
        return [[
            'id' => -1,
            'name' => 'Imported Artworks',
            'enabled' => true,
            'members' => $members,
        ]];
    }

    /** @return list<array<string,mixed>> */
    private function normalizeDummyGalleries(mixed $value, bool $strict): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > 250) {
            if ($strict) {
                throw ValidationException::withMessages(['dummy_galleries' => 'Choose a valid list of Dummy Galleries.']);
            }
            return [];
        }
        $galleries = [];
        $seenIds = [];
        foreach ($value as $group) {
            if (! is_array($group)) {
                if ($strict) {
                    throw ValidationException::withMessages(['dummy_galleries' => 'Invalid Dummy Gallery.']);
                }
                continue;
            }
            $id = filter_var($group['id'] ?? null, FILTER_VALIDATE_INT);
            $name = is_string($group['name'] ?? null) ? trim($group['name']) : '';
            $enabled = $group['enabled'] ?? true;
            $members = $group['members'] ?? [];
            if ($id === false || $id >= 0 || isset($seenIds[$id]) || $name === ''
                || mb_strlen($name) > 100 || ! is_bool($enabled) || ! is_array($members)
                || ! array_is_list($members) || count($members) > 250) {
                if ($strict) {
                    throw ValidationException::withMessages(['dummy_galleries' => 'Invalid Dummy Gallery name, ID or members.']);
                }
                continue;
            }
            $seenIds[$id] = true;
            $normalizedMembers = [];
            $seenMembers = [];
            foreach ($members as $member) {
                $identity = is_array($member) ? $this->memberIdentity($member) : null;
                $memberEnabled = is_array($member) ? ($member['enabled'] ?? true) : null;
                $key = $identity === null ? null : self::memberKey($identity);
                $title = is_array($member) && is_string($member['title'] ?? null)
                    ? trim($member['title']) : null;
                if ($title === '') {
                    $title = null;
                }
                $year = is_array($member) ? $this->datePart($member['year'] ?? null, 1, 9999) : null;
                $month = is_array($member) ? $this->datePart($member['month'] ?? null, 1, 12) : null;
                $day = is_array($member) ? $this->datePart($member['day'] ?? null, 1, 31) : null;
                $rawYear = is_array($member) ? ($member['year'] ?? null) : null;
                $rawMonth = is_array($member) ? ($member['month'] ?? null) : null;
                $rawDay = is_array($member) ? ($member['day'] ?? null) : null;
                $invalidDate = ($rawYear !== null && $rawYear !== '' && $year === null)
                    || ($rawMonth !== null && $rawMonth !== '' && $month === null)
                    || ($rawDay !== null && $rawDay !== '' && $day === null)
                    || ($month !== null && $year === null)
                    || ($day !== null && $month === null)
                    || ($day !== null && ! checkdate($month, $day, $year));
                if ($key === null || isset($seenMembers[$key]) || ! is_bool($memberEnabled)
                    || ($title !== null && mb_strlen($title) > 255) || $invalidDate) {
                    if ($strict) {
                        throw ValidationException::withMessages(['dummy_galleries' => 'Duplicate or invalid Dummy Gallery candidate.']);
                    }
                    continue;
                }
                $seenMembers[$key] = true;
                $normalizedMembers[] = [
                    ...$identity,
                    'enabled' => $memberEnabled,
                    'title' => $title,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                ];
            }
            $galleries[] = ['id' => $id, 'name' => $name, 'enabled' => $enabled, 'members' => $normalizedMembers];
        }
        return $galleries;
    }

    private function datePart(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $part = filter_var($value, FILTER_VALIDATE_INT);
        return $part === false || $part < $min || $part > $max ? null : $part;
    }

    /** @return list<array<string,mixed>> */
    private function validatedDummyGalleries(mixed $value): array
    {
        return $this->normalizeDummyGalleries($value, true);
    }

    /** Pick the first unused readable name, without a persistent counter. */
    private function defaultDummyGalleryName(array $galleries, ?int $excludeId = null): string
    {
        $names = array_column(array_filter(
            $galleries,
            static fn (array $gallery): bool => $gallery['id'] !== $excludeId,
        ), 'name');
        $number = 1;
        while (in_array('Dummy Gallery '.$number, $names, true)) {
            $number++;
        }

        return 'Dummy Gallery '.$number;
    }

    /** @param list<array<string,mixed>> $members */
    public function createDummyGallery(GalleryHeroSetting $settings, string $name, array $members = []): ?int
    {
        $name = trim($name);
        if ($name === '' && $members === []) {
            return null;
        }

        return DB::transaction(function () use ($settings, $name, $members): int {
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $galleries = $this->configuration($fresh)['dummy_galleries'];
            $id = min([0, ...array_column($galleries, 'id')]) - 1;
            $galleries[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $this->defaultDummyGalleryName($galleries),
                'enabled' => true,
                'members' => $members,
            ];
            $this->updateArtworkSettings($fresh, ['dummy_galleries' => $galleries]);

            return $id;
        });
    }

    /**
     * The Hero setting is the transactional source of truth for every Dummy
     * mutation. Never calculate a replacement membership list from a stale
     * Livewire model before acquiring this row lock.
     *
     * @param Closure(array<int,array<string,mixed>>&):bool $mutate
     */
    private function mutateDummyGalleries(GalleryHeroSetting $settings, Closure $mutate): bool
    {
        return DB::transaction(function () use ($settings, $mutate): bool {
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $galleries = $this->configuration($fresh)['dummy_galleries'];
            if (! $mutate($galleries)) {
                return false;
            }

            return $this->updateArtworkSettings($fresh, ['dummy_galleries' => $galleries]);
        });
    }

    /** @param array<string,mixed> $changes */
    public function updateDummyGallery(GalleryHeroSetting $settings, int $id, array $changes): bool
    {
        return $this->mutateDummyGalleries($settings, function (array &$galleries) use ($id, $changes): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $id) {
                    continue;
                }
                $before = $gallery;
                foreach (['name', 'enabled', 'members'] as $field) {
                    if (array_key_exists($field, $changes)) {
                        $gallery[$field] = $field === 'name' && trim((string) $changes[$field]) === ''
                            ? $this->defaultDummyGalleryName($galleries, $id)
                            : $changes[$field];
                    }
                }
                $changed = $before !== $gallery;
                unset($gallery);
                return $changed;
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }

    public function removeDummyGallery(GalleryHeroSetting $settings, int $id): bool
    {
        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($id): bool {
            $count = count($galleries);
            $galleries = array_values(array_filter(
                $galleries, static fn (array $gallery): bool => $gallery['id'] !== $id,
            ));
            return count($galleries) !== $count;
        });
    }

    public function addDummyMedia(GalleryHeroSetting $settings, int $galleryId, int $mediaAssetId): bool
    {
        $asset = MediaAsset::query()->with('variants')->find($mediaAssetId);
        if (! $asset instanceof MediaAsset || ! GalleryHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages([
                'pending_candidate' => 'Choose an eligible image or video from Storage.',
            ]);
        }

        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($galleryId, $asset, $mediaAssetId): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $galleryId) {
                    continue;
                }
                $gallery['members'][] = [
                    'media_asset_id' => $mediaAssetId,
                    'enabled' => true,
                    'title' => (string) $asset->getAttribute('original_filename'),
                    'year' => null,
                    'month' => null,
                    'day' => null,
                ];
                unset($gallery);
                return true;
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }

    public function setDummyMemberEnabled(GalleryHeroSetting $settings, int $galleryId, string $key, bool $enabled): bool
    {
        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($galleryId, $key, $enabled): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $galleryId) {
                    continue;
                }
                foreach ($gallery['members'] as &$member) {
                    if (self::memberKey($member) !== $key) {
                        continue;
                    }
                    $changed = $member['enabled'] !== $enabled;
                    $member['enabled'] = $enabled;
                    unset($member, $gallery);
                    return $changed;
                }
                unset($gallery);
                throw ValidationException::withMessages(['candidate' => 'Dummy Gallery member no longer exists.']);
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }

    /** @param array<string,mixed> $changes */
    public function updateDummyMember(GalleryHeroSetting $settings, int $galleryId, string $key, array $changes): bool
    {
        $fields = ['title', 'year', 'month', 'day'];
        foreach (array_keys($changes) as $field) {
            if (! in_array($field, $fields, true)) {
                throw ValidationException::withMessages(['candidate' => 'Unknown Dummy Gallery field.']);
            }
        }
        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($galleryId, $key, $changes): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $galleryId) {
                    continue;
                }
                foreach ($gallery['members'] as &$member) {
                    if (self::memberKey($member) !== $key) {
                        continue;
                    }
                    if (! isset($member['media_asset_id'])) {
                        throw ValidationException::withMessages([
                            'candidate' => 'Existing Artwork metadata is edited in its Artwork editor.',
                        ]);
                    }
                    $before = $member;
                    foreach ($changes as $field => $value) {
                        $member[$field] = $value;
                    }
                    $changed = $before !== $member;
                    unset($member, $gallery);
                    return $changed;
                }
                unset($gallery);
                throw ValidationException::withMessages(['candidate' => 'Dummy Gallery member no longer exists.']);
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }

    public function addDummyArtwork(GalleryHeroSetting $settings, int $galleryId, int $artworkId): bool
    {
        $artwork = app(\App\Domain\Artwork\PublicArtworkQuery::class)
            ->editorialHeroCandidatesByIds([$artworkId])->first();
        if (! $artwork instanceof \App\Models\Artwork) {
            throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Artwork.']);
        }
        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($galleryId, $artworkId): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $galleryId) {
                    continue;
                }
                $gallery['members'][] = ['artwork_id' => $artworkId, 'enabled' => true];
                unset($gallery);
                return true;
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }

    public function removeDummyMember(GalleryHeroSetting $settings, int $galleryId, string $key): bool
    {
        return $this->mutateDummyGalleries($settings, static function (array &$galleries) use ($galleryId, $key): bool {
            foreach ($galleries as &$gallery) {
                if ($gallery['id'] !== $galleryId) {
                    continue;
                }
                $before = count($gallery['members']);
                $gallery['members'] = array_values(array_filter(
                    $gallery['members'],
                    static fn (array $member): bool => self::memberKey($member) !== $key,
                ));
                $changed = count($gallery['members']) !== $before;
                unset($gallery);
                return $changed;
            }
            throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
        });
    }
}
