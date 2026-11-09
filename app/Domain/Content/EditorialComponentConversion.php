<?php

namespace App\Domain\Content;

use App\Models\CustomPageSetting;
use Illuminate\Validation\ValidationException;

/**
 * Shared loss-aware component conversion for Home and Custom Page.
 * Both editorial services retain their own transactional/audit boundaries.
 */
final class EditorialComponentConversion
{
    /** @param array<string,mixed> $block
     * @return array<string,mixed>
     */
    public static function convert(array $block, string $targetType, bool $insideImage = false): array
    {
        self::assertType($targetType);

        $title = in_array($block['type'] ?? null, ['text', 'list', 'contact'], true)
            && is_string($block['title'] ?? null) ? $block['title'] : null;
        $published = CustomPageSetting::componentPublished($block);

        return match ($targetType) {
            'image' => ['type' => 'image', 'published' => $published, 'media_asset_id' => null, 'image_decorative' => false],
            'text' => ['type' => 'text', 'published' => $published, 'title' => $title, 'body' => null],
            'list' => ['type' => 'list', 'published' => $published, 'title' => $title, 'media_asset_id' => null, 'items' => []],
            'divider' => ['type' => 'divider', 'published' => $published, 'variant' => 'thin'],
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'title' => $title,
                'children' => [
                    ['type' => 'public_email', 'published' => true],
                    ['type' => 'social_links', 'published' => true, 'social_platforms' => array_keys(SocialLinks::options())],
                    ...(! $insideImage ? [['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null]] : []),
                ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

    /** @param array<string,mixed> $block */
    public static function losesContent(array $block, string $targetType): bool
    {
        $next = self::convert($block, $targetType);
        if (($block['type'] ?? null) === $targetType) {
            return false;
        }
        foreach ($block as $key => $value) {
            if (in_array($key, ['type', 'published'], true)) {
                continue;
            }
            if (array_key_exists($key, $next) && $next[$key] === $value) {
                continue;
            }
            if (self::meaningfulValue($value)) {
                return true;
            }
        }

        return false;
    }

    private static function assertType(string $type): void
    {
        if (! in_array($type, CustomPageSetting::COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }
    }

    private static function meaningfulValue(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === []) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            foreach ($value as $nested) {
                if (self::meaningfulValue($nested)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
