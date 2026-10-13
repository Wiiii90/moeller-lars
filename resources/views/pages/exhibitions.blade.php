@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    <x-public.page-heading :section="$section" />
    <section class="exhibitions-page" aria-label="Exhibitions">
        @foreach ($exhibitions as $exhibition)
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
                $mapDialogId = 'exhibition-map-'.$exhibition->getKey();
                $location = collect([$exhibition->venue, $exhibition->city, $exhibition->country])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->unique()
                    ->implode(' · ');
                $hasDetails = $vernissage !== null || $address !== null
                    || $cover instanceof \App\Models\JournalEntryMedia
                    || trim((string) ($exhibition->description ?? '')) !== ''
                    || $gallery->isNotEmpty() || $map !== null || $exhibition->external_url !== null;
            @endphp
            <article class="exhibition-entry" data-matomo-event-view="exhibition_view" data-matomo-event-category="Journal" data-matomo-event-name="{{ $exhibition->title }}">
                @if ($hasDetails)
                    <details class="exhibition-entry__disclosure">
                        <summary class="exhibition-entry__summary">
                @else
                    <div class="exhibition-entry__summary exhibition-entry__summary--static">
                @endif
                    <span class="exhibition-entry__schedule">
                        @if ($displayDate !== null)
                            <span class="exhibition-entry__date">{{ $displayDate }}</span>
                        @endif
                        @if ($timing === 'current' || $timing === 'upcoming')
                            <span class="exhibition-entry__timing">{{ $timing === 'current' ? 'Current' : 'Upcoming' }}</span>
                        @endif
                    </span>
                    <span class="exhibition-entry__heading">
                        <span class="exhibition-entry__title">{{ $exhibition->title }}</span>
                        @if ($location !== '')
                            <span class="exhibition-entry__location">{{ $location }}</span>
                        @endif
                    </span>
                    @if ($hasDetails)
                        <span class="exhibition-entry__toggle" aria-hidden="true">
                            <span class="exhibition-entry__open-label">Open</span>
                            <span class="exhibition-entry__close-label">Close</span>
                            <x-public-icon name="arrow-right" />
                        </span>
                    @endif
                @if ($hasDetails)
                        </summary>
                        <div class="exhibition-entry__body">
                            @if ($vernissage !== null || $address !== null)
                                <div class="exhibition-entry__facts">
                                    @if ($vernissage !== null)
                                        <div class="exhibition-entry__fact">
                                            <span class="exhibition-entry__fact-label">Vernissage</span>
                                            <span class="exhibition-entry__fact-value">{{ $vernissage }}</span>
                                        </div>
                                    @endif
                                    @if ($address !== null)
                                        <div class="exhibition-entry__fact">
                                            <span class="exhibition-entry__fact-label">Address</span>
                                            <span class="exhibition-entry__fact-value">{{ $address }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($cover instanceof \App\Models\JournalEntryMedia)
                                {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--cover exhibition-entry__cover') !!}
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
                                <div class="exhibition-entry__map-access">
                                    <details class="exhibition-entry__map-disclosure" data-exhibition-map-disclosure>
                                        <summary>
                                            <span class="exhibition-entry__show-map">Show map</span>
                                            <span class="exhibition-entry__hide-map">Hide map</span>
                                        </summary>
                                        <div class="exhibition-entry__map exhibition-entry__map--{{ $map['shape'] }}">
                                            <div class="exhibition-entry__map-frame">
                                                <iframe data-exhibition-map-src="{{ $map['embed_url'] }}" title="Map for {{ $exhibition->title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                                <button class="exhibition-entry__map-expand" type="button" data-exhibition-map-expand aria-controls="{{ $mapDialogId }}" aria-label="Enlarge map for {{ $exhibition->title }}">Enlarge</button>
                                            </div>
                                            <dialog id="{{ $mapDialogId }}" class="exhibition-map-dialog exhibition-map-dialog--{{ $map['shape'] }}">
                                                <div class="exhibition-map-dialog__content">
                                                    <button class="exhibition-map-dialog__close" type="button" data-exhibition-map-close>Close</button>
                                                    <div class="exhibition-map-dialog__frame">
                                                        <iframe data-exhibition-dialog-src="{{ $map['embed_url'] }}" title="Enlarged map for {{ $exhibition->title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                                    </div>
                                                </div>
                                            </dialog>
                                        </div>
                                    </details>
                                </div>
                            @endif
                            @if ($exhibition->external_url !== null || $map !== null)
                                <div class="exhibition-entry__links">
                                    @if ($exhibition->external_url !== null)
                                        <a href="{{ $exhibition->external_url }}" target="_blank" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_external_click" data-matomo-event-name="{{ $exhibition->title }}">More information</a>
                                    @endif
                                    @if ($map !== null)
                                        <a href="{{ $map['public_url'] }}" target="_blank" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_directions_click" data-matomo-event-name="{{ $exhibition->title }}">{{ in_array($timing, ['current', 'upcoming'], true) ? 'Directions' : 'Open in OpenStreetMap' }}</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </details>
                @else
                    </div>
                @endif
            </article>
        @endforeach
    </section>
@endsection
