<?php

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\UnsafeRichTextException;

function contentRenderer(): SafeRichTextRenderer
{
    return new SafeRichTextRenderer(new SafeLinkPolicy);
}

it('escapes raw text while preserving the supported rich-text subset', function (): void {
    $html = contentRenderer()->render('plain < text & value **strong** [link](https://example.com)')->toHtml();

    expect(str_contains($html, 'plain &lt; text &amp; value'))->toBeTrue();
    expect(str_contains($html, '<strong>strong</strong>'))->toBeTrue();
    expect(str_contains($html, 'href="https://example.com"'))->toBeTrue();
});

it('rejects executable raw HTML', function (): void {
    expect(fn () => contentRenderer()->render('<script>alert(1)</script>'))
        ->toThrow(UnsafeRichTextException::class);
});

it('rejects unsafe link targets', function (): void {
    foreach (['javascript:alert(1)', 'data:text/html,test', '/relative'] as $url) {
        expect(fn () => contentRenderer()->render('[unsafe]('.$url.')'))
            ->toThrow(UnsafeRichTextException::class);
    }
});

it('never turns malformed unsafe Markdown into an anchor', function (): void {
    $html = contentRenderer()->render('[broken](javascript:alert(1)')->toHtml();

    expect(str_contains($html, '<a'))->toBeFalse();
    expect(str_contains($html, 'href='))->toBeFalse();
});
