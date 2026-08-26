<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalEntryContent;
use App\Domain\Content\JournalEntryMediaService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
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
        $entry->loadMissing('mediaUsages.mediaAsset.variants');

        foreach ($entry->mediaUsages as $usage) {
            $asset = $usage->getRelationValue('mediaAsset');
            if ($asset instanceof MediaAsset) {
                MediaAssetSelect::primeOptionLabel($asset);
            }
        }

        $inlineUsages = $entry->mediaUsages
            ->where('role', JournalEntryMedia::ROLE_INLINE)
            ->keyBy(fn (JournalEntryMedia $usage): string => strtolower((string) $usage->getAttribute('embed_key')));
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

            if ($type !== 'image') {
                continue;
            }

            $embedKey = strtolower((string) ($data['embed_key'] ?? ''));
            $usage = $inlineUsages->get($embedKey);
            $preview = $usage instanceof JournalEntryMedia ? $this->preview($usage) : [];
            $nodes[] = [
                'type' => 'customBlock',
                'attrs' => [
                    'id' => JournalEntryContent::INLINE_IMAGE_BLOCK_ID,
                    'config' => [
                        'embed_key' => $embedKey,
                        'media_asset_id' => $data['media_asset_id'] ?? null,
                        'alt_text_override' => $data['alt_text_override'] ?? null,
                        ...$preview,
                    ],
                ],
            ];
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

    /** @return array{preview_label?:string,preview_url?:string} */
    private function preview(JournalEntryMedia $usage): array
    {
        $asset = $usage->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            return [];
        }

        $variant = $asset->getRelationValue('variants')->first(
            fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
                && $candidate->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
                && $candidate->getAttribute('state') === 'available',
        );

        return array_filter([
            'preview_label' => (string) $asset->getAttribute('original_filename'),
            'preview_url' => $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }
}
