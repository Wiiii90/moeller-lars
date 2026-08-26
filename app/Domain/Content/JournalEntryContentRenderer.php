<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use Illuminate\Support\HtmlString;

final class JournalEntryContentRenderer
{
    public function __construct(
        private readonly JournalEntryContent $content,
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
    ) {}

    public function render(BlogPost|Exhibition $entry): HtmlString
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');
        $inline = $entry->mediaUsages
            ->where('role', JournalEntryMedia::ROLE_INLINE)
            ->keyBy(fn (JournalEntryMedia $usage): string => strtolower((string) $usage->getAttribute('embed_key')));

        $html = [];
        foreach ($this->content->blocks($this->source($entry)) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $markdown = trim((string) ($block['data']['markdown'] ?? ''));
                if ($markdown !== '') {
                    $html[] = $this->richText->render($markdown)->toHtml();
                }
                continue;
            }

            $key = strtolower((string) ($block['data']['embed_key'] ?? ''));
            $usage = $inline->get($key);
            if ($usage instanceof JournalEntryMedia) {
                $html[] = $this->renderMedia($usage, 'journal-entry-media journal-entry-media--inline')->toHtml();
            }
        }

        return new HtmlString(implode("\n", $html));
    }

    public function renderMedia(JournalEntryMedia $usage, string $class = 'journal-entry-media', bool $priority = false): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        $variant = $this->media->thumbnailVariantForAsset($asset);
        $alt = $this->media->altTextForAsset($asset, $usage->getAttribute('alt_text_override'));
        $width = (int) ($variant->getAttribute('width') ?? 0);
        $height = (int) ($variant->getAttribute('height') ?? 0);
        $credit = trim((string) ($asset->getAttribute('credit') ?? ''));
        $copyright = trim((string) ($asset->effectiveCopyrightNotice() ?? ''));
        $caption = trim(implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== '')));

        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $figure = '<figure class="'.e($class).'">'
            .'<img src="'.e(route('media.variant', $variant)).'" alt="'.e($alt).'"'.$dimensions
            .' loading="'.($priority ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($priority ? 'high' : 'auto').'">'
            .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')
            .'</figure>';

        return new HtmlString($figure);
    }

    private function source(BlogPost|Exhibition $entry): string
    {
        return (string) ($entry instanceof BlogPost
            ? ($entry->getAttribute('body') ?? '')
            : ($entry->getAttribute('description') ?? ''));
    }
}
