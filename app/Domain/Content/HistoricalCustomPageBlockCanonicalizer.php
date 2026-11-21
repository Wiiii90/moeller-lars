<?php

namespace App\Domain\Content;

/**
 * Converts historical component payloads at database migration and checkpoint
 * restore boundaries. Live application code only consumes the current shape.
 */
final class HistoricalCustomPageBlockCanonicalizer
{
    public static function canonicalize(mixed $blocks, ?callable $isVideoMedia = null): mixed
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            return $blocks;
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                $normalized[] = $block;

                continue;
            }

            if (($block['type'] ?? null) === 'list') {
                foreach (self::convertListToRichText($block) as $converted) {
                    $normalized[] = $converted;
                }

                continue;
            }

            if (($block['type'] ?? null) === 'image'
                && is_numeric($block['media_asset_id'] ?? null)
                && $isVideoMedia !== null
                && $isVideoMedia((int) $block['media_asset_id'])) {
                $block['type'] = 'video';
            }

            if (in_array($block['type'] ?? null, ['image', 'video'], true)
                && is_array($block['children'] ?? null)) {
                $block['children'] = self::canonicalize($block['children'], $isVideoMedia);
            }

            if (($block['type'] ?? null) === 'contact') {
                $block = self::canonicalizeContact($block);
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * A legacy List is not an editor type. Keep every authored field, media
     * reference and entry publication choice as ordinary editable components.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    private static function convertListToRichText(array $block): array
    {
        $result = [];
        $published = ($block['published'] ?? true) === true;

        if (is_numeric($block['media_asset_id'] ?? null)) {
            $result[] = [
                'type' => 'image',
                'published' => $published,
                'media_asset_id' => (int) $block['media_asset_id'],
                'image_decorative' => false,
            ];
        }

        $heading = is_string($block['title'] ?? null) ? trim($block['title']) : '';
        if ($heading !== '') {
            $result[] = ['type' => 'text', 'published' => $published, 'title' => $heading, 'body' => null];
        }

        $items = $block['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            throw new \UnexpectedValueException('Legacy List entries must be ordered before canonicalization.');
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new \UnexpectedValueException('A legacy List entry is not structured data.');
            }

            $itemPublished = $published && ($item['published'] ?? $item['visible'] ?? true) === true;
            $itemTitle = is_string($item['title'] ?? null) ? trim($item['title']) : '';

            $context = is_string($item['context'] ?? null) ? trim($item['context']) : '';
            if ($context === '') {
                $context = implode(' · ', array_filter(
                    array_map(static fn (string $key): string => is_string($item[$key] ?? null)
                        ? trim($item[$key]) : '', ['date', 'meta', 'location']),
                    static fn (string $value): bool => $value !== '',
                ));
            }

            $body = is_string($item['body'] ?? null) ? trim($item['body']) : '';
            if ($body === '<p></p>') {
                $body = '';
            }
            // Rich Text JSON and legacy Markdown must remain byte-for-byte valid.
            // Keep context and optional URL separate instead of concatenating
            // them with a potentially visual Rich Text document.
            if ($itemTitle !== '' || $body !== '') {
                $result[] = [
                    'type' => 'text', 'published' => $itemPublished,
                    'title' => $itemTitle !== '' ? $itemTitle : null,
                    'body' => $body !== '' ? $body : null,
                ];
            }
            if ($context !== '') {
                $result[] = ['type' => 'text', 'published' => $itemPublished, 'title' => null, 'body' => $context];
            }

            $url = is_string($item['url'] ?? null) ? trim($item['url']) : '';
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $result[] = [
                    'type' => 'text',
                    'published' => $itemPublished,
                    'title' => null,
                    'body' => '[More information]('.str_replace(['(', ')'], ['%28', '%29'], $url).')',
                ];
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $block
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
            $block['children'] = [
                ['type' => 'public_email', 'published' => (bool) ($block['show_email'] ?? true)],
                ['type' => 'social_links', 'published' => true, 'social_platforms' => $platforms],
                ['type' => 'contact_form', 'published' => (bool) ($block['show_form'] ?? true) && $formState !== 'hidden'],
            ];
        }

        unset($block['show_email'], $block['show_form'], $block['form_state'],
            $block['social_platforms'], $block['status_text']);

        return $block;
    }
}
