<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class CanonicalMediaImageRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly PublicMedia $publicMedia,
        private readonly SafeLinkPolicy $safeLinkPolicy,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
    {
        if (! $node instanceof Image) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        $url = $node->getUrl();
        $renderedAlt = $childRenderer->renderNodes($node->children());
        $override = trim(html_entity_decode(strip_tags($renderedAlt), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $mediaAssetId = RichTextMediaReference::idFromUrl($url);

        if ($mediaAssetId === null) {
            if (! $this->isAllowedExternalImageUrl($url)) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }

            return new HtmlElement('img', [
                'class' => 'rich-text__media',
                'src' => $url,
                'alt' => $override,
                'loading' => 'lazy',
                'decoding' => 'async',
            ], '', true);
        }

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()
            ->whereKey($mediaAssetId)
            ->where('state', 'available')
            ->where('mime_type', 'like', 'image/%')
            ->first();
        if (! $asset instanceof MediaAsset) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        return new HtmlElement('img', [
            'class' => 'rich-text__media',
            'src' => $this->publicMedia->thumbnailUrlForAsset($asset),
            'alt' => $this->publicMedia->altTextForAsset($asset, $override === '' ? null : $override),
            'loading' => 'lazy',
            'decoding' => 'async',
        ], '', true);
    }

    private function isAllowedExternalImageUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && $this->safeLinkPolicy->isAllowed($url);
    }
}
