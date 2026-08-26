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
    public function __construct(private readonly PublicMedia $publicMedia) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
    {
        if (! $node instanceof Image) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        $mediaAssetId = RichTextMediaReference::idFromUrl($node->getUrl());
        if ($mediaAssetId === null) {
            throw UnsafeRichTextException::unsupportedSyntax();
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

        $renderedAlt = $childRenderer->renderNodes($node->children());
        $override = trim(html_entity_decode(strip_tags($renderedAlt), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return new HtmlElement('img', [
            'class' => 'rich-text__media',
            'src' => $this->publicMedia->thumbnailUrlForAsset($asset),
            'alt' => $this->publicMedia->altTextForAsset($asset, $override === '' ? null : $override),
            'loading' => 'lazy',
            'decoding' => 'async',
        ], '', true);
    }
}
