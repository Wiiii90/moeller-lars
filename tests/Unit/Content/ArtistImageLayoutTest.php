<?php

use App\Domain\Content\ArtistImageLayout;

it('keeps the canonical image placement and caption in public markup', function (): void {
    $source = '<p><img data-id="12" data-artist-placement="float-left" data-artist-caption="Artist credit" alt="Artwork"></p>';
    $rendered = '<p><img src="/preview/12" alt="Artwork"></p>';

    $html = ArtistImageLayout::decorateHtml($rendered, $source);

    expect($html)->toContain('rich-text__image--float-left')
        ->toContain('Artist credit')
        ->toContain('<img src="/preview/12"');
});

it('does not carry image metadata across a mismatched public render', function (): void {
    ArtistImageLayout::decorateHtml('<p>Missing image</p>', '<p><img data-id="12"></p>');
})->throws(\App\Domain\Content\UnsafeRichTextException::class);
