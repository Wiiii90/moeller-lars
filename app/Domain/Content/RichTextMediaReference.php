<?php

namespace App\Domain\Content;

final class RichTextMediaReference
{
    private const MARKDOWN_PATTERN = '/!\[\]\(media:(\d+)\)/';

    private const URL_PATTERN = '/\Amedia:(\d+)\z/';

    public static function url(int $mediaAssetId): string
    {
        return 'media:'.$mediaAssetId;
    }

    public static function markdown(int $mediaAssetId): string
    {
        return '![]('.self::url($mediaAssetId).')';
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

    /** @return list<int> */
    public static function ids(string $source): array
    {
        if ($source === '') {
            return [];
        }

        preg_match_all(self::MARKDOWN_PATTERN, $source, $matches);

        return collect($matches[1] ?? [])
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
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
}
