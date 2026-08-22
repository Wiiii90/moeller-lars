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

    expect($html)->toContain('plain &lt; text &amp; value')
        ->and($html)->toContain('<strong>strong</strong>')
        ->and($html)->toContain('href="https://example.com"');
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

    expect($html)->not->toContain('<a')
        ->and($html)->not->toContain('href=');
});
