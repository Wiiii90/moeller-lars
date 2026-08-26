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

        return new HtmlElement('img', [
            'class' => 'rich-text__media',
            'src' => $this->publicMedia->thumbnailUrlForAsset($asset),
            'alt' => $this->publicMedia->altTextForAsset($asset),
            'loading' => 'lazy',
            'decoding' => 'async',
        ], '', true);
    }
}
