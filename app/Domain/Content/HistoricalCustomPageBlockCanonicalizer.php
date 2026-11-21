<?php

namespace App\Domain\Content;

/**
 * Converts historical component payloads at database migration and checkpoint
 * restore boundaries. Live application code only consumes the current shape.
 */
final class HistoricalCustomPageBlockCanonicalizer
{
    public static function canonicalize(mixed $blocks, ?callable $isVideoMedia = null, string $path = 'root'): mixed
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            return $blocks;
        }

        $normalized = [];
        foreach ($blocks as $blockIndex => $block) {
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

            $identity = $path.'.'.$blockIndex;

            if (($block['type'] ?? null) === 'divider') {
                $gridId = self::stableId('divider-grid', $identity);
                $areaId = self::stableId('divider-area', $identity);
                $grid = [
                    'type' => 'grid',
                    'published' => ($block['published'] ?? true) === true,
                    'id' => $gridId,
                    'columns' => 1,
                    'rows' => 1,
                    'gap' => 'none',
                    'areas' => [['id' => $areaId, 'border' => $block['variant'] ?? 'thin']],
                ];
                if (is_string($block['layout_area_id'] ?? null)) {
                    $grid['layout_area_id'] = $block['layout_area_id'];
                }
                $normalized[] = $grid;

                continue;
            }

            if (($block['type'] ?? null) === 'image'
                && is_numeric($block['media_asset_id'] ?? null)
                && $isVideoMedia !== null
                && $isVideoMedia((int) $block['media_asset_id'])) {
                $block['type'] = 'video';
            }

            if (in_array($block['type'] ?? null, ['image', 'video'], true)
                && is_array($block['children'] ?? null) && $block['children'] !== []) {
                $children = self::canonicalize($block['children'], $isVideoMedia, $identity.'.children');
                $mediaRight = ($block['image_alignment'] ?? 'left') === 'right';
                $imageArea = self::stableId('media-image', $identity);
                $copyArea = self::stableId('media-copy', $identity);
                $grid = [
                    'type' => 'grid',
                    'published' => ($block['published'] ?? true) === true,
                    'id' => self::stableId('media-grid', $identity),
                    'columns' => 2,
                    'column_layout' => $mediaRight ? 'left-wide' : 'right-wide',
                    'rows' => 1,
                    'gap' => 'medium',
                    'areas' => [
                        ['id' => $mediaRight ? $copyArea : $imageArea, 'border' => 'none'],
                        ['id' => $mediaRight ? $imageArea : $copyArea, 'border' => 'none'],
                    ],
                ];
                if (is_string($block['layout_area_id'] ?? null)) {
                    $grid['layout_area_id'] = $block['layout_area_id'];
                }
                if (isset($block['layout_divider_before'])) {
                    $grid['layout_divider_before'] = $block['layout_divider_before'];
                    unset($block['layout_divider_before']);
                }
                unset($block['children'], $block['layout_area_id']);
                $block['layout_area_id'] = $imageArea;
                $normalized[] = $grid;
                $normalized[] = $block;
                foreach ($children as $child) {
                    $child['layout_area_id'] = $copyArea;
                    $normalized[] = $child;
                }

                continue;
            }
            // Empty historical children do not become a separate layout.
            if (in_array($block['type'] ?? null, ['image', 'video'], true)) {
                unset($block['children']);
            }

            if (($block['type'] ?? null) === 'contact') {
                $block = self::canonicalizeContact($block);
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * Stable IDs must survive repeat migration and checkpoint restore for the
     * same historical page. Paths distinguish multiple identical components.
     */
    private static function stableId(string $kind, string $path): string
    {
        return $kind.'-'.substr(hash('sha256', $path), 0, 24);
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
