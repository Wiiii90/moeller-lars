<?php

namespace App\Domain\Artwork;

use Illuminate\Validation\ValidationException;

final class ArtworkDimensions
{
    /** @var list<string> */
    public const UNITS = ['cm', 'mm', 'in'];

    /** @return array{height:?string,width:?string,depth:?string,unit:string,custom:?string} */
    public static function split(?string $dimensions): array
    {
        $value = trim((string) $dimensions);
        if ($value === '') {
            return [
                'height' => null,
                'width' => null,
                'depth' => null,
                'unit' => 'cm',
                'custom' => null,
            ];
        }

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*[×xX]\s*([0-9]+(?:[.,][0-9]+)?)(?:\s*[×xX]\s*([0-9]+(?:[.,][0-9]+)?))?\s*(cm|mm|in)$/u', $value, $matches) === 1) {
            return [
                'height' => self::normalizeNumber($matches[1]),
                'width' => self::normalizeNumber($matches[2]),
                'depth' => isset($matches[3]) && $matches[3] !== '' ? self::normalizeNumber($matches[3]) : null,
                'unit' => $matches[4],
                'custom' => null,
            ];
        }

        return [
            'height' => null,
            'width' => null,
            'depth' => null,
            'unit' => 'cm',
            'custom' => $value,
        ];
    }

    public static function compose(
        mixed $height,
        mixed $width,
        mixed $depth,
        mixed $unit,
        mixed $custom,
    ): ?string {
        $customValue = trim(is_string($custom) ? $custom : '');
        if ($customValue !== '') {
            if (mb_strlen($customValue) > 240) {
                throw ValidationException::withMessages([
                    'dimension_custom' => 'Custom dimensions may not exceed 240 characters.',
                ]);
            }

            return $customValue;
        }

        $heightValue = self::number($height, 'dimension_height');
        $widthValue = self::number($width, 'dimension_width');
        $depthValue = self::number($depth, 'dimension_depth');

        if ($heightValue === null && $widthValue === null && $depthValue === null) {
            return null;
        }

        if ($heightValue === null || $widthValue === null) {
            throw ValidationException::withMessages([
                'dimensions' => 'Height and width are required for structured dimensions.',
            ]);
        }

        $unitValue = is_string($unit) ? trim($unit) : '';
        if (! in_array($unitValue, self::UNITS, true)) {
            throw ValidationException::withMessages([
                'dimension_unit' => 'Choose cm, mm, or in, or use Custom dimensions.',
            ]);
        }

        $parts = [$heightValue, $widthValue];
        if ($depthValue !== null) {
            $parts[] = $depthValue;
        }

        return implode(' × ', $parts).' '.$unitValue;
    }

    private static function number(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (! is_numeric($normalized) || (float) $normalized <= 0) {
            throw ValidationException::withMessages([
                $field => 'Dimensions must be positive numbers.',
            ]);
        }

        return self::normalizeNumber($normalized);
    }

    private static function normalizeNumber(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if (! str_contains($value, '.')) {
            return ltrim($value, '0') === '' ? '0' : ltrim($value, '0');
        }

        $value = rtrim(rtrim($value, '0'), '.');
        $parts = explode('.', $value, 2);
        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;

        return isset($parts[1]) && $parts[1] !== '' ? $whole.'.'.$parts[1] : $whole;
    }
}
