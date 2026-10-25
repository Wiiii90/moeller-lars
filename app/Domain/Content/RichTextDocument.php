<?php

namespace App\Domain\Content;

use Illuminate\Support\Str;

final class RichTextDocument
{
    /** Do not migrate stored legacy content just because the editor is opened. */
    public static function isVisual(string $source): bool
    {
        return preg_match('/^\\s*<(?:p|h[1-6]|blockquote|ul|ol|table|div|img|hr|pre|figure|details|section)\\b/i', $source) === 1;
    }

    public static function forEditor(string $source): string
    {
        if ($source === '' || self::isVisual($source)) {
            return $source;
        }

        // The old canonical references must become TipTap image nodes with
        // stable MediaAsset IDs, not URLs or duplicate uploads.
        $references = [];
        $markdown = preg_replace_callback(
            '/!\\[([^\\r\\n]*?)\\]\\(media:(\\d+)\\)/',
            static function (array $match) use (&$references): string {
                $key = 'RICHTEXTMEDIAREF'.count($references).'TOKEN';
                $references[$key] = '<img data-id="'.(int) $match[2].'" alt="'.e($match[1]).'">';
                return $key;
            },
            $source,
        );
        $html = Str::markdown(is_string($markdown) ? $markdown : $source, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        return str_replace(array_keys($references), array_values($references), $html);
    }
}
