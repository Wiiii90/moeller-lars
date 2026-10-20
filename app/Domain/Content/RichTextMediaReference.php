<?php

namespace App\Domain\Content;

final class RichTextMediaReference
{
    private const MARKDOWN_PATTERN = '/!\[([^\r\n]*?)\]\(media:(\d+)\)/';

    // New visual rich content stores the canonical MediaAsset id in data-id.
    private const HTML_IMAGE_PATTERN = '/<img\\b(?=[^>]*\\bdata-id\\s*=\\s*[\"\\\'](\\d+)[\"\\\'])[^>]*>/i';

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

        $id = (int) $matches[1];

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
            $id = (int) $match[2];
            if ($id <= 0) {
                continue;
            }

            $alt = self::unescapeAlt($match[1]);
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
        $legacy = collect(self::references($source))->pluck('media_asset_id')->all();
        preg_match_all(self::HTML_IMAGE_PATTERN, $source, $matches);
        $visual = array_map('intval', $matches[1] ?? []);

        return array_values(array_unique(array_filter([...$legacy, ...$visual], static fn (int $id): bool => $id > 0)));
    }

    public static function remove(string $source, int $mediaAssetId): string
    {
        $clean = preg_replace_callback(
            self::MARKDOWN_PATTERN,
            static fn (array $matches): string => (int) $matches[2] === $mediaAssetId ? '' : (string) $matches[0],
            $source,
        );
        if (! is_string($clean)) {
            return $source;
        }

        // MediaAssets are shared across Journal, Home and Custom Pages.
        // Deletion must also remove visual-editor nodes, not just Markdown.
        $clean = preg_replace_callback(
            self::HTML_IMAGE_PATTERN,
            static fn (array $match): string => (int) $match[1] === $mediaAssetId ? '' : $match[0],
            $clean,
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
     * @param  list<array<string, mixed>>  $blocks
     * @return list<int>
     */
    public static function idsFromCustomPageBlocks(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
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
