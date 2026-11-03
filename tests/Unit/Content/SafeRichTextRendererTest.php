<?php

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\UnsafeRichTextException;
use App\Domain\Media\PublicMedia;

function contentRenderer(): SafeRichTextRenderer
{
    return new SafeRichTextRenderer(
        new SafeLinkPolicy,
        \Mockery::mock(PublicMedia::class),
    );
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

it('sanitizes supported visual editor content without interpreting it as Markdown', function (): void {
    $html = contentRenderer()->render('<p><strong>Selected text</strong></p>')->toHtml();
    expect($html)->toContain('<strong>Selected text</strong>');
});

it('rejects scripts and unsafe links inside visual editor paragraphs', function (): void {
    expect(fn () => contentRenderer()->render('<p>Text<script>alert(1)</script></p>'))
        ->toThrow(UnsafeRichTextException::class);
    expect(fn () => contentRenderer()->render('<p><a href="javascript:alert(1)">Link</a></p>'))
        ->toThrow(UnsafeRichTextException::class);
});


it('renders safe email links in visual rich text', function (): void {
    $html = contentRenderer()->render('<p><a href="mailto:artist@example.com">Email</a></p>')->toHtml();
    expect(html_entity_decode($html, ENT_QUOTES | ENT_HTML5))->toContain('href="mailto:artist@example.com"');
});

it('keeps visual tables, line spacing, safe links and text color data through public rendering', function (): void {
    $source = '<p data-artist-line-spacing="2.0">An <a href="https://example.org/notes">authored link</a>'
        .' with <span class="color" data-color="#136ab9">colored words</span></p>'
        .'<table><tbody><tr><th>Year</th><th>Work</th></tr>'
        .'<tr><td>2025</td><td>Painting</td></tr></tbody></table>';

    $html = contentRenderer()->render($source)->toHtml();

    expect($html)->toContain('<table')
        ->toContain('2025')
        ->toContain('Painting')
        ->toContain('href="https://example.org/notes"')
        ->toContain('data-artist-line-spacing="2.0"')
        ->toContain('data-color="#136ab9"');
});

it('rejects executable links in visual tables without replacing valid authored links', function (): void {
    $unsafe = '<table><tbody><tr><td><a href="javascript:alert(1)">unsafe</a></td></tr></tbody></table>';

    expect(fn () => contentRenderer()->render($unsafe))->toThrow(UnsafeRichTextException::class);
    $html = contentRenderer()->render('<p><a href="mailto:studio@example.org">Mail</a></p>')->toHtml();
    expect(html_entity_decode($html, ENT_QUOTES | ENT_HTML5))
        ->toContain('href="mailto:studio@example.org"');
});
