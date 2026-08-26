@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    <section class="exhibitions-page" aria-labelledby="exhibitions-heading">
        <h2 id="exhibitions-heading" class="category-heading">{{ $section->title }}</h2>
        @forelse ($exhibitions as $exhibition)
            @php
                $timing = $exhibition->temporalState(now());
                $cover = $exhibition->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
                $gallery = $exhibition->shouldShowPublicGallery()
                    ? $exhibition->mediaUsages->where('role', \App\Models\JournalEntryMedia::ROLE_GALLERY)->sortBy('position')
                    : collect();
                $galleryPresentation = $exhibition->galleryPresentation();
                $displayDate = $exhibition->displayDate();
                $vernissage = $exhibition->vernissageDisplay();
                $address = $exhibition->address();
                $map = $exhibition->shouldShowPublicMap() ? $exhibition->mapPresentation() : null;
                $mapAspect = $map !== null ? app(\App\Domain\Content\ExhibitionMapPresentation::class)->aspectRatio($map['shape']) : null;
                $mapDialogId = 'exhibition-map-'.$exhibition->getKey();
            @endphp
            <article class="exhibition-entry" data-matomo-event-view="exhibition_view" data-matomo-event-category="Journal" data-matomo-event-name="{{ $exhibition->title }}">
                <div class="exhibition-entry__schedule" aria-label="Exhibition dates">
                    @if ($vernissage !== null)<div class="exhibition-entry__opening"><span class="exhibition-entry__opening-label">Vernissage</span><span>{{ $vernissage }}</span></div>@endif
                    @if ($displayDate !== null)<div class="exhibition-entry__date">{{ $displayDate }}</div>@endif
                </div>
                <div class="exhibition-entry__content">
                    @if ($timing === 'current')<p class="exhibition-entry__timing is-current">Current</p>@endif
                    <h3>{{ $exhibition->title }}</h3>
                    @if ($exhibition->venue !== null || $address !== null)
                        <div class="exhibition-entry__facts">
                            @if ($exhibition->venue !== null)<div class="exhibition-entry__fact"><span class="exhibition-entry__fact-label">Venue</span><span class="exhibition-entry__fact-value">{{ $exhibition->venue }}</span></div>@endif
                            @if ($address !== null)<div class="exhibition-entry__fact"><span class="exhibition-entry__fact-label">Address</span><span class="exhibition-entry__fact-value">{{ $address }}</span></div>@endif
                        </div>
                    @endif
                    @if ($cover instanceof \App\Models\JournalEntryMedia)
                        {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--cover exhibition-entry__cover', $loop->first) !!}
                    @endif
                    @if (trim((string) ($exhibition->description ?? '')) !== '')
                        <div class="rich-text journal-entry-content exhibition-entry__description">{!! $richText->render((string) $exhibition->description) !!}</div>
                    @endif
                    @if ($gallery->isNotEmpty())
                        <div class="journal-entry-gallery journal-entry-gallery--{{ $galleryPresentation }}" aria-label="Exhibition images" @if ($galleryPresentation === 'slideshow') data-journal-slideshow @endif>
                            @foreach ($gallery as $usage)
                                <div class="journal-entry-gallery__item" @if ($galleryPresentation === 'slideshow') data-journal-slide @if (! $loop->first) hidden @endif @endif>{!! $journalMedia->render($usage, 'journal-entry-media journal-entry-media--gallery') !!}</div>
                            @endforeach
                            @if ($galleryPresentation === 'slideshow' && $gallery->count() > 1)
                                <div class="journal-entry-gallery__controls" aria-label="Gallery navigation">
                                    <button type="button" data-journal-slide-prev aria-label="Previous image">Previous</button>
                                    <span data-journal-slide-status aria-live="polite">1 / {{ $gallery->count() }}</span>
                                    <button type="button" data-journal-slide-next aria-label="Next image">Next</button>
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($map !== null)
                        <div class="exhibition-entry__map exhibition-entry__map--{{ $map['shape'] }}">
                            <div class="exhibition-entry__map-frame" style="aspect-ratio: {{ $mapAspect }}">
                                <iframe src="{{ $map['embed_url'] }}" title="Map for {{ $exhibition->title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                <button class="exhibition-entry__map-expand" type="button" data-exhibition-map-expand aria-controls="{{ $mapDialogId }}" aria-label="Enlarge map for {{ $exhibition->title }}">Enlarge</button>
                            </div>
                            <dialog id="{{ $mapDialogId }}" class="exhibition-map-dialog exhibition-map-dialog--{{ $map['shape'] }}">
                                <div class="exhibition-map-dialog__content">
                                    <button class="exhibition-map-dialog__close" type="button" data-exhibition-map-close>Close</button>
                                    <div class="exhibition-map-dialog__frame" style="aspect-ratio: {{ $mapAspect }}">
                                        <iframe src="{{ $map['embed_url'] }}" title="Enlarged map for {{ $exhibition->title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                    </div>
                                </div>
                            </dialog>
                        </div>
                    @endif
                    @if ($exhibition->external_url !== null || $map !== null)
                        <div class="exhibition-entry__links">
                            @if ($exhibition->external_url !== null)<a href="{{ $exhibition->external_url }}" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_external_click" data-matomo-event-name="{{ $exhibition->title }}">More information</a>@endif
                            @if ($map !== null)<a href="{{ $map['public_url'] }}" target="_blank" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_directions_click" data-matomo-event-name="{{ $exhibition->title }}">Open map</a>@endif
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <p class="public-empty-state">No exhibitions are currently published.</p>
        @endforelse
    </section>
@endsection
