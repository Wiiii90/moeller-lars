<?php

namespace App\Domain\Artwork;

use App\Domain\Content\DividerVariant;
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
        return $gallery->getAttribute('show_artwork_information') !== false;
    }

    public static function showMediaCredits(ArtworkCategory $gallery): bool
    {
        return (bool) $gallery->getAttribute('show_media_credits');
    }

    public static function showMediaCopyright(ArtworkCategory $gallery): bool
    {
        return (bool) $gallery->getAttribute('show_media_copyright');
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

    public const SORT_OPTIONS = [
        self::SORT_MANUAL => 'Manual (new Artworks first)',
        self::SORT_NEWEST => 'Artwork date (newest first)',
    ];

    public static function sortMode(ArtworkCategory $gallery): string
    {
        $value = $gallery->getAttribute('sort_mode');

        return is_string($value) && array_key_exists($value, self::SORT_OPTIONS)
            ? $value : self::SORT_MANUAL;
    }

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public static function orderArtworks(Builder $query, ArtworkCategory $gallery): Builder
    {
        if (self::sortMode($gallery) === self::SORT_NEWEST) {
            return $query
                ->orderByRaw('CASE WHEN work_year IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('work_year')
                ->orderByRaw('CASE WHEN work_date IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('work_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        return $query->orderBy('position')->orderBy('id');
    }

    public const EDGE_DISABLED = 'disabled';
    public const EDGE_HIDDEN = 'hidden';
    public const EDGE_CYCLIC = 'cyclic';

    public const EDGE_MODES = [self::EDGE_DISABLED, self::EDGE_HIDDEN, self::EDGE_CYCLIC];

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
        if (array_key_exists('portrait_detail_enabled', $data)) {
            $enabled = $data['portrait_detail_enabled'];
            if (! is_bool($enabled) && ! in_array($enabled, [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages(['portrait_detail_enabled' => 'Choose whether portrait Artwork pages use viewport height.']);
            }
            $values['portrait_detail_enabled'] = (bool) $enabled;
        }
        if (array_key_exists('landscape_detail_enabled', $data)) {
            $enabled = $data['landscape_detail_enabled'];
            if (! is_bool($enabled) && ! in_array($enabled, [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages(['landscape_detail_enabled' => 'Choose whether landscape details can expand.']);
            }
            $values['landscape_detail_enabled'] = (bool) $enabled;
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

    public static function enabled(ArtworkCategory $gallery): bool
    {
        return (bool) $gallery->getAttribute('landscape_detail_enabled');
    }

    public static function portraitEnabled(ArtworkCategory $gallery): bool
    {
        return (bool) $gallery->getAttribute('portrait_detail_enabled');
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
