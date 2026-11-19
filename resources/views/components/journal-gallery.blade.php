@props(['gallery', 'presentation', 'label', 'journalMedia', 'includeCopyrightNotice' => true, 'showCaptions' => false, 'enableViewer' => false])

@php
    // A Carousel needs a center and two different neighbors; with fewer
    // media, retain the saved choice but present a normal Slideshow instead.
    $effectivePresentation = $presentation === 'carousel' && $gallery->count() < 3
        ? 'slideshow'
        : $presentation;
    $galleryGroups = $effectivePresentation === 'mosaic'
        ? app(\App\Domain\Content\JournalMosaicColumns::class)->arrange($gallery)
        : [$gallery->values()->all()];
    $stepped = in_array($effectivePresentation, ['slideshow', 'carousel'], true);
@endphp
<div class="journal-entry-gallery journal-entry-gallery--{{ $effectivePresentation }} @if ($effectivePresentation === 'mosaic' && $gallery->count() === 1) journal-entry-gallery--single @endif" aria-label="{{ $label }}" @if ($effectivePresentation === 'slideshow') data-journal-slideshow @elseif ($effectivePresentation === 'carousel') data-journal-carousel @if ($gallery->count() >= 5) data-journal-carousel-five @endif @endif>
    @if ($stepped && $gallery->count() > 1)
        <nav class="journal-entry-gallery__controls" aria-label="{{ $label }} navigation">
            <button type="button" data-journal-slide-prev aria-label="Previous media"><x-public-icon name="arrow-left" /></button>
            <span data-journal-slide-status aria-live="polite" aria-atomic="true">1 / {{ $gallery->count() }}</span>
            <button type="button" data-journal-slide-next aria-label="Next media"><x-public-icon name="arrow-right" /></button>
        </nav>
    @endif
    @foreach ($galleryGroups as $column)
        @if ($effectivePresentation === 'mosaic')
            <div class="journal-entry-gallery__column">
        @endif
        @foreach ($column as $usage)
            <div class="journal-entry-gallery__item" @if ($stepped) data-journal-slide @if (! $loop->first) hidden @endif @endif>{!! $journalMedia->render($usage, 'journal-entry-media journal-entry-media--gallery', includeInheritedCopyright: false, includeCopyrightNotice: $includeCopyrightNotice, showCaption: $showCaptions, enableViewer: $enableViewer) !!}</div>
        @endforeach
        @if ($effectivePresentation === 'mosaic')
            </div>
        @endif
    @endforeach
</div>
