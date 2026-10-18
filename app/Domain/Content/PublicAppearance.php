<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;
use Illuminate\Validation\ValidationException;

final class PublicAppearance
{
    public const MODE_DEFAULT = 'default';

    public const MODE_SOLID = 'solid';

    public const MODE_GRADIENT = 'gradient';
    public const MODE_RADIAL = 'radial';
    public const MODE_CONIC = 'conic';
    public const MAX_STOPS = 12;
    public const DEFAULT_REPEAT_SPAN = 120;

    public const DEFAULT_PAGE_COLOR = '#777777';
    public const DEFAULT_SURFACE_COLOR = '#FFFFFF';
    public const SURFACE_PREFIXES = ['background', 'menu_background', 'page_background'];

    public const DEFAULT_GRADIENT_ANGLE = 180;

    public const HEADER_POSITIONS = ['outside' => 'Beside menu', 'inside' => 'Inside menu', 'below' => 'Below menu'];
    public const HEADER_CAPS = ['small-caps' => 'Small caps', 'normal' => 'Original case'];

    public static function headerPosition(mixed $value): string
    {
        $value ??= 'inside';
        if (! is_string($value) || ! array_key_exists($value, self::HEADER_POSITIONS)) {
            throw ValidationException::withMessages(['header_name_position' => 'Choose a supported name position.']);
        }

        return $value;
    }

