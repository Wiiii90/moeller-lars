<?php

namespace App\Domain\Content;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Throwable;

final class SafeRichTextRenderer
{
    private readonly Environment $environment;

    public function __construct(
        private readonly SafeLinkPolicy $safeLinkPolicy,
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
    }

    public function assertValid(string $source): void
    {
        $this->validate($this->parse($source));
    }

    public function render(string $source): HtmlString
    {
        $document = $this->parse($source);
        $this->validate($document);

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

    private function validate(Document $document): void
    {
        $walker = $document->walker();
        while ($event = $walker->next()) {
            if (! $event->isEntering()) {
                continue;
            }

            $node = $event->getNode();
            if (! $node instanceof Document
                && ! $node instanceof Paragraph
                && ! $node instanceof ListBlock
                && ! $node instanceof ListItem
                && ! $node instanceof Text
                && ! $node instanceof Newline
                && ! $node instanceof Emphasis
                && ! $node instanceof Strong
                && ! $node instanceof Link) {
                throw UnsafeRichTextException::unsupportedSyntax();
            }

            if ($node instanceof Link && ! $this->safeLinkPolicy->isAllowed($node->getUrl())) {
                throw UnsafeRichTextException::unsafeLink();
            }
        }
    }
}
