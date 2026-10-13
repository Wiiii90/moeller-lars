<?php

it('keeps Journal typography fixed independently of configured content width', function (): void {
    $root = dirname(__DIR__, 3);
    $css = file_get_contents($root.'/resources/css/public-presentation.css');

    expect($css)
        ->toContain('--journal-date-font-size: .8rem;')
        ->toContain('--journal-exhibition-title-font-size: 1.34rem;')
        ->toContain('--journal-blog-index-title-font-size: 1.58rem;')
        ->toContain('--journal-blog-article-title-font-size: 1.88rem;')
        ->toContain('font-size: var(--journal-exhibition-title-font-size);')
        ->toContain('font-size: var(--journal-blog-index-title-font-size);')
        ->toContain('font-size: var(--journal-blog-article-title-font-size);')
        ->toContain('@container (max-width: 22rem)')
        ->not->toContain('font-size: clamp(1.6rem, 3.2vw, 2.2rem);');

    // Layout reflows at a content-width boundary, but does not override the
    // Exhibition title size there (or any Blog title size).
    expect(preg_match_all('/^\.exhibition-entry__title\s*\{/m', $css))->toBe(1);
});

it('keeps the Blog index compact and the Exhibition Slideshow navigation above the artwork', function (): void {
    $root = dirname(__DIR__, 3);
    $blog = file_get_contents($root.'/resources/views/pages/blog/index.blade.php');
    $exhibitions = file_get_contents($root.'/resources/views/pages/exhibitions.blade.php');
    $galleryCss = file_get_contents($root.'/resources/css/journal-presentation.css');

    expect($blog)
        ->toContain('blog-entry--with-cover')
        ->toContain('blog-entry__summary')
        ->toContain('blog-entry__cover')
        ->toContain('Read more');

    $gallery = substr($exhibitions, strpos($exhibitions, 'journal-entry-gallery--{{ $galleryPresentation }}'));
    expect(strpos($gallery, 'data-journal-slide-prev'))
        ->toBeLessThan(strpos($gallery, '@foreach ($galleryGroups as $column)'));

    expect($galleryCss)
        ->toContain('journal-entry-gallery--mosaic:not(.journal-entry-gallery--single)')
        ->toContain('journal-entry-gallery--slideshow .journal-entry-media img')
        ->toContain('max-height: min(23rem, 49dvh);')
        ->not->toContain('grid-row: span 2');
});
