<?php

namespace App\Domain\Artwork;

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * Canonical Artwork metadata visibility and display. Gallery and individual
 * Artwork have one fixed layout; Home Hero has its own explicit layout option.
 * Values are always sourced from Artwork and MediaAsset.
 */
final class ArtworkInformation
{
    public const FIELDS = [
        'title' => 'Title',
        'date' => 'Date',
        'dimensions' => 'Dimensions',
        'material' => 'Material',
        'credits' => 'Media credits',
        'copyright' => 'Copyright',
    ];

    /** @return list<array{field:string,row:int,align:string}> */
    public static function defaultArrangement(bool $rightsRight = false): array
    {
        $infoSide = $rightsRight ? 'left' : 'right';
        $rightsSide = $rightsRight ? 'right' : 'left';

        return [
            ['field' => 'credits', 'row' => 1, 'align' => $rightsSide],
            ['field' => 'copyright', 'row' => 1, 'align' => $rightsSide],
            ['field' => 'title', 'row' => 1, 'align' => $infoSide],
            ['field' => 'date', 'row' => 1, 'align' => $infoSide],
            ['field' => 'dimensions', 'row' => 2, 'align' => $infoSide],
            ['field' => 'material', 'row' => 2, 'align' => $infoSide],
        ];
    }

    /** @return array{enabled:bool,visible:array<string,bool>} */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'visible' => [
                'title' => true,
                'date' => true,
                'dimensions' => true,
                'material' => true,
                'credits' => false,
                'copyright' => false,
            ],
        ];
    }

    /** @return array{enabled:bool,visible:array<string,bool>} */
    public static function gallery(ArtworkCategory $gallery): array
    {
        $stored = $gallery->getAttribute('artwork_information');
        if (is_array($stored)) {
            return self::normalize($stored);
        }

        // Old immutable checkpoints contain the former individual booleans.
        // A modern edited Gallery always writes the canonical JSON definition.
        $legacy = self::defaults();
        $legacy['enabled'] = $gallery->getAttribute('show_artwork_information') !== false
            || (bool) $gallery->getAttribute('show_media_credits')
            || (bool) $gallery->getAttribute('show_media_copyright');
        foreach (['title', 'date', 'dimensions', 'material'] as $field) {
            $legacy['visible'][$field] = $gallery->getAttribute('show_artwork_information') !== false;
        }
        $legacy['visible']['credits'] = (bool) $gallery->getAttribute('show_media_credits');
        $legacy['visible']['copyright'] = (bool) $gallery->getAttribute('show_media_copyright');

        return $legacy;
    }

    /** @return array{enabled:bool,visible:array<string,bool>} */
    public static function effective(ArtworkCategory $gallery, ?Artwork $artwork = null): array
    {
        $result = self::gallery($gallery);
        $override = $artwork?->getAttribute('artwork_information_override');
        if (! is_array($override)) {
            return $result;
        }

        if (array_key_exists('enabled', $override)) {
            $result['enabled'] = self::boolean($override['enabled'], 'artwork_information_override.enabled');
        }
        if (array_key_exists('visible', $override)) {
            if (! is_array($override['visible'])) {
                throw ValidationException::withMessages(['artwork_information_override' => 'Artwork field overrides must be a field map.']);
            }
            foreach ($override['visible'] as $field => $visible) {
                if (! array_key_exists($field, self::FIELDS)) {
                    throw ValidationException::withMessages(['artwork_information_override' => 'Unknown Artwork information field.']);
                }
                $result['visible'][$field] = self::boolean($visible, $field);
            }
        }

        return $result;
    }

    /** @return array{enabled:bool,visible:array<string,bool>} */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['artwork_information' => 'Choose a valid Artwork information configuration.']);
        }
        $defaults = self::defaults();
        $visible = $defaults['visible'];
        if (array_key_exists('visible', $value)) {
            if (! is_array($value['visible'])) {
                throw ValidationException::withMessages(['artwork_information' => 'Artwork field visibility must be a field map.']);
            }
            foreach ($value['visible'] as $field => $enabled) {
                if (! array_key_exists($field, $visible)) {
                    throw ValidationException::withMessages(['artwork_information' => 'Unknown Artwork information field.']);
                }
                $visible[$field] = self::boolean($enabled, $field);
            }
        }

        return [
            'enabled' => array_key_exists('enabled', $value)
                ? self::boolean($value['enabled'], 'artwork_information.enabled')
                : $defaults['enabled'],
            'visible' => $visible,
        ];
    }

    /** @param array<string,mixed> $data
     *  @return array{enabled:bool,visible:array<string,bool>}
     */
    public static function fromForm(array $data): array
    {
        $visible = [];
        foreach (self::FIELDS as $field => $label) {
            $visible[$field] = self::boolean($data['info_'.$field] ?? false, 'info_'.$field);
        }

        return self::normalize([
            'enabled' => $data['info_enabled'] ?? false,
            'visible' => $visible,
        ]);
    }

    /** @param array<string,mixed> $data */
    public static function individualFromForm(array $data, ArtworkCategory $gallery): ?array
    {
        if (! self::boolean($data['info_individual'] ?? false, 'info_individual')) {
            return null;
        }

        $base = self::gallery($gallery);
        $individual = self::fromForm($data);
        $override = ['visible' => []];
        if ($individual['enabled'] !== $base['enabled']) {
            $override['enabled'] = $individual['enabled'];
        }
        foreach (self::FIELDS as $field => $label) {
            if ($individual['visible'][$field] !== $base['visible'][$field]) {
                $override['visible'][$field] = $individual['visible'][$field];
            }
        }
        return $override;
    }

    /** @return array<string,mixed> */
    public static function formValues(array $configuration): array
    {
        $result = [
            'info_enabled' => $configuration['enabled'],
        ];
        foreach (self::FIELDS as $field => $label) {
            $result['info_'.$field] = $configuration['visible'][$field];
        }
        return $result;
    }

    /**
     * @return list<array{left:list<array{field:string,text:string}>,center:list<array{field:string,text:string}>,right:list<array{field:string,text:string}>}>
     */
    public static function rows(Artwork $artwork, ?MediaAsset $asset = null, ?array $configuration = null): array
    {
        $gallery = $artwork->getRelationValue('category');
        if (! $gallery instanceof ArtworkCategory) {
            return [];
        }
        $configuration ??= self::effective($gallery, $artwork);
        if (! $configuration['enabled']) {
            return [];
        }

        if ($asset === null && ($configuration['visible']['credits'] || $configuration['visible']['copyright'])) {
            $primary = $artwork->artworkMedia()->where('role', 'primary')->with('mediaAsset')->first();
            $asset = $primary?->getRelationValue('mediaAsset');
        }
        $values = [
            'title' => trim((string) $artwork->getAttribute('title')),
            'date' => ArtworkDate::display($artwork) ?? '',
            'dimensions' => ArtworkDimensions::display($artwork->getAttribute('dimensions')) ?? '',
            'material' => html_entity_decode(trim((string) ($artwork->getAttribute('medium') ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'credits' => $asset instanceof MediaAsset ? trim((string) ($asset->getAttribute('credit') ?? '')) : '',
            'copyright' => $asset instanceof MediaAsset ? trim((string) ($asset->effectiveCopyrightNotice() ?? '')) : '',
        ];

        $rows = [];
        // Gallery/Artwork visibility settings cannot change layout. Only
        // Home Hero may explicitly supply its independent arrangement.
        foreach (($configuration['arrangement'] ?? self::defaultArrangement()) as $slot) {
            $field = $slot['field'];
            if (! $configuration['visible'][$field] || $values[$field] === '') {
                continue;
            }
            $index = $slot['row'];
            $rows[$index] ??= ['left' => [], 'center' => [], 'right' => []];
            $rows[$index][$slot['align']][] = ['field' => $field, 'text' => $values[$field]];
        }
        ksort($rows);

        return array_values($rows);
    }

    private static function boolean(mixed $value, string $field): bool
    {
        if (! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            throw ValidationException::withMessages([$field => 'Choose an enabled or disabled state.']);
        }
        return (bool) $value;
    }
}
