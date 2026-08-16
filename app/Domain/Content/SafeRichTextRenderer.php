<?php

namespace App\Domain\Content;

use Illuminate\Support\HtmlString;

final class SafeRichTextRenderer
{
    private const MAX_NESTING = 8;

    public function __construct(private readonly SafeLinkPolicy $links = new SafeLinkPolicy) {}

    public function render(string $source): HtmlString
    {
        if (preg_match('/<\/?[A-Za-z][^>]*>|<!--[\s\S]*?-->|<!DOCTYPE\b[^>]*>/i', $source) === 1) {
            throw new UnsafeRichTextException('Raw HTML is not accepted by the rich-text boundary.');
        }

        $blocks = preg_split("/\r?\n\r?\n/", trim($source));
        if ($source === '' || trim($source) === '') {
            return new HtmlString('');
        }

        $html = [];
        $list = null;

        foreach ($blocks as $block) {
            $lines = preg_split('/\r?\n/', $block) ?: [];
            $first = $lines[0] ?? '';
            $listType = preg_match('/^(?:[-*])\s+/', $first) === 1 ? 'ul'
                : (preg_match('/^\d+\.\s+/', $first) === 1 ? 'ol' : null);

            if ($listType !== null) {
                if ($list !== $listType) {
                    if ($list !== null) {
                        $html[] = "</{$list}>";
                    }
                    $html[] = "<{$listType}>";
                    $list = $listType;
                }

                foreach ($lines as $line) {
                    $pattern = $listType === 'ul' ? '/^[-*]\s+(.+)$/' : '/^\d+\.\s+(.+)$/';
                    if (preg_match($pattern, $line, $matches) !== 1) {
                        throw new UnsafeRichTextException('Lists must contain supported list items.');
                    }
                    $html[] = '<li>'.$this->inline($matches[1]).'</li>';
                }

                continue;
            }

            if ($list !== null) {
                $html[] = "</{$list}>";
                $list = null;
            }

            if (preg_match('/^(?:#{1,6}\s|```|>\s)/', $first) === 1 || str_contains($block, '~~') || str_contains($block, '`')) {
                throw new UnsafeRichTextException('Unsupported rich-text syntax.');
            }

            $html[] = '<p>'.$this->inline(implode("\n", $lines)).'</p>';
        }

        if ($list !== null) {
            $html[] = "</{$list}>";
        }

        return new HtmlString(implode("\n", $html));
    }

    private function inline(string $text, int $depth = 0): string
    {
        if ($depth > self::MAX_NESTING) {
            throw new UnsafeRichTextException('Rich-text nesting limit exceeded.');
        }

        $output = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            if (substr($text, $offset, 2) === '![') {
                throw new UnsafeRichTextException('Images are not supported in rich text.');
            }

            if ($text[$offset] === '[') {
                $close = strpos($text, '](', $offset + 1);
                if ($close === false) {
                    throw new UnsafeRichTextException('Malformed rich-text link.');
                }
                $end = strpos($text, ')', $close + 2);
                if ($end === false) {
                    throw new UnsafeRichTextException('Malformed rich-text link.');
                }
                $label = substr($text, $offset + 1, $close - $offset - 1);
                $url = substr($text, $close + 2, $end - $close - 2);
                $safeUrl = $this->links->sanitize($url);
                if ($safeUrl === null) {
                    throw new UnsafeRichTextException('Unsafe link URL.');
                }
                $output .= '<a href="'.e($safeUrl).'">'.$this->inline($label, $depth + 1).'</a>';
                $offset = $end + 1;

                continue;
            }

            foreach (['**' => 'strong', '__' => 'strong', '*' => 'em', '_' => 'em'] as $marker => $tag) {
                if (substr($text, $offset, strlen($marker)) === $marker) {
                    $end = strpos($text, $marker, $offset + strlen($marker));
                    if ($end === false) {
                        throw new UnsafeRichTextException('Unbalanced rich-text formatting.');
                    }
                    $inner = substr($text, $offset + strlen($marker), $end - $offset - strlen($marker));
                    $output .= "<{$tag}>".$this->inline($inner, $depth + 1)."</{$tag}>";
                    $offset = $end + strlen($marker);

                    continue 2;
                }
            }

            if ($text[$offset] === ']' || $text[$offset] === ')' || $text[$offset] === '`' || $text[$offset] === '~') {
                throw new UnsafeRichTextException('Unsupported rich-text syntax.');
            }

            $next = $offset + 1;
            while ($next < $length && in_array($text[$next], ['[', ']', '(', ')', '*', '_', '`', '~'], true) === false) {
                $next++;
            }
            $output .= nl2br(e(substr($text, $offset, $next - $offset)), false);
            $offset = $next;
        }

        return $output;
    }
}
