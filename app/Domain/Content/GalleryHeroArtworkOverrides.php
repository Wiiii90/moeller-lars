<?php

namespace App\Domain\Content;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\ArtworkDimensions;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hero-only metadata, opt-in per source and Artwork. The single persistence
 * owner remains GalleryHeroConfigurationService; original artwork/media stay
 * untouched. Off retains configured deltas so reactivation is reversible.
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
            if (is_bool($raw['enabled'] ?? null)) {
                $entry['enabled'] = $raw['enabled'];
            }
            foreach (['title', 'medium', 'dimensions', 'date'] as $field) {
                if (array_key_exists($field, $raw)
                    && ($raw[$field] === null || is_string($raw[$field]))) {
                    $entry[$field] = $raw[$field];
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
     * Save only selected Hero metadata. The panel switch controls whether
     * stored differences are applied, never original Artwork persistence.
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
            $fresh = GalleryHeroSetting::query()
                ->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();
            $config = $this->configuration($fresh);
            $artwork = $this->heroOverrideSourceArtwork($config, $sourceId, $artworkId);
            $identity = self::heroArtworkOverrideKey($sourceId, $artworkId);
            $current = $config['hero_artwork_overrides'][$identity] ?? [];

            $active = filter_var(
                $values['hero_override_enabled'] ?? ($current['enabled'] ?? ($current !== [])),
                FILTER_VALIDATE_BOOLEAN,
            );

            if (! $active) {
                // Keep the user's entered metadata for a later reactivation.
                $overrides = $current === [] ? [] : [...$current, 'enabled' => false];
            } else {
                $title = trim((string) ($values['title']
                    ?? ($current['title'] ?? $artwork->getAttribute('title'))));
                if ($title === '' || mb_strlen($title) > 240) {
                    throw ValidationException::withMessages([
                        'title' => 'Enter a title of at most 240 characters.',
                    ]);
                }
                $medium = trim((string) ($values['medium']
                    ?? ($current['medium'] ?? $artwork->getAttribute('medium') ?? '')));
                if (mb_strlen($medium) > 255) {
                    throw ValidationException::withMessages([
                        'medium' => 'Material must not exceed 255 characters.',
                    ]);
                }
                $date = $values['artwork_date']
                    ?? ($current['date'] ?? ArtworkDate::fromArtwork($artwork));
                // A submitted explicit null clears the Hero date.
                if (array_key_exists('artwork_date', $values)) {
                    $date = $values['artwork_date'];
                }
                ArtworkDate::normalize($date);

                $inheritedDimensions = $current['dimensions'] ?? $artwork->getAttribute('dimensions');
                $inheritedParts = ArtworkDimensions::split($inheritedDimensions);
                $height = array_key_exists('dimension_height', $values)
                    ? $values['dimension_height'] : $inheritedParts['height'];
                $width = array_key_exists('dimension_width', $values)
                    ? $values['dimension_width'] : $inheritedParts['width'];
                $depth = array_key_exists('dimension_depth', $values)
                    ? $values['dimension_depth'] : $inheritedParts['depth'];
                $unit = $values['dimension_unit'] ?? $inheritedParts['unit'];
                $noStructuredValues = blank($height) && blank($width) && blank($depth);
                // Historical freeform dimensions are inherited read-only;
                // editing other Hero fields must never erase those saved values.
                $dimensions = $noStructuredValues && $inheritedParts['custom'] !== null
                    ? $inheritedParts['custom']
                    : ArtworkDimensions::compose($height, $width, $depth, $unit, null);

                $overrides = ['enabled' => true];
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
            }

            $map = $config['hero_artwork_overrides'];
            if ($overrides === []) {
                unset($map[$identity]);
            } else {
                $map[$identity] = $overrides;
            }
            if ($map === $config['hero_artwork_overrides']) {
                return false;
            }

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
