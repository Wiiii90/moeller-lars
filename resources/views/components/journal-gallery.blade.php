@props(['gallery', 'presentation', 'label', 'journalMedia', 'includeCopyrightNotice' => true])

@php
    $galleryGroups = $presentation === 'mosaic'
        ? app(\App\Domain\Content\JournalMosaicColumns::class)->arrange($gallery)
        : [$gallery->values()->all()];
@endphp
<div class="journal-entry-gallery journal-entry-gallery--{{ $presentation }} @if ($presentation === 'mosaic' && $gallery->count() === 1) journal-entry-gallery--single @endif" aria-label="{{ $label }}" @if ($presentation === 'slideshow') data-journal-slideshow @endif>
    @if ($presentation === 'slideshow' && $gallery->count() > 1)
        <nav class="journal-entry-gallery__controls" aria-label="{{ $label }} navigation">
            <button type="button" data-journal-slide-prev aria-label="Previous image"><x-public-icon name="arrow-left" /></button>
            <span data-journal-slide-status aria-live="polite" aria-atomic="true">1 / {{ $gallery->count() }}</span>
            <button type="button" data-journal-slide-next aria-label="Next image"><x-public-icon name="arrow-right" /></button>
        </nav>
    @endif
    @foreach ($galleryGroups as $column)
        @if ($presentation === 'mosaic')
            <div class="journal-entry-gallery__column">
        @endif
        @foreach ($column as $usage)
            <div class="journal-entry-gallery__item" @if ($presentation === 'slideshow') data-journal-slide @if (! $loop->first) hidden @endif @endif>{!! $journalMedia->render($usage, 'journal-entry-media journal-entry-media--gallery', includeInheritedCopyright: false, includeCopyrightNotice: $includeCopyrightNotice, showCaption: false) !!}</div>
        @endforeach
        @if ($presentation === 'mosaic')
            </div>
        @endif
    @endforeach
</div>
