<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;
use Illuminate\Validation\ValidationException;

final class PublicAppearance
{
    public const MODE_DEFAULT = 'default';
    public const MODE_SOLID = 'solid';
    public const MODE_GRADIENT = 'gradient';
    public const DEFAULT_PAGE_COLOR = '#777777';
    public const DEFAULT_GRADIENT_ANGLE = 180;

    /** @return array<string, string> */
    public static function modeOptions(): array
    {
        return [
            self::MODE_DEFAULT => 'Default',
            self::MODE_SOLID => 'Solid color',
            self::MODE_GRADIENT => 'Linear gradient',
        ];
    }

    public static function normalizeMode(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === self::MODE_DEFAULT) {
            return null;
        }

        if (! is_string($value) || ! in_array($value, [self::MODE_SOLID, self::MODE_GRADIENT], true)) {
            throw ValidationException::withMessages([
                'background_mode' => 'Choose Default, Solid color, or Linear gradient.',
            ]);
        }

        return $value;
    }

    public static function normalizeColor(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Use a six-digit hexadecimal color.']);
        }

        $candidate = strtoupper(trim($value));
        if (! str_starts_with($candidate, '#')) {
            $candidate = '#'.$candidate;
        }

        if (preg_match('/^#[0-9A-F]{6}$/', $candidate) !== 1) {
            throw ValidationException::withMessages([$field => 'Use a six-digit hexadecimal color such as #777777.']);
        }

        return $candidate;
    }

    public static function normalizeAngle(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([
                'background_gradient_angle' => 'Gradient angle must be a whole number from 0 to 360.',
            ]);
        }

        $angle = (int) $value;
        if ($angle < 0 || $angle > 360) {
            throw ValidationException::withMessages([
                'background_gradient_angle' => 'Gradient angle must be between 0 and 360 degrees.',
            ]);
        }

        return $angle;
    }

    public static function backgroundCss(PublicContentSetting $settings): ?string
    {
        $mode = self::normalizeMode($settings->getAttribute('background_mode'));

        if ($mode === null) {
            return null;
        }

        if ($mode === self::MODE_SOLID) {
            return self::normalizeColor($settings->getAttribute('background_color'), 'background_color')
                ?? self::DEFAULT_PAGE_COLOR;
        }

        $start = self::normalizeColor($settings->getAttribute('background_gradient_start'), 'background_gradient_start')
            ?? self::DEFAULT_PAGE_COLOR;
        $end = self::normalizeColor($settings->getAttribute('background_gradient_end'), 'background_gradient_end')
            ?? self::DEFAULT_PAGE_COLOR;
        $angle = self::normalizeAngle($settings->getAttribute('background_gradient_angle'))
            ?? self::DEFAULT_GRADIENT_ANGLE;

        return sprintf('linear-gradient(%ddeg, %s, %s) fixed', $angle, $start, $end);
    }
}
