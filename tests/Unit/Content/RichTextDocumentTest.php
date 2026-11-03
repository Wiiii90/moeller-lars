<?php

use App\Domain\Content\ArtistImageLayout;
use App\Domain\Content\RichTextDocument;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\UnsafeRichTextException;

it('keeps visual HTML and upgrades canonical legacy image references for the editor', function (): void {
    expect(RichTextDocument::forEditor('<p>Hello</p>'))->toBe('<p>Hello</p>');
    $html = RichTextDocument::forEditor("**Hello**\n\n![Alt](media:17)");
    expect($html)->toContain('<strong>Hello</strong>')
        ->toContain('data-id="17"')
        ->and(RichTextMediaReference::ids($html))->toBe([17]);
});

it('removes only the targeted canonical image from visual rich text', function (): void {
    $body = '<p>Intro</p><p><img data-id="3" alt="Old"></p><p><img data-id="4" alt="Retained"></p>';
    $clean = RichTextMediaReference::remove($body, 3);
    expect($clean)->not->toContain('data-id="3"')
        ->and($clean)->toContain('data-id="4"')
        ->and($clean)->toContain('Intro');
});

it('recognizes media references in both text generations', function (): void {
    expect(RichTextMediaReference::ids('![Old](media:3) <img data-id="4">'))->toBe([3, 4]);
});

it('renders one escaped caption below each image for all placements without duplicating images', function (string $placement): void {
    $source = '<p><img data-id="5" data-artist-placement="'.$placement.'" data-artist-caption="A &amp; B &lt;caption&gt;" width="320" height="240"></p>'
        .'<p><img data-id="5" data-artist-placement="inline" alt="Same original, no caption"></p>';
    $rendered = '<p><img src="/synthetic/5.webp" width="320" height="240" alt="First"></p>'
        .'<p><img src="/synthetic/5.webp" alt="Second"></p>';

    $html = ArtistImageLayout::decorateHtml($rendered, $source);

    expect(substr_count($html, '<img '))->toBe(2)
        ->and(substr_count($html, 'rich-text__image-caption'))->toBe(1)
        ->and($html)->toContain('rich-text__image--'.$placement)
        ->toContain('A &amp; B &lt;caption&gt;')
        ->not->toContain('<caption>');
    expect($html)->toMatch('/<img[^>]+><span class="rich-text__image-caption">A &amp; B &lt;caption&gt;<\/span><\/span>/');
})->with(['inline', 'left', 'center', 'right', 'float-left', 'float-right']);

it('removes the image caption without touching neighboring images and rejects mismatched output', function (): void {
    $first = '<p><img data-id="4" data-artist-caption=""></p><p><img data-id="4" data-artist-caption="Retained"></p>';
    $out = '<p><img src="/synthetic/4.webp"></p><p><img src="/synthetic/4.webp"></p>';

    $html = ArtistImageLayout::decorateHtml($out, $first);
    expect(substr_count($html, 'rich-text__image-caption'))->toBe(1)
        ->and($html)->toContain('Retained');
    expect(fn () => ArtistImageLayout::decorateHtml(
        '<p><img src="/synthetic/4.webp"></p>', $first,
    ))->toThrow(UnsafeRichTextException::class);
});
