<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalEntryMediaService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Models\BlogPost;
use App\Models\Exhibition;
use Filament\Forms\Components\RichEditor;

final class JournalEntryEditorState
{
    public function __construct(
        private readonly JournalEntryMediaService $media,
        private readonly SafeRichTextRenderer $richText,
    ) {}

    /** @return array<string, mixed> */
    public function for(BlogPost|Exhibition $entry): array
    {
        $state = $this->media->editorState($entry);
        $blocks = is_array($state['content_blocks'] ?? null) ? $state['content_blocks'] : [];
        $nodes = [];
        $editor = RichEditor::make('content_blocks')->json()->customBlocks([JournalInlineImageBlock::class]);

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if ($type === 'text') {
                $markdown = trim((string) ($data['markdown'] ?? ''));
                if ($markdown === '') {
                    continue;
                }

                $document = $editor->getTipTapEditor()
                    ->setContent($this->richText->render($markdown)->toHtml())
                    ->getDocument();
                foreach ((array) ($document['content'] ?? []) as $node) {
                    if (is_array($node)) {
                        $nodes[] = $node;
                    }
                }
                continue;
            }

            if ($type === 'image') {
                $nodes[] = [
                    'type' => 'customBlock',
                    'attrs' => [
                        'id' => JournalInlineImageBlock::getId(),
                        'config' => [
                            'embed_key' => $data['embed_key'] ?? null,
                            'media_asset_id' => $data['media_asset_id'] ?? null,
                            'alt_text_override' => $data['alt_text_override'] ?? null,
                        ],
                    ],
                ];
            }
        }

        $state['content_blocks'] = [
            'type' => 'doc',
            'content' => $nodes !== [] ? $nodes : [[
                'type' => 'paragraph',
                'content' => [],
            ]],
        ];

        return $state;
    }
}
