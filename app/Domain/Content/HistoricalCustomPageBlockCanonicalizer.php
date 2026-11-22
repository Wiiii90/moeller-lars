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

            $identity = $path.'.'.$blockIndex;
            if (($block['type'] ?? null) === 'grid') {
                $block['type'] = 'layout';
                $block['layout_mode'] = 'rows';
            }

            if (($block['type'] ?? null) === 'divider') {
                $layoutId = self::stableId('divider-layout', $identity);
                $areaId = self::stableId('divider-area', $identity);
                $layout = [
                    'type' => 'layout',
                    'layout_mode' => 'rows',
                    'published' => ($block['published'] ?? true) === true,
                    'id' => $layoutId,
                    'columns' => 1,
                    'rows' => 1,
                    'gap' => 'none',
                    'areas' => [['id' => $areaId, 'border' => $block['variant'] ?? 'thin']],
                ];
                if (is_string($block['layout_area_id'] ?? null)) {
                    $layout['layout_area_id'] = $block['layout_area_id'];
                }
                $normalized[] = $layout;

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
                $layout = [
                    'type' => 'layout',
                    'layout_mode' => 'rows',
                    'published' => ($block['published'] ?? true) === true,
                    'id' => self::stableId('media-layout', $identity),
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
                    $layout['layout_area_id'] = $block['layout_area_id'];
                }
                if (isset($block['layout_divider_before'])) {
                    $layout['layout_divider_before'] = $block['layout_divider_before'];
                    unset($block['layout_divider_before']);
                }
                unset($block['children'], $block['layout_area_id']);
                $block['layout_area_id'] = $imageArea;
                $normalized[] = $layout;
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
