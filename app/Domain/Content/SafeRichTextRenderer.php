<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use LogicException;
use Throwable;

final class SafeRichTextRenderer
{
    private readonly Environment $environment;

    public function __construct(
        private readonly SafeLinkPolicy $safeLinkPolicy,
        private readonly PublicMedia $publicMedia,
    ) {
        $this->environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
            'max_delimiters_per_line' => 1000,
            'renderer' => [
                'soft_break' => "<br>\n",
            ],
        ]);
        $this->environment->addExtension(new CommonMarkCoreExtension);
        $this->environment->addRenderer(
            Image::class,
            new CanonicalMediaImageRenderer($this->publicMedia),
            100,
        );
    }

    public function assertValid(
        string $source,
        bool $allowEmbeddedMedia = false,
        bool $requirePublicMedia = false,
    ): void {
        $this->validate($this->parse($source), $source, $allowEmbeddedMedia, $requirePublicMedia);
    }

    public function render(string $source): HtmlString
    {
        $document = $this->parse($source);
        $this->validate(
            $document,
            $source,
            allowEmbeddedMedia: true,
            requirePublicMedia: true,
        );

        if ($source === '') {
            return new HtmlString('');
        }

        return new HtmlString((new HtmlRenderer($this->environment))->renderDocument($document));
    }

    private function parse(string $source): Document
    {
        try {
            return (new MarkdownParser($this->environment))->parse($source);
        } catch (Throwable) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }
    }

    private function validate(
        Document $document,
        string $source,
        bool $allowEmbeddedMedia,
        bool $requirePublicMedia,
    ): void {
        $parsedImageIds = [];
        $walker = $document->walker();

        while ($event = $walker->next()) {
            if (! $event->isEntering()) {
                continue;
            }

            $node = $event->getNode();
            if (! $node instanceof Document
                && ! $node instanceof Paragraph
                && ! $node instanceof Heading
                && ! $node instanceof BlockQuote
                && ! $node instanceof ListBlock
                && ! $node instanceof ListItem
                && ! $node instanceof Text
                && ! $node instanceof Newline
                && ! $node instanceof Emphasis
                && ! $node instanceof Strong
                && ! $node instanceof Link
                && ! $node instanceof Image) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }

            if ($node instanceof Link && ! $this->safeLinkPolicy->isAllowed($node->getUrl())) {
                throw UnsafeRichTextException::unsafeLink();
            }

            if ($node instanceof Image) {
                if (! $allowEmbeddedMedia) {
                    throw UnsafeRichTextException::unsupportedSyntax();
                }

                $mediaAssetId = RichTextMediaReference::idFromUrl($node->getUrl());
                if ($mediaAssetId === null) {
                    throw UnsafeRichTextException::unsupportedSyntax();
                }
                $parsedImageIds[] = $mediaAssetId;
            }
        }

        $references = RichTextMediaReference::references($source);
        $sourceImageIds = array_map(
            static fn (array $reference): int => $reference['media_asset_id'],
            $references,
        );
        if ($parsedImageIds !== $sourceImageIds) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        foreach ($references as $reference) {
            $override = $reference['alt_text_override'];
            if ($override !== null && (trim($override) === '' || mb_strlen($override) > 500)) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }
        }

        $uniqueIds = array_values(array_unique($sourceImageIds));
        if ($uniqueIds === []) {
            return;
        }

        $assets = MediaAsset::query()
            ->whereIn('id', $uniqueIds)
            ->where('state', 'available')
            ->where('mime_type', 'like', 'image/%')
            ->with('variants')
            ->get()
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        if ($assets->count() !== count($uniqueIds)) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        if (! $requirePublicMedia) {
            return;
        }

        foreach ($references as $reference) {
            /** @var MediaAsset|null $asset */
            $asset = $assets->get($reference['media_asset_id']);
            if (! $asset instanceof MediaAsset) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }

            try {
                $this->publicMedia->altTextForAsset($asset, $reference['alt_text_override']);
                $this->publicMedia->thumbnailVariantForAsset($asset);
            } catch (LogicException) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }
        }
    }
}
