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
    public const DEFAULT_SURFACE_COLOR = '#FFFFFF';
    public const SURFACE_PREFIXES = ['background', 'menu_background', 'page_background'];

    public const DEFAULT_GRADIENT_ANGLE = 180;

    public const DEFAULT_PAGE_WIDTH = 800;

    public const DEFAULT_CONTENT_PADDING = 75;

    public const MIN_PAGE_WIDTH = 640;

    public const MAX_PAGE_WIDTH = 1440;

    public const MIN_CONTENT_PADDING = 24;

    public const MAX_CONTENT_PADDING = 180;

    /** @return array<string, string> */
    public static function modeOptions(): array
    {
        return [
            self::MODE_DEFAULT => 'Default',
            self::MODE_SOLID => 'Solid color',
            self::MODE_GRADIENT => 'Linear gradient',
        ];
    }

    public static function normalizeMode(mixed $value, string $field = 'background_mode'): ?string
    {
        if ($value === null || $value === '' || $value === self::MODE_DEFAULT) {
            return null;
        }

        if (! is_string($value) || ! in_array($value, [self::MODE_SOLID, self::MODE_GRADIENT], true)) {
            throw ValidationException::withMessages([
                $field => 'Choose Default, Solid color, or Linear gradient.',
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

    public static function normalizeAngle(mixed $value, string $field = 'background_gradient_angle'): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([
                $field => 'Gradient angle must be a whole number from 0 to 360.',
            ]);
        }

        $angle = (int) $value;
        if ($angle < 0 || $angle > 360) {
            throw ValidationException::withMessages([
                $field => 'Gradient angle must be between 0 and 360 degrees.',
            ]);
        }

        return $angle;
    }

    public static function normalizePageWidth(mixed $value): int
    {
        return self::normalizeDimension(
            $value,
            self::DEFAULT_PAGE_WIDTH,
            self::MIN_PAGE_WIDTH,
            self::MAX_PAGE_WIDTH,
            'public_page_width',
            'Page width',
        );
    }

    public static function normalizeContentPadding(mixed $value): int
    {
        return self::normalizeDimension(
            $value,
            self::DEFAULT_CONTENT_PADDING,
            self::MIN_CONTENT_PADDING,
            self::MAX_CONTENT_PADDING,
            'public_content_padding',
            'Content padding',
        );
    }

    public static function backgroundCss(PublicContentSetting $settings): ?string
    {
        return self::surfaceCss($settings, 'background');
    }

    /**
     * The same validated paint contract owns all global public surfaces.
     * Null preserves the existing CSS defaults. Future page overrides can
     * shadow the Page CSS custom property without modifying this record.
     */
    public static function surfaceCss(PublicContentSetting $settings, string $surface): ?string
    {
        if (! in_array($surface, self::SURFACE_PREFIXES, true)) {
            throw new \InvalidArgumentException('Unsupported public appearance surface.');
        }

        $modeField = $surface.'_mode';
        $mode = self::normalizeMode($settings->getAttribute($modeField), $modeField);
        if ($mode === null) {
            return null;
        }

        $fallback = $surface === 'background' ? self::DEFAULT_PAGE_COLOR : self::DEFAULT_SURFACE_COLOR;
        if ($mode === self::MODE_SOLID) {
            return self::normalizeColor($settings->getAttribute($surface.'_color'), $surface.'_color')
                ?? $fallback;
        }

        $start = self::normalizeColor($settings->getAttribute($surface.'_gradient_start'), $surface.'_gradient_start')
            ?? $fallback;
        $end = self::normalizeColor($settings->getAttribute($surface.'_gradient_end'), $surface.'_gradient_end')
            ?? $fallback;
        $angle = self::normalizeAngle($settings->getAttribute($surface.'_gradient_angle'), $surface.'_gradient_angle')
            ?? self::DEFAULT_GRADIENT_ANGLE;
        $gradient = sprintf('linear-gradient(%ddeg, %s, %s)', $angle, $start, $end);

        // Only the viewport Background retains the established fixed attachment.
        return $surface === 'background' ? $gradient.' fixed' : $gradient;
    }

    /** @return array{shell:string,art:string,padding:string} */
    public static function layoutCss(PublicContentSetting $settings): array
    {
        $width = self::normalizePageWidth($settings->getAttribute('public_page_width'));
        $padding = min(self::normalizeContentPadding($settings->getAttribute('public_content_padding')), (int) floor(($width - 320) / 2));
        $artWidth = max(320, $width - (2 * $padding));

        return [
            'shell' => $width.'px',
            'art' => $artWidth.'px',
            'padding' => $padding.'px',
        ];
    }

    private static function normalizeDimension(
        mixed $value,
        int $default,
        int $min,
        int $max,
        string $field,
        string $label,
    ): int {
        if ($value === null || $value === '') {
            return $default;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([$field => $label.' must be a whole number.']);
        }

        $dimension = (int) $value;
        if ($dimension < $min || $dimension > $max) {
            throw ValidationException::withMessages([$field => sprintf('%s must be between %d and %d pixels.', $label, $min, $max)]);
        }

        return $dimension;
    }
}
