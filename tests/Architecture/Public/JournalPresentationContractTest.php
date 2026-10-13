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
        ->toContain('@container (max-width: 23rem)')
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
        ->toContain('blog-entry--illustrated')
        ->toContain('blog-entry--reverse')
        ->toContain('blog-entry__mount')
        ->toContain('blog-entry__number')
        ->toContain('includeInheritedCopyright: false')
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


it('gives the Blog a sculpted folio and preserves art, typography and copyright contracts', function (): void {
    $root = dirname(__DIR__, 3);
    $css = file_get_contents($root.'/resources/css/public-presentation.css');
    $blog = file_get_contents($root.'/resources/views/pages/blog/index.blade.php');
    $ornament = file_get_contents($root.'/public/ornaments/journal-engraving.svg');

    expect($css)
        ->toContain('#content:has(> .blog-page)')
        ->toContain('clip-path: polygon(')
        ->toContain('.blog-entry--reverse .blog-entry__mount')
        ->toContain('.blog-entry__mount::before {')
        ->toContain('.blog-entry__mount::after {')
        ->toContain('top: -2.05rem;')
        ->toContain('blog-page__colophon')
        ->not->toContain('blog-entry--with-cover')
        ->not->toContain('.blog-entry:last-child {');

    expect($blog)
        ->toContain('$illustratedIndex++')
        ->toContain('inheritsDefaultNotice($cover)')
        ->toContain('blog-page__colophon')
        ->toContain('blog-entry--text-only')
        ->toContain('includeInheritedCopyright: false');

    expect($ornament)
        ->toContain('viewBox="0 0 128 128"')
        ->toContain('<path')
        ->not->toContain('<image');
});
