<?php

namespace App\Domain\Content;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkInformation;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hero-only editorial overlays. The persisted Artwork and its Gallery remain
 * unchanged; the single persistence owner is GalleryHeroConfigurationService.
 */
trait GalleryHeroArtworkOverrides
{
    public static function heroArtworkOverrideKey(int $sourceId, int $artworkId): string
    {
        return $sourceId.'|artwork:'.$artworkId;
    }

    /** @return array<string,array<string,mixed>> */
    private function readHeroArtworkOverrides(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value) || count($value) > 1000) {
            return [];
        }

        $result = [];
        foreach ($value as $identity => $raw) {
            if (! is_string($identity)
                || preg_match('/^-?[1-9][0-9]*\\|artwork:[1-9][0-9]*$/D', $identity) !== 1
                || ! is_array($raw) || array_is_list($raw)) {
                continue;
            }
            $entry = [];
            foreach (['title', 'medium', 'dimensions', 'date', 'alt_text'] as $field) {
                if (array_key_exists($field, $raw)
                    && ($raw[$field] === null || is_string($raw[$field]))) {
                    $entry[$field] = $raw[$field];
                }
            }
            if (is_int($raw['media_asset_id'] ?? null) && $raw['media_asset_id'] > 0) {
                $entry['media_asset_id'] = $raw['media_asset_id'];
            }
            if (is_array($raw['information'] ?? null)) {
                $info = $raw['information'];
                $normalized = [];
                if (is_bool($info['enabled'] ?? null)) {
                    $normalized['enabled'] = $info['enabled'];
                }
                if (is_array($info['visible'] ?? null)) {
                    foreach ($info['visible'] as $field => $flag) {
                        if (array_key_exists($field, ArtworkInformation::FIELDS) && is_bool($flag)) {
                            $normalized['visible'][$field] = $flag;
                        }
                    }
                }
                if ($normalized !== []) {
                    $entry['information'] = $normalized;
                }
            }
            if ($entry !== []) {
                $result[$identity] = $entry;
            }
        }

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function validatedHeroArtworkOverrides(mixed $value): array
    {
        if (! is_array($value) || (array_is_list($value) && $value !== []) || count($value) > 1000) {
            throw ValidationException::withMessages(['artwork' => 'Invalid Hero Artwork overrides.']);
        }

        $clean = $this->readHeroArtworkOverrides($value);
        if (count($clean) !== count($value)) {
            throw ValidationException::withMessages(['artwork' => 'Invalid Hero Artwork override identity.']);
        }

        return $clean;
    }

    /** One source-membership check for editing and saving the Hero version. */
    public function sourceArtworkForHero(GalleryHeroSetting $settings, int $sourceId, int $artworkId): Artwork
    {
        return $this->heroOverrideSourceArtwork($this->configuration($settings), $sourceId, $artworkId);
    }

    /** @return array<string,mixed> */
    public function heroArtworkOverridesFor(GalleryHeroSetting $settings, int $sourceId, int $artworkId): array
    {
        return $this->configuration($settings)['hero_artwork_overrides'][
            self::heroArtworkOverrideKey($sourceId, $artworkId)
        ] ?? [];
    }

    /**
     * A Hero Artwork editor may override inherited presentation and media,
     * never update the original Artwork, its Gallery or MediaAsset metadata.
     *
     * @param array<string,mixed> $values
     */
    public function saveHeroArtworkOverrides(
        GalleryHeroSetting $settings,
        int $sourceId,
        int $artworkId,
        array $values,
    ): bool {
        return DB::transaction(function () use ($settings, $sourceId, $artworkId, $values): bool {
            /** @var GalleryHeroSetting $fresh */
            $fresh = GalleryHeroSetting::query()
                ->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();
            $config = $this->configuration($fresh);
            $artwork = $this->heroOverrideSourceArtwork($config, $sourceId, $artworkId);
            $original = $artwork->artworkMedia()->where('role', 'primary')->first();
            $originalMediaId = $original === null ? null : (int) $original->getAttribute('media_asset_id');

            $title = trim((string) ($values['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 240) {
                throw ValidationException::withMessages(['title' => 'Enter a title of at most 240 characters.']);
            }
            $medium = trim((string) ($values['medium'] ?? ''));
            if (mb_strlen($medium) > 255) {
                throw ValidationException::withMessages(['medium' => 'Material must not exceed 255 characters.']);
            }
            $date = $values['artwork_date'] ?? null;
            ArtworkDate::normalize($date);

            $dimensions = ArtworkDimensions::compose(
                $values['dimension_height'] ?? null,
                $values['dimension_width'] ?? null,
                $values['dimension_depth'] ?? null,
                $values['dimension_unit'] ?? 'cm',
                $values['dimension_custom'] ?? null,
            );

            $selectedMedia = $values['primary_media_asset_id'] ?? null;
            if ($selectedMedia === '' || $selectedMedia === null) {
                $mediaId = $originalMediaId;
            } else {
                $parsed = filter_var($selectedMedia, FILTER_VALIDATE_INT);
                if ($parsed === false || $parsed < 1) {
                    throw ValidationException::withMessages(['primary_media_asset_id' => 'Choose a valid Media File.']);
                }
                $mediaId = (int) $parsed;
            }
            if ($mediaId !== null && $mediaId !== $originalMediaId) {
                $media = MediaAsset::query()->with('variants')->find($mediaId);
                if (! $media instanceof MediaAsset || ! GalleryHeroCandidate::mediaIsEligible($media)) {
                    throw ValidationException::withMessages([
                        'primary_media_asset_id' => 'Choose an available image or video with ALT text.',
                    ]);
                }
            }

            $alt = trim((string) ($values['primary_alt_text'] ?? ''));
            if (mb_strlen($alt) > 500) {
                throw ValidationException::withMessages(['primary_alt_text' => 'ALT text must not exceed 500 characters.']);
            }
            $originalAlt = $mediaId === $originalMediaId
                ? trim((string) ($original?->getAttribute('alt_text_override') ?? ''))
                : '';

            $overrides = [];
            if ($title !== trim((string) $artwork->getAttribute('title'))) {
                $overrides['title'] = $title;
            }
            if ($medium !== trim((string) ($artwork->getAttribute('medium') ?? ''))) {
                $overrides['medium'] = $medium === '' ? null : $medium;
            }
            if ($date !== ArtworkDate::fromArtwork($artwork)) {
                $overrides['date'] = $date;
            }
            if ($dimensions !== ArtworkDimensions::display($artwork->getAttribute('dimensions'))) {
                $overrides['dimensions'] = $dimensions;
            }
            if ($mediaId !== $originalMediaId && $mediaId !== null) {
                $overrides['media_asset_id'] = $mediaId;
            }
            if ($alt !== $originalAlt) {
                $overrides['alt_text'] = $alt === '' ? null : $alt;
            }

            if (filter_var($values['info_individual'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $base = ArtworkInformation::effective($artwork->getRelation('category'), $artwork);
                $chosen = ArtworkInformation::fromForm($values);
                $information = [];
                if ($chosen['enabled'] !== $base['enabled']) {
                    $information['enabled'] = $chosen['enabled'];
                }
                foreach (ArtworkInformation::FIELDS as $field => $label) {
                    if ($chosen['visible'][$field] !== $base['visible'][$field]) {
                        $information['visible'][$field] = $chosen['visible'][$field];
                    }
                }
                if ($information !== []) {
                    $overrides['information'] = $information;
                }
            }

            $identity = self::heroArtworkOverrideKey($sourceId, $artworkId);
            $map = $config['hero_artwork_overrides'];
            if ($overrides === []) {
                unset($map[$identity]);
            } else {
                $map[$identity] = $overrides;
            }
            if ($map === $config['hero_artwork_overrides']) {
                return false;
            }

            // updateArtworkSettings owns the audited configuration transaction.
            return $this->updateArtworkSettings($fresh, ['hero_artwork_overrides' => $map]);
        });
    }

    public function resetHeroArtworkOverrides(GalleryHeroSetting $settings, int $sourceId, int $artworkId): bool
    {
        return DB::transaction(function () use ($settings, $sourceId, $artworkId): bool {
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $config = $this->configuration($fresh);
            $this->heroOverrideSourceArtwork($config, $sourceId, $artworkId);
            $key = self::heroArtworkOverrideKey($sourceId, $artworkId);
            if (! isset($config['hero_artwork_overrides'][$key])) {
                return false;
            }
            $map = $config['hero_artwork_overrides'];
            unset($map[$key]);

            return $this->updateArtworkSettings($fresh, ['hero_artwork_overrides' => $map]);
        });
    }

    /** @param array<string,mixed> $config */
    private function heroOverrideSourceArtwork(array $config, int $sourceId, int $artworkId): Artwork
    {
        /** @var Artwork $artwork */
        $artwork = Artwork::query()->with('category')->findOrFail($artworkId);
        $valid = false;
        if ($sourceId > 0) {
            $valid = (int) $artwork->getAttribute('artwork_category_id') === $sourceId;
        } elseif ($sourceId < 0) {
            foreach ($config['dummy_galleries'] as $source) {
                if ($source['id'] !== $sourceId) {
                    continue;
                }
                foreach ($source['members'] as $member) {
                    if (($member['artwork_id'] ?? null) === $artworkId) {
                        $valid = true;
                        break 2;
                    }
                }
            }
        }
        if (! $valid || ! $artwork->getRelationValue('category') instanceof ArtworkCategory) {
            throw ValidationException::withMessages([
                'artwork' => 'The Artwork no longer belongs to this Hero source.',
            ]);
        }

        return $artwork;
    }
}
