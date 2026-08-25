<?php

namespace App\Domain\Content;

use Illuminate\Validation\ValidationException;

final class JournalEntryContent
{
    private const EMBED_PATTERN = '/^\[\[journal-image:([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\]\][ \t]*$/mi';

    public function __construct(
        private readonly SafeRichTextRenderer $richText,
    ) {}

    /** @return list<array{type:string,data:array<string,mixed>}> */
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

    /** @param list<array{type:string,data:array<string,mixed>}> $blocks */
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

    /** @param list<string> $allowedEmbedKeys */
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

    private function validEmbedKey(string $key): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $key) === 1;
    }
}
