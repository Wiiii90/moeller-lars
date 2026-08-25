<?php

namespace App\Domain\Content;

use Illuminate\Validation\ValidationException;

final class JournalEntryContent
{
    public const INLINE_IMAGE_BLOCK_ID = 'journalInlineImage';

    private const EMBED_PATTERN = '/^\[\[journal-image:([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\]\][ \t]*$/mi';

    public function __construct(private readonly SafeRichTextRenderer $richText) {}

    public function blocks(?string $source): array
    {
        $source = is_string($source) ? trim($source) : '';
        if ($source === '') {
            return [['type' => 'text', 'data' => ['markdown' => '']]];
        }
        $parts = preg_split(self::EMBED_PATTERN, $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            return [['type' => 'text', 'data' => ['markdown' => $source]]];
        }
        $blocks = [];
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $blocks[] = ['type' => 'image', 'data' => ['embed_key' => strtolower($part)]];
                continue;
            }
            $markdown = trim($part);
            if ($markdown !== '') {
                $blocks[] = ['type' => 'text', 'data' => ['markdown' => $markdown]];
            }
        }
        return $blocks === [] ? [['type' => 'text', 'data' => ['markdown' => '']]] : $blocks;
    }

    public function editorBlocks(array $document): array
    {
        if (($document['type'] ?? null) !== 'doc' || ! is_array($document['content'] ?? null)) {
            throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']);
        }
        $blocks = [];
        $textNodes = [];
        $flushText = function () use (&$blocks, &$textNodes): void {
            if ($textNodes === []) {
                return;
            }
            $markdown = trim(implode("\n\n", $textNodes));
            if ($markdown !== '') {
                $blocks[] = ['type' => 'text', 'data' => ['markdown' => $markdown]];
            }
            $textNodes = [];
        };

        foreach ($document['content'] as $node) {
            if (! is_array($node)) {
                throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']);
            }
            if (($node['type'] ?? null) === 'customBlock') {
                $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
                if (($attrs['id'] ?? null) !== self::INLINE_IMAGE_BLOCK_ID) {
                    throw ValidationException::withMessages(['content_blocks' => 'Journal content contains an unsupported embedded block.']);
                }
                $config = is_array($attrs['config'] ?? null) ? $attrs['config'] : [];
                $flushText();
                $blocks[] = ['type' => 'image', 'data' => [
                    'embed_key' => $config['embed_key'] ?? null,
                    'media_asset_id' => $config['media_asset_id'] ?? null,
                    'alt_text_override' => $config['alt_text_override'] ?? null,
                ]];
                continue;
            }
            $textNodes[] = $this->blockMarkdown($node);
        }
        $flushText();
        return $blocks === [] ? [['type' => 'text', 'data' => ['markdown' => '']]] : $blocks;
    }

    public function serialize(array $blocks): string
    {
        $segments = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if ($type === 'text') {
                $markdown = trim((string) ($data['markdown'] ?? ''));
                if ($markdown !== '') {
                    $this->richText->assertValid($markdown);
                    $segments[] = $markdown;
                }
                continue;
            }
            if ($type === 'image') {
                $key = strtolower(trim((string) ($data['embed_key'] ?? '')));
                if (! $this->validEmbedKey($key)) {
                    throw ValidationException::withMessages(['content_blocks' => 'Inline images require a valid internal media reference.']);
                }
                $segments[] = '[[journal-image:'.$key.']]';
                continue;
            }
            throw ValidationException::withMessages(['content_blocks' => 'Journal content contains an unsupported block.']);
        }
        return implode("\n\n", $segments);
    }

    public function assertValid(string $source, array $allowedEmbedKeys): void
    {
        $allowed = array_fill_keys(array_map('strtolower', $allowedEmbedKeys), true);
        foreach ($this->blocks($source) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $markdown = trim((string) ($block['data']['markdown'] ?? ''));
                if ($markdown !== '') {
                    $this->richText->assertValid($markdown);
                }
                continue;
            }
            $key = strtolower((string) ($block['data']['embed_key'] ?? ''));
            if (! isset($allowed[$key])) {
                throw ValidationException::withMessages(['content_blocks' => 'Journal content contains a missing inline image reference.']);
            }
        }
    }

    public function removeEmbed(string $source, string $embedKey): string
    {
        $quoted = preg_quote(strtolower($embedKey), '/');
        $clean = preg_replace('/^[ \t]*\[\[journal-image:'.$quoted.'\]\][ \t]*(?:\R{1,2})?/mi', '', $source);
        $clean = is_string($clean) ? preg_replace('/\R{3,}/', "\n\n", $clean) : '';
        return trim(is_string($clean) ? $clean : '');
    }

    private function blockMarkdown(array $node): string
    {
        return match ($node['type'] ?? null) {
            'paragraph' => $this->inlineMarkdown($node['content'] ?? []),
            'bulletList' => $this->listMarkdown($node, false),
            'orderedList' => $this->listMarkdown($node, true),
            default => throw ValidationException::withMessages(['content_blocks' => 'Journal content contains unsupported rich-text formatting.']),
        };
    }

    private function inlineMarkdown(mixed $nodes): string
    {
        if ($nodes === null) {
            return '';
        }
        if (! is_array($nodes)) {
            throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']);
        }
        $markdown = '';
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']);
            }
            if (($node['type'] ?? null) === 'hardBreak') {
                $markdown .= "  \n";
                continue;
            }
            if (($node['type'] ?? null) !== 'text') {
                throw ValidationException::withMessages(['content_blocks' => 'Journal content contains unsupported inline formatting.']);
            }
            $text = $this->escapeMarkdown((string) ($node['text'] ?? ''));
            $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];
            foreach (array_reverse($marks) as $mark) {
                if (! is_array($mark)) {
                    throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']);
                }
                $text = match ($mark['type'] ?? null) {
                    'bold' => '**'.$text.'**',
                    'italic' => '*'.$text.'*',
                    'link' => $this->linkMarkdown($text, $mark),
                    default => throw ValidationException::withMessages(['content_blocks' => 'Journal content contains unsupported inline formatting.']),
                };
            }
            $markdown .= $text;
        }
        return $markdown;
    }

    private function listMarkdown(array $node, bool $ordered, int $depth = 0): string
    {
        $items = $node['content'] ?? null;
        if (! is_array($items)) {
            throw ValidationException::withMessages(['content_blocks' => 'Journal list content is invalid.']);
        }
        $lines = [];
        $number = 1;
        foreach ($items as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'listItem' || ! is_array($item['content'] ?? null)) {
                throw ValidationException::withMessages(['content_blocks' => 'Journal list content is invalid.']);
            }
            $content = $item['content'];
            $paragraph = array_shift($content);
            if (! is_array($paragraph) || ($paragraph['type'] ?? null) !== 'paragraph') {
                throw ValidationException::withMessages(['content_blocks' => 'Journal list items require text.']);
            }
            $prefix = $ordered ? ($number++).'. ' : '- ';
            $lines[] = str_repeat('    ', $depth).$prefix.$this->inlineMarkdown($paragraph['content'] ?? []);
            foreach ($content as $child) {
                if (! is_array($child) || ! in_array($child['type'] ?? null, ['bulletList', 'orderedList'], true)) {
                    throw ValidationException::withMessages(['content_blocks' => 'Journal list content contains unsupported formatting.']);
                }
                $lines[] = $this->listMarkdown($child, ($child['type'] ?? null) === 'orderedList', $depth + 1);
            }
        }
        return implode("\n", $lines);
    }

    private function linkMarkdown(string $text, array $mark): string
    {
        $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
        $href = trim((string) ($attrs['href'] ?? ''));
        if ($href === '') {
            throw ValidationException::withMessages(['content_blocks' => 'Journal links require a URL.']);
        }
        return '['.$text.']('.str_replace(['(', ')'], ['%28', '%29'], $href).')';
    }

    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\`*_{}\[\]()<>#+\-.!|])/', '\\\\$1', $text) ?? $text;
    }

    private function validEmbedKey(string $key): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $key) === 1;
    }
}
