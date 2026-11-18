<?php

namespace App\Domain\Artwork;

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * The single source of truth for Artwork metadata visibility and arrangement.
 * Fields are sourced from Artwork/MediaAsset; configuration only controls
 * whether and where the field is presented.
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

    public const ALIGNMENTS = [
        'left' => 'Left',
        'center' => 'Center',
        'right' => 'Right',
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

    /** @return array{enabled:bool,visible:array<string,bool>,arrangement:list<array{field:string,row:int,align:string}>} */
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
            'arrangement' => self::defaultArrangement(),
        ];
    }

    /** @return array{enabled:bool,visible:array<string,bool>,arrangement:list<array{field:string,row:int,align:string}>} */
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
        $legacy['arrangement'] = self::defaultArrangement($gallery->getAttribute('caption_layout') === 'rights_right');

        return $legacy;
    }

    /** @return array{enabled:bool,visible:array<string,bool>,arrangement:list<array{field:string,row:int,align:string}>} */
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
        if (array_key_exists('arrangement', $override)) {
            $result['arrangement'] = self::arrangement($override['arrangement']);
        }

        return $result;
    }

    /** @return array{enabled:bool,visible:array<string,bool>,arrangement:list<array{field:string,row:int,align:string}>} */
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
            'arrangement' => array_key_exists('arrangement', $value)
                ? self::arrangement($value['arrangement'])
                : $defaults['arrangement'],
        ];
    }

    /** @return list<array{field:string,row:int,align:string}> */
    public static function arrangement(mixed $value): array
    {
        if (! is_array($value) || count($value) !== count(self::FIELDS)) {
            throw ValidationException::withMessages(['artwork_information.arrangement' => 'Arrange every available information field exactly once.']);
        }

        $items = [];
        $seen = [];
        foreach (array_values($value) as $entry) {
            if (! is_array($entry) || ! is_string($entry['field'] ?? null)
                || ! array_key_exists($entry['field'], self::FIELDS)
                || isset($seen[$entry['field']])
                || ! in_array($entry['align'] ?? null, array_keys(self::ALIGNMENTS), true)
                || filter_var($entry['row'] ?? null, FILTER_VALIDATE_INT) === false
                || (int) $entry['row'] < 1 || (int) $entry['row'] > 4) {
                throw ValidationException::withMessages(['artwork_information.arrangement' => 'Each field needs a unique name, row (1–4) and alignment.']);
            }
            $seen[$entry['field']] = true;
            $items[] = [
                'field' => $entry['field'],
                'row' => (int) $entry['row'],
                'align' => $entry['align'],
            ];
        }
        return $items;
    }

    /** @param array<string,mixed> $data
     *  @return array{enabled:bool,visible:array<string,bool>,arrangement:list<array{field:string,row:int,align:string}>}
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
            'arrangement' => $data['info_arrangement'] ?? self::defaultArrangement(),
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
        if (self::boolean($data['info_individual_arrangement'] ?? false, 'info_individual_arrangement')) {
            $override['arrangement'] = $individual['arrangement'];
        }

        return $override;
    }

    /** @return array<string,mixed> */
    public static function formValues(array $configuration): array
    {
        $result = [
            'info_enabled' => $configuration['enabled'],
            'info_arrangement' => $configuration['arrangement'],
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
        foreach ($configuration['arrangement'] as $slot) {
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
