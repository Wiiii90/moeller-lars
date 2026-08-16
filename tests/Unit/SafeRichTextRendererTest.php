<?php

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\UnsafeRichTextException;
use Illuminate\Support\HtmlString;

function safeRenderer(): SafeRichTextRenderer
{
    return new SafeRichTextRenderer(new SafeLinkPolicy);
}

it('accepts empty, escaped, formatted, and linked CommonMark', function (): void {
    expect(safeRenderer()->render('')->toHtml())->toBe('');
    expect(str_contains(safeRenderer()->render("plain < text & value\nnext")->toHtml(), 'plain &lt; text &amp; value'))->toBeTrue();
    expect(str_contains(safeRenderer()->render("plain < text & value\nnext")->toHtml(), '<br>'))->toBeTrue();
    expect(str_contains(safeRenderer()->render("one\n\ntwo")->toHtml(), '<p>one</p>'))->toBeTrue();
    expect(str_contains(safeRenderer()->render("one\n\ntwo")->toHtml(), '<p>two</p>'))->toBeTrue();
    expect(str_contains(safeRenderer()->render("two spaces  \nhard break")->toHtml(), '<br'))->toBeTrue();
    expect(str_contains(safeRenderer()->render('**strong** *emphasis* **_nested_**')->toHtml(), '<strong>strong</strong>'))->toBeTrue();
    expect(str_contains(safeRenderer()->render('**strong** *emphasis* **_nested_**')->toHtml(), '<em>emphasis</em>'))->toBeTrue();
    expect(str_contains(safeRenderer()->render('**strong** *emphasis* **_nested_**')->toHtml(), '<strong><em>nested</em></strong>'))->toBeTrue();
});

it('accepts unordered, ordered, and nested lists', function (): void {
    $source = "- one\n- two\n\n1. first\n2. second\n\n- parent\n  - child";
    $renderer = safeRenderer();

    $renderer->assertValid($source);
    $html = $renderer->render($source)->toHtml();
    expect(str_contains($html, '<ul>'))->toBeTrue();
    expect(str_contains($html, '<ol>'))->toBeTrue();
    expect(str_contains($html, '<li>one</li>'))->toBeTrue();
    expect(str_contains($html, '<li>child</li>'))->toBeTrue();
});

it('accepts safe HTTP, HTTPS, mailto, and nested links', function (): void {
    $renderer = safeRenderer();
    $source = '[HTTPS](https://example.com) [HTTP](http://example.com) [Mail](mailto:lars@example.com) **[nested](https://example.com)**';

    $renderer->assertValid($source);
    $html = $renderer->render($source)->toHtml();
    expect(str_contains($html, 'href="https://example.com"'))->toBeTrue();
    expect(str_contains($html, 'href="http://example.com"'))->toBeTrue();
    expect(str_contains($html, 'href="mailto:lars@example.com"'))->toBeTrue();
    expect(str_contains($html, '<strong><a'))->toBeTrue();
});

it('returns an HtmlString and validates through the public API', function (): void {
    $renderer = safeRenderer();

    expect($renderer->render('safe'))->toBeInstanceOf(HtmlString::class);
    $renderer->assertValid('safe');
});

it('rejects every unsupported AST syntax', function (string $source): void {
    $renderer = safeRenderer();

    expect(fn () => $renderer->assertValid($source))->toThrow(UnsafeRichTextException::class)
        ->and(fn () => $renderer->render($source))->toThrow(UnsafeRichTextException::class);
})->with([
    '# heading',
    '> blockquote',
    '`inline code`',
    "```\ncode\n```",
    '    indented code',
    '![image](https://example.com/image.jpg)',
    '---',
    '<b>raw HTML</b>',
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '<svg onload=alert(1)>x</svg>',
    '<iframe src="https://example.com"></iframe>',
    '<style>body{display:none}</style>',
    '<object data=x></object>',
    '<embed src=x>',
]);

it('rejects unsafe links in all representative forms', function (string $url): void {
    $renderer = safeRenderer();
    $source = '[unsafe]('.$url.')';

    expect(fn () => $renderer->assertValid($source))->toThrow(UnsafeRichTextException::class)
        ->and(fn () => $renderer->render($source))->toThrow(UnsafeRichTextException::class);
})->with([
    'javascript:alert(1)',
    'data:text/html,test',
    'file:///etc/passwd',
    'vbscript:msgbox(1)',
    '/relative',
    '//example.com',
    '#fragment',
    'ftp://example.com',
]);

it('rejects unsafe links nested in emphasis and strong text', function (): void {
    $renderer = safeRenderer();

    expect(fn () => $renderer->render('**[unsafe](javascript:alert(1))**'))->toThrow(UnsafeRichTextException::class)
        ->and(fn () => $renderer->render('*[unsafe](data:text/html,test)*'))->toThrow(UnsafeRichTextException::class);
});

it('never renders malformed Markdown as an unsafe anchor', function (): void {
    $html = safeRenderer()->render('[broken](javascript:alert(1)')->toHtml();

    expect(str_contains($html, '<a'))->toBeFalse();
    expect(str_contains($html, 'href='))->toBeFalse();
});

it('enforces the CommonMark nesting limit', function (): void {
    $source = str_repeat('>', 12).' deep nesting';

    expect(fn () => safeRenderer()->render($source))->toThrow(UnsafeRichTextException::class);
});

it('exposes the exact exception contract', function (): void {
    expect(UnsafeRichTextException::unsupportedSyntax()->getMessage())
        ->toBe('Unsupported rich-text syntax.')
        ->and(UnsafeRichTextException::unsafeLink()->getMessage())
        ->toBe('Unsafe rich-text link.');
});