    public static function headerCaps(mixed $value): string
    {
        $value ??= 'small-caps';
        if (! is_string($value) || ! array_key_exists($value, self::HEADER_CAPS)) {
            throw ValidationException::withMessages(['header_name_caps' => 'Choose a supported letter style.']);
        }

        return $value;
    }

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
            self::MODE_RADIAL => 'Radial gradient',
            self::MODE_CONIC => 'Conic gradient',
        ];
    }

    public static function normalizeMode(mixed $value, string $field = 'background_mode'): ?string
    {
        if ($value === null || $value === '' || $value === self::MODE_DEFAULT) {
            return null;
        }

        if (! is_string($value) || ! in_array($value, [self::MODE_SOLID, self::MODE_GRADIENT, self::MODE_RADIAL, self::MODE_CONIC], true)) {
            throw ValidationException::withMessages([
                $field => 'Choose Solid, Linear, Radial or Conic gradient.',
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
    public static function isGradient(?string $mode): bool
    {
        return in_array($mode, [self::MODE_GRADIENT, self::MODE_RADIAL, self::MODE_CONIC], true);
    }

    /** @return list<array{color:string,position:int}> */
    public static function stops(PublicContentSetting $settings, string $surface): array
    {
        $current = $settings->getAttribute($surface.'_gradient_stops');
        if (is_array($current)) {
            return self::normalizeStops($current, $surface.'_gradient_stops');
        }

        $fallback = $surface === 'background' ? self::DEFAULT_PAGE_COLOR : self::DEFAULT_SURFACE_COLOR;
        return [
            ['color' => self::normalizeColor($settings->getAttribute($surface.'_gradient_start'), $surface.'_gradient_start') ?? $fallback, 'position' => 0],
            ['color' => self::normalizeColor($settings->getAttribute($surface.'_gradient_end'), $surface.'_gradient_end') ?? $fallback, 'position' => 100],
        ];
    }

    /**
     * @return list<array{color:string,position:int}>|null
     */
    public static function normalizeStops(mixed $value, string $field): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || count($value) < 2 || count($value) > self::MAX_STOPS) {
            throw ValidationException::withMessages([$field => 'Choose between 2 and '.self::MAX_STOPS.' color stops.']);
        }

        $result = [];
        foreach (array_values($value) as $stop) {
            if (! is_array($stop) || ! array_key_exists('position', $stop)) {
                throw ValidationException::withMessages([$field => 'Each stop needs a color and position.']);
            }
            $color = self::normalizeColor($stop['color'] ?? null, $field);
            $position = filter_var($stop['position'], FILTER_VALIDATE_INT);
            if ($color === null || $position === false || $position < 0 || $position > 100) {
                throw ValidationException::withMessages([$field => 'Color stops require a color and a position between 0 and 100 percent.']);
            }
            $result[] = ['color' => $color, 'position' => $position];
        }

        // Position, rather than insertion order, defines the color sequence.
        // Stable order for identical positions preserves deliberate hard edges.
        usort($result, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        if ($result[0]['position'] !== 0 || $result[count($result) - 1]['position'] !== 100) {
            throw ValidationException::withMessages([$field => 'The first stop must be at 0% and the last at 100%.']);
        }
        return $result;
    }

    public static function normalizeRepeating(mixed $value, string $field): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            throw ValidationException::withMessages([$field => 'Repeating must be enabled or disabled.']);
        }
        return (bool) $value;
    }

    public static function normalizeRepeatSpan(mixed $value, string $field): int
    {
        return self::normalizeInteger($value, 120, 16, 360, $field, 'Repeat span');
    }

    public static function normalizeCenter(mixed $value, string $field): int
    {
        return self::normalizeInteger($value, 50, 0, 100, $field, 'Center');
    }

    public static function normalizeShape(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            return 'circle';
        }
        if (! is_string($value) || ! in_array($value, ['circle', 'ellipse'], true)) {
            throw ValidationException::withMessages([$field => 'Choose circle or ellipse.']);
        }
        return $value;
    }

    private static function normalizeInteger(mixed $value, int $default, int $min, int $max, string $field, string $label): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $min || $number > $max) {
            throw ValidationException::withMessages([$field => $label.' must be between '.$min.' and '.$max.'.']);
        }
        return $number;
    }

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
            return self::normalizeColor($settings->getAttribute($surface.'_color'), $surface.'_color') ?? $fallback;
        }

        $repeating = self::normalizeRepeating($settings->getAttribute($surface.'_gradient_repeating'), $surface.'_gradient_repeating');
        $span = self::normalizeRepeatSpan($settings->getAttribute($surface.'_gradient_repeat_span'), $surface.'_gradient_repeat_span');
        $angle = self::normalizeAngle($settings->getAttribute($surface.'_gradient_angle'), $surface.'_gradient_angle')
            ?? self::DEFAULT_GRADIENT_ANGLE;
        $centerX = self::normalizeCenter($settings->getAttribute($surface.'_gradient_center_x'), $surface.'_gradient_center_x');
        $centerY = self::normalizeCenter($settings->getAttribute($surface.'_gradient_center_y'), $surface.'_gradient_center_y');
        $shape = self::normalizeShape($settings->getAttribute($surface.'_gradient_shape'), $surface.'_gradient_shape');
        $stops = self::stops($settings, $surface);
        $parts = array_map(static function (array $stop) use ($repeating, $span, $mode): string {
            $position = $stop['position'];
            if ($repeating) {
                $value = ($position * $span) / 100;
                $unit = $mode === self::MODE_CONIC ? 'deg' : 'px';
                $point = rtrim(rtrim(sprintf('%.2f', $value), '0'), '.').$unit;
            } else {
                $point = $position.'%';
            }
            return $stop['color'].' '.$point;
        }, $stops);

        // Keep old two-color gradients byte-identical until a stop list is
        // edited, so existing publication checkpoints remain stable.
        if (! $repeating && $settings->getAttribute($surface.'_gradient_stops') === null) {
            $parts = array_column($stops, 'color');
        }

        $prefix = $repeating ? 'repeating-' : '';
        $css = match ($mode) {
            self::MODE_GRADIENT => $prefix.'linear-gradient('.$angle.'deg, '.implode(', ', $parts).')',
            self::MODE_RADIAL => $prefix.'radial-gradient('.$shape.' at '.$centerX.'% '.$centerY.'%, '.implode(', ', $parts).')',
            self::MODE_CONIC => $prefix.'conic-gradient(from '.$angle.'deg at '.$centerX.'% '.$centerY.'%, '.implode(', ', $parts).')',
            default => throw new \LogicException('Unexpected gradient mode.'),
        };

        return $surface === 'background' ? $css.' fixed' : $css;
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
