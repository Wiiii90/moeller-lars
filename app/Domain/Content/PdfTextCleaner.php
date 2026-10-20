<?php

namespace App\Domain\Content;

final class PdfTextCleaner
{
    /** Suggests readable paragraphs; the editor must show the result before committing. */
    public static function clean(string $text): string
    {
        $source = preg_replace('/[\\x{00AD}]/u', '', str_replace(["\r\n", "\r"], "\n", $text));
        if (! is_string($source)) {
            return $text;
        }
        $paragraphs = preg_split('/\\n[ \\t]*\\n+/u', trim($source)) ?: [];
        $cleaned = [];
        foreach ($paragraphs as $paragraph) {
            $lines = array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                explode("\n", $paragraph),
            ), static fn (string $line): bool => $line !== ''));
            if ($lines === []) {
                continue;
            }
            // Never join list items or change explicit hyphenation without
            // knowing whether the artist intended the break.
            if (count($lines) > 1 && preg_grep('/^(?:[-*•]|\\d+[.)])\\s/u', $lines)) {
                $cleaned[] = implode("\n", $lines);
                continue;
            }
            $cleaned[] = implode(' ', $lines);
        }

        return implode("\n\n", $cleaned);
    }
}
