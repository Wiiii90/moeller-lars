<?php

namespace App\Domain\Content;

/**
 * Canonicalizes historical Custom Page payloads when they cross an explicit
 * migration/restore boundary. Normal editorial/runtime code must only consume
 * the canonical schema and must not call this helper.
 */
final class HistoricalCustomPageBlockCanonicalizer
{
    public static function canonicalize(mixed $blocks): mixed
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            return $blocks;
        }

        return array_map(static function (mixed $block): mixed {
            if (! is_array($block)) {
                return $block;
            }

            if (($block['type'] ?? null) === 'list') {
                $block['items'] = self::canonicalizeListItems($block['items'] ?? null);
            }

            if (($block['type'] ?? null) === 'contact') {
                $block = self::canonicalizeContact($block);
            }

            return $block;
        }, $blocks);
    }

    private static function canonicalizeListItems(mixed $items): mixed
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return $items;
        }

        return array_map(static function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            if (! array_key_exists('published', $item) && array_key_exists('visible', $item)) {
                $item['published'] = $item['visible'] === true;
            }
            unset($item['visible']);

            return $item;
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function canonicalizeContact(array $block): array
    {
        $children = $block['children'] ?? null;
        if (! is_array($children) || ! array_is_list($children)) {
            $formState = is_string($block['form_state'] ?? null) ? $block['form_state'] : 'enabled';
            $platforms = array_values(array_unique(array_filter(
                is_array($block['social_platforms'] ?? null) ? $block['social_platforms'] : [],
                static fn (mixed $platform): bool => is_string($platform),
            )));
            $statusText = is_string($block['status_text'] ?? null) ? trim($block['status_text']) : null;
            if ($statusText === '') {
                $statusText = null;
            }

            $block['children'] = [
                [
                    'type' => 'public_email',
                    'published' => (bool) ($block['show_email'] ?? true),
                ],
                [
                    'type' => 'social_links',
                    'published' => true,
                    'social_platforms' => $platforms,
                ],
                [
                    'type' => 'contact_form',
                    'published' => (bool) ($block['show_form'] ?? true) && $formState !== 'hidden',
                    'form_state' => $formState === 'under_construction' ? 'under_construction' : 'enabled',
                    'status_text' => $statusText,
                ],
            ];
        }

        unset(
            $block['show_email'],
            $block['show_form'],
            $block['form_state'],
            $block['social_platforms'],
            $block['status_text'],
        );

        return $block;
    }
}
