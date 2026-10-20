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
                $vernissage = $exhibition->shouldShowPublicVernissage() ? $exhibition->vernissageDisplay() : null;
                $address = $exhibition->streetAddress() !== null ? $exhibition->address() : null;
                $map = $exhibition->shouldShowPublicMap() ? $exhibition->mapPresentation() : null;
                $mapDialogId = 'exhibition-map-'.$exhibition->getKey();
                $attribution = $exhibition->summaryAttribution();
                $physicalVenue = filled($exhibition->organizer) && filled($exhibition->venue)
                    ? trim((string) $exhibition->venue)
                    : null;
                $location = $exhibition->mapLocationLabel();
                $hasDescription = trim((string) ($exhibition->description ?? '')) !== ''
                    && trim((string) $exhibition->description) !== trim((string) $exhibition->location_text);
                $hasCover = $cover instanceof \App\Models\JournalEntryMedia;
                $hasFacts = $vernissage !== null || $address !== null || $physicalVenue !== null;
                $hasActions = $map !== null || $exhibition->external_url !== null;
                $hasDetails = $hasFacts || $hasDescription || $hasCover
                    || $gallery->isNotEmpty() || $hasActions;
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
                        @if (in_array($timing, ['current', 'upcoming'], true))
                            <span class="exhibition-entry__timing">{{ $timing === 'current' ? 'Current' : 'Upcoming' }}</span>
                        @endif
                    </span>
                    <span class="exhibition-entry__heading">
                        <span class="exhibition-entry__title">{{ $exhibition->title }}</span>
                        @if ($attribution !== null)
                            <span class="exhibition-entry__byline">
                                <span class="exhibition-entry__location">{{ $attribution }}</span>
                            </span>
                        @endif
                    </span>
                @if ($hasDetails)
                        </summary>
                        <div class="exhibition-entry__body">
                            {{-- Cover, practical information and long-form prose each have a
                                 stable editorial place. No empty partner column is rendered. --}}
                            @if ($hasCover || $hasFacts || $hasActions || $hasDescription)
                                <div class="exhibition-entry__feature {{ $hasCover ? 'exhibition-entry__feature--with-cover' : 'exhibition-entry__feature--text' }}">
                                    @if ($hasCover)
                                        {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--cover exhibition-entry__cover', includeInheritedCopyright: false, showCaption: true) !!}
                                    @endif
                                    @if ($hasFacts || $hasActions)
                                        <div class="exhibition-entry__info">
                                            @if ($hasFacts)
                                                <dl class="exhibition-entry__facts">
                                                    @if ($physicalVenue !== null)
                                                        <div class="exhibition-entry__fact">
                                                            <dt>Venue</dt>
                                                            <dd>{{ $physicalVenue }}</dd>
                                                        </div>
                                                    @endif
                                                    @if ($vernissage !== null)
                                                        <div class="exhibition-entry__fact">
                                                            <dt>Vernissage</dt>
                                                            <dd>{{ $vernissage }}</dd>
                                                        </div>
                                                    @endif
                                                    @if ($address !== null)
                                                        <div class="exhibition-entry__fact">
                                                            <dt>Address</dt>
                                                            <dd>{{ $address }}</dd>
                                                        </div>
                                                    @endif
                                                </dl>
                                            @endif
                                            @if ($hasActions)
                                                <div class="exhibition-entry__links">
                                                    @if ($map !== null)
                                                        <button class="exhibition-entry__map-trigger" type="button"
                                                            data-exhibition-map-open
                                                            aria-haspopup="dialog"
                                                            aria-controls="{{ $mapDialogId }}">
                                                            Show map
                                                        </button>
                                                    @endif
                                                    @if ($exhibition->external_url !== null)
                                                        <a href="{{ $exhibition->external_url }}" target="_blank" rel="noopener noreferrer"
                                                            data-matomo-event-category="Journal"
                                                            data-matomo-event-action="exhibition_external_click"
                                                            data-matomo-event-name="{{ $exhibition->title }}">More information <x-public-icon name="external-link" /></a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($hasDescription)
                                        <div class="rich-text journal-entry-content exhibition-entry__description">{!! $richText->render((string) $exhibition->description) !!}</div>
                                    @endif
                                </div>
                            @endif

                            @if ($gallery->isNotEmpty())
                                <x-journal-gallery :gallery="$gallery" :presentation="$galleryPresentation" label="Exhibition media" :journal-media="$journalMedia" :show-captions="true" />
                            @endif

                            @if ($map !== null)
                                <dialog id="{{ $mapDialogId }}"
                                    class="exhibition-map-dialog"
                                    aria-labelledby="exhibition-map-heading-{{ $exhibition->getKey() }}">
                                    <div class="exhibition-map-dialog__content">
                                        <header class="exhibition-map-dialog__header">
                                            <div class="exhibition-map-dialog__identity">
                                                <span class="exhibition-map-dialog__eyebrow">Exhibition location</span>
                                                <h4 id="exhibition-map-heading-{{ $exhibition->getKey() }}">{{ $exhibition->title }}</h4>
                                                @if ($location !== null)
                                                    <p>{{ $location }}</p>
                                                @endif
                                            </div>
                                            <button class="exhibition-map-dialog__close" type="button" data-exhibition-map-close>
                                                Close <x-public-icon name="close" />
                                            </button>
                                        </header>
                                        <div class="exhibition-map-dialog__canvas"
                                            data-exhibition-map-canvas
                                            data-latitude="{{ $map['latitude'] }}"
                                            data-longitude="{{ $map['longitude'] }}"
                                            data-leaflet-script="{{ asset('vendor/leaflet/leaflet.js') }}"
                                            data-leaflet-style="{{ asset('vendor/leaflet/leaflet.css') }}"
                                            role="region"
                                            aria-label="Map for {{ $exhibition->title }}">
                                            <p class="exhibition-map-dialog__status" data-exhibition-map-status role="status">Loading map…</p>
                                        </div>
                                        <footer class="exhibition-map-dialog__footer">
                                            <a href="{{ $map['public_url'] }}" target="_blank" rel="noopener noreferrer"
                                                data-matomo-event-category="Journal"
                                                data-matomo-event-action="exhibition_directions_click"
                                                data-matomo-event-name="{{ $exhibition->title }}">
                                                {{ in_array($timing, ['current', 'upcoming'], true) ? 'Directions in OpenStreetMap' : 'Open in OpenStreetMap' }}
                                                <x-public-icon name="external-link" />
                                            </a>
                                        </footer>
                                    </div>
                                </dialog>
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
