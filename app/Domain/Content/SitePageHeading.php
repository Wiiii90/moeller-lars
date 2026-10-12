<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use Illuminate\Validation\ValidationException;

/**
 * One content-heading contract for all public SiteSection pages.
 * Navigation/title identity remains independent of the visible heading.
 */
final class SitePageHeading
{
    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'];

    public const DIVIDERS = [
        'none' => 'None',
        'thin' => 'Thin',
        'subtle' => 'Subtle',
        'strong' => 'Strong',
        'dotted' => 'Dotted',
    ];

    /** @return array<string, mixed> */
    public static function formState(SiteSection $section): array
    {
        return [
            'content_heading_enabled' => (bool) $section->getAttribute('content_heading_enabled'),
            'content_heading_override' => $section->getAttribute('content_heading_override'),
            'content_heading_alignment' => $section->getAttribute('content_heading_alignment') ?: 'left',
            'content_heading_divider' => $section->getAttribute('content_heading_divider') ?: 'none',
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function validated(array $data): array
    {
        $override = $data['content_heading_override'] ?? null;
        if ($override !== null && ! is_string($override)) {
            throw ValidationException::withMessages(['content_heading_override' => 'Enter a valid heading.']);
        }
        $override = is_string($override) ? trim($override) : null;
        if ($override === '') {
            $override = null;
        }
        if ($override !== null && mb_strlen($override) > 160) {
            throw ValidationException::withMessages(['content_heading_override' => 'The heading must not exceed 160 characters.']);
        }
        $alignment = $data['content_heading_alignment'] ?? 'left';
        $divider = $data['content_heading_divider'] ?? 'none';
        if (! is_string($alignment) || ! array_key_exists($alignment, self::ALIGNMENTS)) {
            throw ValidationException::withMessages(['content_heading_alignment' => 'Choose a supported alignment.']);
        }
        if (! is_string($divider) || ! array_key_exists($divider, self::DIVIDERS)) {
            throw ValidationException::withMessages(['content_heading_divider' => 'Choose a supported divider.']);
        }

        return [
            'content_heading_enabled' => ($data['content_heading_enabled'] ?? false) === true,
            'content_heading_override' => $override,
            'content_heading_alignment' => $alignment,
            'content_heading_divider' => $divider,
        ];
    }

    public static function text(SiteSection $section): string
    {
        $override = trim((string) ($section->getAttribute('content_heading_override') ?? ''));

        return $override !== '' ? $override : (string) $section->getAttribute('title');
    }
}
