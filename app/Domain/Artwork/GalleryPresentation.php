<?php

namespace App\Domain\Artwork;

use App\Domain\Content\DividerVariant;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class GalleryPresentation
{
    public const CAPTION_RIGHTS_LEFT = 'rights_left';

    public const CAPTION_RIGHTS_RIGHT = 'rights_right';

    public const CAPTION_LAYOUTS = [
        self::CAPTION_RIGHTS_LEFT => 'Credits left, Artwork information right',
        self::CAPTION_RIGHTS_RIGHT => 'Artwork information left, Credits right',
    ];

    public static function showArtworkInformation(ArtworkCategory $gallery): bool
    {
        $info = ArtworkInformation::gallery($gallery);
        return $info['enabled'] && (
            $info['visible']['title'] || $info['visible']['date']
            || $info['visible']['dimensions'] || $info['visible']['material']
        );
    }

    public static function showMediaCredits(ArtworkCategory $gallery): bool
    {
        $info = ArtworkInformation::gallery($gallery);
        return $info['enabled'] && $info['visible']['credits'];
    }

    public static function showMediaCopyright(ArtworkCategory $gallery): bool
    {
        $info = ArtworkInformation::gallery($gallery);
        return $info['enabled'] && $info['visible']['copyright'];
    }

    public static function captionLayout(ArtworkCategory $gallery): string
    {
        $value = $gallery->getAttribute('caption_layout');

        return is_string($value) && array_key_exists($value, self::CAPTION_LAYOUTS)
            ? $value : self::CAPTION_RIGHTS_LEFT;
    }

    public const SORT_MANUAL = 'manual';

    public static function allowVideoSound(ArtworkCategory $gallery): bool
    {
        return (bool) $gallery->getAttribute('video_sound_enabled');
    }

    public const SORT_NEWEST = 'newest';

    // Gallery sort_mode is varchar(16); Home's source-rule keys live in JSON.
    // Use short persisted Gallery keys but the exact same ArtworkSort algorithms.
    public const SORT_NEWEST_MEDIA = 'newest_added';

    public const SORT_OLDEST_MEDIA = 'oldest_added';

    public const SORT_OPTIONS = [
        self::SORT_MANUAL => 'Manual',
        self::SORT_NEWEST => 'Newest by Artwork',
        ArtworkSort::OLDEST_ARTWORK => 'Oldest by Artwork',
        self::SORT_NEWEST_MEDIA => 'Newest by Media Added',
        self::SORT_OLDEST_MEDIA => 'Oldest by Media Added',
    ];

    public static function sortMode(ArtworkCategory $gallery): string
    {
        $value = $gallery->getAttribute('sort_mode');

        return is_string($value) && array_key_exists($value, self::SORT_OPTIONS)
            ? $value : self::SORT_MANUAL;
    }

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function orderArtworks(Builder $query, ArtworkCategory $gallery): Builder
    {
        $mode = self::sortMode($gallery);
        if ($mode === self::SORT_MANUAL) {
            return $query->orderBy('position')->orderBy('id');
        }

        return ArtworkSort::apply($query, $mode);
    }

    public const EDGE_DISABLED = 'disabled';

    public const EDGE_HIDDEN = 'hidden';

    public const EDGE_CYCLIC = 'cyclic';

    public const EDGE_MODES = [self::EDGE_DISABLED, self::EDGE_HIDDEN, self::EDGE_CYCLIC];

    public static function viewerEnabled(ArtworkCategory $gallery): bool
    {
        return $gallery->getAttribute('media_viewer_enabled') !== false;
    }

    public static function artworkPagesEnabled(ArtworkCategory $gallery): bool
    {
        return $gallery->getAttribute('artwork_pages_enabled') !== false;
    }

    public static function viewerZoomEnabled(ArtworkCategory $gallery): bool
    {
        return $gallery->getAttribute('viewer_zoom_enabled') !== false;
    }

    public static function viewerNavigationEnabled(ArtworkCategory $gallery): bool
    {
        return $gallery->getAttribute('viewer_navigation_enabled') !== false;
    }

    /**
     * Gallery-only visual drift, never Artwork sort order or saved metadata.
     * A stable Gallery+Artwork hash produces five small horizontal offsets.
     * Avoid the same side twice in succession for a composed, non-repeating
     * rhythm. The CSS grid keeps every resulting placement inside General's
     * Content width, even for large landscape images.
     *
     * @param iterable<Artwork> $artworks Already in canonical Gallery order
     * @return array<int, int> Artwork ID => offset in [-2, 2]
     */
    public static function indexCardOffsets(iterable $artworks, ArtworkCategory $gallery): array
    {
        $offsets = [];
        $previous = null;

        foreach ($artworks as $artwork) {
            $id = (int) $artwork->getKey();
            $hash = (int) sprintf('%u', crc32($gallery->getKey().':'.$id));
            $offset = ($hash % 5) - 2;

            if ($previous !== null && ($offset === $previous || ($offset * $previous) > 0)) {
                // Select a different side or the neutral position, without
                // depending on request-time randomness or storing UI state.
                $candidates = array_values(array_filter(
                    [-2, -1, 0, 1, 2],
                    static fn (int $candidate): bool => $candidate !== $previous
                        && ($candidate === 0 || $previous === 0 || ($candidate * $previous) < 0),
                ));
                $offset = $candidates[(intdiv($hash, 5) % count($candidates))];
            }

            $offsets[$id] = $offset;
            $previous = $offset;
        }

        return $offsets;
    }

    /**
     * Deterministic pseudo-random Gallery spacing, independent of lateral
     * drift and Artwork order. A stable seed prevents changes on reload.
     *
     * @param iterable<Artwork> $artworks Canonical Gallery order
     * @return array<int,int> Artwork ID to spacing step 0..4
     */
    public static function indexCardGapSteps(iterable $artworks, ArtworkCategory $gallery): array
    {
        $steps = [];
        foreach ($artworks as $artwork) {
            $hash = (int) sprintf('%u', crc32($gallery->getKey().':gap:'.$artwork->getKey()));
            $steps[(int) $artwork->getKey()] = $hash % 5;
        }

        return $steps;
    }

    public const DIVIDER_NONE = 'none';

    public const DIVIDER_OPTIONS = [self::DIVIDER_NONE => 'None', ...DividerVariant::LABELS];

    /** @return array<string, mixed> */
    public static function validateSettings(array $data): array
    {
        $values = [];
        foreach (['show_artwork_information', 'show_media_credits', 'show_media_copyright'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if (! is_bool($data[$field]) && ! in_array($data[$field], [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages([$field => 'Choose a supported Gallery caption visibility.']);
            }
            $values[$field] = (bool) $data[$field];
        }
        if (array_key_exists('caption_layout', $data)) {
            $layout = $data['caption_layout'];
            if (! is_string($layout) || ! array_key_exists($layout, self::CAPTION_LAYOUTS)) {
                throw ValidationException::withMessages(['caption_layout' => 'Choose a supported caption alignment.']);
            }
            $values['caption_layout'] = $layout;
        }
        if (array_key_exists('artwork_information', $data)) {
            $values['artwork_information'] = ArtworkInformation::normalize($data['artwork_information']);
        }
        foreach (['media_viewer_enabled', 'artwork_pages_enabled', 'viewer_zoom_enabled', 'viewer_navigation_enabled'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if (! is_bool($data[$field]) && ! in_array($data[$field], [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages([$field => 'Choose an enabled or disabled state.']);
            }
            $values[$field] = (bool) $data[$field];
        }
        if (array_key_exists('video_sound_enabled', $data)) {
            $value = $data['video_sound_enabled'];
            if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages(['video_sound_enabled' => 'Choose whether visitors may enable video sound.']);
            }
            $values['video_sound_enabled'] = (bool) $value;
        }
        if (array_key_exists('sort_mode', $data)) {
            if (! is_string($data['sort_mode']) || ! array_key_exists($data['sort_mode'], self::SORT_OPTIONS)) {
                throw ValidationException::withMessages(['sort_mode' => 'Choose a supported Gallery order.']);
            }
            $values['sort_mode'] = $data['sort_mode'];
        }
        if (array_key_exists('artwork_divider', $data)) {
            $variant = $data['artwork_divider'];
            if (! is_string($variant) || ! array_key_exists($variant, self::DIVIDER_OPTIONS)) {
                throw ValidationException::withMessages(['artwork_divider' => 'Choose a supported artwork divider.']);
            }
            $values['artwork_divider'] = $variant;
        }
        if (array_key_exists('edge_navigation_mode', $data)) {
            if (! is_string($data['edge_navigation_mode']) || ! in_array($data['edge_navigation_mode'], self::EDGE_MODES, true)) {
                throw ValidationException::withMessages(['edge_navigation_mode' => 'Choose Disabled, Hidden, or Cyclic.']);
            }
            $values['edge_navigation_mode'] = $data['edge_navigation_mode'];
        }

        return $values;
    }

    public static function divider(ArtworkCategory $gallery): string
    {
        $variant = $gallery->getAttribute('artwork_divider');

        return is_string($variant) && array_key_exists($variant, self::DIVIDER_OPTIONS)
            ? $variant : self::DIVIDER_NONE;
    }

    public static function edgeMode(ArtworkCategory $gallery): string
    {
        $mode = $gallery->getAttribute('edge_navigation_mode');

        return is_string($mode) && in_array($mode, self::EDGE_MODES, true)
            ? $mode : self::EDGE_DISABLED;
    }
}
