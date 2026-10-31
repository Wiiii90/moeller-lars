<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Http\Middleware\SecurityHeaders;
use App\Filament\Support\RichTextEditorPlugin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
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
            new CanonicalMediaImageRenderer($this->publicMedia, $this->safeLinkPolicy),
            100,
        );
    }

    public function assertValid(
        string $source,
        bool $allowEmbeddedMedia = false,
        bool $requirePublicMedia = false,
    ): void {
        if (RichTextDocument::isVisual($source)) {
            $this->validateVisual($source, $allowEmbeddedMedia, $requirePublicMedia);

            return;
        }
        $this->validate($this->parse($source), $source, $allowEmbeddedMedia, $requirePublicMedia);
    }

    public function render(string $source): HtmlString
    {
        if (RichTextDocument::isVisual($source)) {
            $imageMetadata = $this->validateVisual($source, allowEmbeddedMedia: true, requirePublicMedia: true);
            $imageIndex = 0;

            $html = RichContentRenderer::make($source)
                ->plugins([RichTextEditorPlugin::make()])
                ->linkProtocols(['http', 'https', 'mailto'])
                ->processNodesUsing(static function (object &$node) use ($imageMetadata, &$imageIndex): void {
                    if (($node->type ?? null) !== 'image') {
                        return;
                    }

                    $entry = $imageMetadata[$imageIndex++] ?? null;
                    if (! is_array($entry)
                        || (int) ($node->attrs->id ?? 0) !== $entry['media_asset_id']) {
                        throw UnsafeRichTextException::unsupportedSyntax();
                    }

                    // The public ALT is resolved from canonical Storage metadata
                    // and any validated override, never from an old editor URL.
                    $node->attrs->alt = $entry['alt'];
                })
                ->toHtml();

            return $this->withPublicTextColors(ArtistImageLayout::decorateHtml($html, $source));
        }

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

    /**
     * Filament renders color marks with inline CSS variables, but Public CSP
     * forbids style attributes. Only verified hexadecimal values from the
     * already-sanitized editor HTML may become nonce-authorized color rules.
     */
    private function withPublicTextColors(string $html): HtmlString
    {
        $nonce = request()->attributes->get(SecurityHeaders::STYLE_NONCE_ATTRIBUTE);
        if (! is_string($nonce) || $nonce === '') {
            return new HtmlString($html);
        }

        $colors = [];
        $html = preg_replace_callback(
            '/<span\\b(?=[^>]*\\bclass="[^"]*\\bcolor\\b[^"]*")(?=[^>]*\\bdata-color="(#[0-9a-f]{6})")[^>]*>/i',
            static function (array $matches) use (&$colors): string {
                $color = strtolower($matches[1]);
                $colors[$color] = true;

                // Remove the unusable inline variable at the canonical output boundary.
                return preg_replace('/\\sstyle="[^"]*"/i', '', $matches[0]) ?? $matches[0];
            },
            $html,
        ) ?? $html;

        if ($colors === []) {
            return new HtmlString($html);
        }

        $rules = '';
        foreach (array_keys($colors) as $color) {
            $rules .= '.rich-text .color[data-color="'.$color.'" i]{color:'.$color.';}';
        }

        // Keep style elements in the existing public layout head stack, not
        // inside Rich Text: inserting a style as the first or last child would
        // change :first-child / :last-child paragraph spacing.
        view()->startPush('public-component-styles', '<style nonce="'.e($nonce).'">'.$rules.'</style>');

        return new HtmlString($html);
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

                $url = $node->getUrl();
                $mediaAssetId = RichTextMediaReference::idFromUrl($url);
                if ($mediaAssetId !== null) {
                    $parsedImageIds[] = $mediaAssetId;

                    continue;
                }

                if (! $this->isAllowedExternalImageUrl($url)) {
                    throw UnsafeRichTextException::unsupportedSyntax();
                }
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

    /** @return list<array{media_asset_id: int, alt: string}> */
    private function validateVisual(string $source, bool $allowEmbeddedMedia, bool $requirePublicMedia): array
    {
        // Reject active and remote-embed payloads before TipTap's normal HTML
        // normalization. All public rendering additionally goes through its
        // server-side sanitized renderer, never raw stored HTML.
        if (preg_match('/<\\s*(?:script|iframe|object|embed|svg|form|style|meta|link)\\b/i', $source)) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }
        if (preg_match('/\\son[a-z]+\\s*=/i', $source)) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        preg_match_all('/<img\\b[^>]*>/i', $source, $visualImageTags);
        $images = RichTextMediaReference::ids($source);
        if (! $allowEmbeddedMedia && ($visualImageTags[0] ?? []) !== []) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        // Every visual-editor image must identify a canonical Storage asset.
        // A bare external <img> would bypass the asset's publication,
        // availability and ALT checks.
        foreach ($visualImageTags[0] ?? [] as $imageTag) {
            if (preg_match('/\\bdata-id\\s*=\\s*(["\\\'])([1-9]\\d*)\\1/i', $imageTag) !== 1) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }
        }
        $resolvedImages = [];
        if ($images !== []) {
            $assets = MediaAsset::query()
                ->whereIn('id', $images)
                ->where('state', 'available')
                ->where('mime_type', 'like', 'image/%')
                ->with('variants')
                ->get()
                ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

            if ($assets->count() !== count($images)) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }

            if ($requirePublicMedia) {
                // Resolve in document order: repeated references to one asset
                // may have different editorial ALT overrides.
                foreach ($visualImageTags[0] ?? [] as $imageTag) {
                    if (preg_match('/\\bdata-id\\s*=\\s*(["\\\'])([1-9]\\d*)\\1/i', $imageTag, $idMatch) !== 1) {
                        throw UnsafeRichTextException::unsupportedSyntax();
                    }
                    $asset = $assets->get((int) $idMatch[2]);
                    if (! $asset instanceof MediaAsset) {
                        throw UnsafeRichTextException::unsupportedSyntax();
                    }

                    $override = null;
                    if (preg_match('/\\balt\\s*=\\s*(["\\\'])(.*?)\\1/i', $imageTag, $altMatch) === 1) {
                        $value = trim(html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $override = $value !== '' ? $value : null;
                        if ($override !== null && mb_strlen($override) > 500) {
                            throw UnsafeRichTextException::unsupportedSyntax();
                        }
                    }

                    try {
                        $alt = $this->publicMedia->altTextForAsset($asset, $override);
                        $this->publicMedia->thumbnailVariantForAsset($asset);
                    } catch (LogicException) {
                        throw UnsafeRichTextException::unsupportedSyntax();
                    }

                    $resolvedImages[] = [
                        'media_asset_id' => (int) $asset->getKey(),
                        'alt' => $alt,
                    ];
                }
            }
        }

        // Validate any external links and images in addition to canonical IDs.
        if (preg_match_all('/\\b(?:href|src)\\s*=\\s*([\"\\\'])(.*?)\\1/i', $source, $attributes, PREG_SET_ORDER)) {
            foreach ($attributes as $attribute) {
                $url = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($url !== '' && ! $this->safeLinkPolicy->isAllowed($url)) {
                    throw UnsafeRichTextException::unsafeLink();
                }
            }
        }

        return $resolvedImages;
    }

    private function isAllowedExternalImageUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && $this->safeLinkPolicy->isAllowed($url);
    }
}
