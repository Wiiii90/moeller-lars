<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;

final class PublicImageViewer
{
    public const TOGGLE_LABEL = 'Enlarge images on click';

    public function __construct(
        private readonly PublicMedia $media,
        private readonly SafeRichTextRenderer $richText,
    ) {}

    public function renderRichText(string $source, string $keyPrefix): HtmlString
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
        $rendered = preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($ids, $assets, &$index, $keyPrefix): string {
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

            return ($prefix ?? $tag).$this->attributes($keyPrefix.'-'.$index, $imageUrl, $alt).'>';
        }, $html);

        return new HtmlString($rendered ?? $html);
    }

    public function attributes(string $key, string $source, string $alt): string
    {
        return ' data-artwork-viewer-item data-artwork-viewer-trigger data-viewer-scope="content"'
            .' data-viewer-key="'.e($key).'"'
            .' data-viewer-kind="image"'
            .' data-viewer-src="'.e($source).'"'
            .' data-viewer-alt="'.e($alt).'"'
            .' data-viewer-title="'.e($alt !== '' ? $alt : 'Image').'"'
            .' role="button" tabindex="0" aria-label="'.e('Enlarge image'.($alt !== '' ? ': '.$alt : '')).'"';
    }

}
