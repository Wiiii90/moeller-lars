<?php

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\UnsafeRichTextException;
use Illuminate\Support\HtmlString;

it('returns safe formatted HTML for supported rich text', function (): void {
    $html = (new SafeRichTextRenderer)->render("A **strong** and *emphasis*.\nNext\n\n- one\n- two\n\n[Visit](https://example.test)");

    expect($html)->toBeInstanceOf(HtmlString::class)
        ->and($html->toHtml())->toBe("<p>A <strong>strong</strong> and <em>emphasis</em>.<br>\nNext</p>\n<ul>\n<li>one</li>\n<li>two</li>\n</ul>\n<p><a href=\"https://example.test\">Visit</a></p>");
});

it('escapes plain text and preserves line breaks', function (): void {
    expect((new SafeRichTextRenderer)->render("plain < text & value\nnext")->toHtml())
        ->toBe("<p>plain &lt; text &amp; value<br>\nnext</p>");
});

it('rejects raw HTML and executable link URLs', function (string $input): void {
    expect(fn () => (new SafeRichTextRenderer)->render($input))
        ->toThrow(UnsafeRichTextException::class);
})->with([
    '<script>alert(1)</script>',
    'Click [here](javascript:alert(1))',
    '[bad](data:text/html,evil)',
]);

it('rejects unsupported syntax and malformed links', function (string $input): void {
    expect(fn () => (new SafeRichTextRenderer)->render($input))
        ->toThrow(UnsafeRichTextException::class);
})->with([
    '# heading',
    '~~strikethrough~~',
    '`code`',
    '![image](https://example.test/image.jpg)',
    '[missing](https://example.test',
]);

it('enforces the nesting limit', function (): void {
    $input = 'x';
    for ($i = 0; $i < 9; $i++) {
        $input = '['.$input.'](#x)';
    }

    expect(fn () => (new SafeRichTextRenderer)->render($input))
        ->toThrow(UnsafeRichTextException::class);
});
