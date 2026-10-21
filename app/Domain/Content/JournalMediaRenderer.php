<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Domain\Media\MediaTypePolicy;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;
use LogicException;

final class JournalMediaRenderer
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly SafeRichTextRenderer $richText,
    ) {}

    public function inheritsDefaultNotice(JournalEntryMedia $usage): bool
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->mediaAsset;

        return $asset instanceof MediaAsset
            && ! in_array((string) $asset->getAttribute('copyright_notice_mode'), [
                MediaAsset::COPYRIGHT_OVERRIDE,
                MediaAsset::COPYRIGHT_NONE,
            ], true);
    }

    public function render(JournalEntryMedia $usage, string $class = 'journal-entry-media', bool $priority = false, bool $includeInheritedCopyright = true, bool $includeCopyrightNotice = true, bool $showCaption = true, bool $enableViewer = false): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Journal media usage requires a media asset.');
        }

        $isVideo = MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'));
        $posterVariant = $isVideo ? \App\Domain\Media\MediaPreviewVariant::videoPoster($asset) : null;
        $posterUrl = $posterVariant ? $this->media->variantUrl($posterVariant) : null;
        $variant = $isVideo ? null : $this->media->thumbnailVariantForAsset($asset);
        $alt = $this->media->altTextForAsset($asset);
        $width = (int) ($variant?->getAttribute('width') ?? 0);
        $height = (int) ($variant?->getAttribute('height') ?? 0);
        $credit = trim((string) ($asset->getAttribute('credit') ?? ''));
        // Blog presentation can hide all visual copyright notices while
        // preserving separately authored credits and the stored copyright data.
        // Other Journal surfaces retain their own inheritance/override policy.
        $copyright = '';
        if ($includeCopyrightNotice && ($includeInheritedCopyright || ! $this->inheritsDefaultNotice($usage))) {
            $copyright = trim((string) ($asset->effectiveCopyrightNotice() ?? ''));
        }
        // Public Journal presentations may omit visible figure captions.
        // Asset credit, copyright inheritance and accessible ALT remain owned
        // by their existing canonical media metadata, not this display flag.
        $caption = trim(implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== '')));

        $viewerAttributes = $enableViewer && ! $isVideo
            ? $this->viewerAttributes('journal-media-'.(int) $usage->getKey(), $this->media->originalUrlForAsset($asset), $alt)
            : '';

        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $element = $isVideo
            ? '<video src="'.e($this->media->originalUrlForAsset($asset)).'" aria-label="'.e($alt).'"'
                .($posterUrl !== null ? ' poster="'.e($posterUrl).'"' : '')
                .($posterVariant !== null ? ' width="'.(int) $posterVariant->getAttribute('width').'" height="'.(int) $posterVariant->getAttribute('height').'"' : '')
                .' controls muted loop playsinline preload="metadata" data-public-loop-video></video>'
            : '<img src="'.e($this->media->variantUrl($variant)).'" alt="'.e($alt).'"'.$dimensions
                .' loading="'.($priority ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($priority ? 'high' : 'auto').'"'.$viewerAttributes.'>';
        $figure = '<figure class="'.e($class).'">'
            .$element
            .($showCaption && $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')
            .'</figure>';

        return new HtmlString($figure);
    }

    public function renderRichText(string $source): HtmlString
    {
        $html = (string) $this->richText->render($source);
        $ids = [];

        if (RichTextDocument::isVisual($source)) {
            preg_match_all('/<img\b[^>]*>/i', $source, $sourceImages);
            foreach ($sourceImages[0] ?? [] as $tag) {
                if (preg_match('/\bdata-id\s*=\s*(["\'])(\d+)\1/i', $tag, $match) === 1) {
                    $ids[] = (int) $match[2];
                }
            }
        } else {
            preg_match_all('/!\[[^\r\n]*?\]\(([^)\r\n]+)\)/', $source, $sourceImages);
            foreach ($sourceImages[1] ?? [] as $url) {
                $ids[] = RichTextMediaReference::idFromUrl($url);
            }
        }

        $assets = MediaAsset::query()
            ->whereIn('id', array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null))))
            ->where('state', 'available')
            ->get()
            ->keyBy('id');

        $index = 0;
        $rendered = preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($ids, $assets, &$index): string {
            $index++;
            $tag = $match[0];
            $id = $ids[$index - 1] ?? null;
            $asset = $id !== null ? $assets->get($id) : null;
            $imageUrl = $asset instanceof MediaAsset ? $this->media->originalUrlForAsset($asset) : null;

            if ($imageUrl === null && preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $tag, $urlMatch) === 1) {
                $imageUrl = html_entity_decode($urlMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if ($imageUrl === null || $imageUrl === '') {
                return $tag;
            }

            $alt = '';
            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $altMatch) === 1) {
                $alt = html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $prefix = preg_replace('/\s*\/?>$/', '', $tag);

            return ($prefix ?? $tag).$this->viewerAttributes('journal-rich-'.$index, $imageUrl, $alt).'>';
        }, $html);

        return new HtmlString($rendered ?? $html);
    }

    private function viewerAttributes(string $key, string $source, string $alt): string
    {
        return ' data-artwork-viewer-item data-artwork-viewer-trigger data-viewer-scope="journal"'
            .' data-viewer-key="'.e($key).'"'
            .' data-viewer-kind="image"'
            .' data-viewer-src="'.e($source).'"'
            .' data-viewer-alt="'.e($alt).'"'
            .' data-viewer-title="'.e($alt !== '' ? $alt : 'Journal image').'"'
            .' role="button" tabindex="0" aria-label="'.e('Enlarge image: '.($alt !== '' ? $alt : 'Journal image')).'"';
    }

}
