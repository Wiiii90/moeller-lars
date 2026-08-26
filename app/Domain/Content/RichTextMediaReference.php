<?php

namespace App\Domain\Content;

final class RichTextMediaReference
{
    private const MARKDOWN_PATTERN = '/!\[([^\r\n]*?)\]\(media:(\d+)\)/';

    private const URL_PATTERN = '/\Amedia:(\d+)\z/';

    public static function url(int $mediaAssetId): string
    {
        return 'media:'.$mediaAssetId;
    }

    public static function markdown(int $mediaAssetId, ?string $altTextOverride = null): string
    {
        $alt = $altTextOverride === null ? '' : self::escapeAlt($altTextOverride);

        return '!['.$alt.']('.self::url($mediaAssetId).')';
    }

    public static function token(int $mediaAssetId): string
    {
        return self::markdown($mediaAssetId);
    }

    public static function idFromUrl(string $url): ?int
    {
        if (preg_match(self::URL_PATTERN, $url, $matches) !== 1) {
            return null;
        }

        $id = (int) ($matches[1] ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return list<array{media_asset_id:int,alt_text_override:?string}> */
    public static function references(string $source): array
    {
        if ($source === '') {
            return [];
        }

        preg_match_all(self::MARKDOWN_PATTERN, $source, $matches, PREG_SET_ORDER);
        $references = [];

        foreach ($matches as $match) {
            $id = (int) ($match[2] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $alt = self::unescapeAlt((string) ($match[1] ?? ''));
            $references[] = [
                'media_asset_id' => $id,
                'alt_text_override' => $alt === '' ? null : $alt,
            ];
        }

        return $references;
    }

    /** @return list<int> */
    public static function ids(string $source): array
    {
        return collect(self::references($source))
            ->pluck('media_asset_id')
            ->unique()
            ->values()
            ->all();
    }

    public static function remove(string $source, int $mediaAssetId): string
    {
        $clean = preg_replace_callback(
            self::MARKDOWN_PATTERN,
            static fn (array $matches): string => (int) ($matches[2] ?? 0) === $mediaAssetId ? '' : (string) $matches[0],
            $source,
        );
        if (! is_string($clean)) {
            return $source;
        }

        $clean = preg_replace('/(?:\R[ \t]*){3,}/', "\n\n", $clean);

        return trim(is_string($clean) ? $clean : '');
    }

    /**
     * Extract only canonical rich-text media fields from Custom Page blocks.
     * Titles, URLs, labels and other plain-text fields are deliberately ignored.
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<int>
     */
    public static function idsFromCustomPageBlocks(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text' && is_string($block['body'] ?? null)) {
                $ids = array_merge($ids, self::ids($block['body']));
            }

            if (($block['type'] ?? null) !== 'list' || ! is_array($block['items'] ?? null)) {
                continue;
            }

            foreach ($block['items'] as $item) {
                if (is_array($item) && is_string($item['body'] ?? null)) {
                    $ids = array_merge($ids, self::ids($item['body']));
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private static function escapeAlt(string $alt): string
    {
        $alt = preg_replace('/\s+/u', ' ', trim($alt)) ?? trim($alt);

        return str_replace(['\\', ']'], ['\\\\', '\\]'], $alt);
    }

    private static function unescapeAlt(string $alt): string
    {
        return preg_replace('/\\\\(.)/u', '$1', $alt) ?? $alt;
    }
}
