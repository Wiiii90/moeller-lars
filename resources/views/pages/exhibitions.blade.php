@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    <section class="exhibitions-page" aria-label="{{ $section->title }}">
        @forelse ($exhibitions as $exhibition)
            @php
                $timing = $exhibition->temporalState(now());
                $cover = $exhibition->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
                $gallery = $exhibition->mediaUsages->where('role', \App\Models\JournalEntryMedia::ROLE_GALLERY)->sortBy('position');
                $displayDate = $exhibition->displayDate();
                $vernissage = $exhibition->vernissageDisplay();
                $address = $exhibition->address();
                $showMap = $exhibition->shouldShowPublicMap(now());
                $mapUrl = $showMap ? $exhibition->publicMapUrl() : null;
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
                        {!! $journalContent->renderMedia($cover, 'journal-entry-media journal-entry-media--cover exhibition-entry__cover', $loop->first) !!}
                    @endif
                    @if (trim((string) ($exhibition->description ?? '')) !== '')
                        <div class="rich-text journal-entry-content exhibition-entry__description">{!! $journalContent->render($exhibition) !!}</div>
                    @endif
                    @if ($gallery->isNotEmpty())
                        <div class="journal-entry-gallery" aria-label="Exhibition images">@foreach ($gallery as $usage){!! $journalContent->renderMedia($usage, 'journal-entry-media journal-entry-media--gallery') !!}@endforeach</div>
                    @endif
                    @if ($showMap)
                        <div class="exhibition-entry__map">
                            <iframe src="{{ $exhibition->mapEmbedUrl() }}" title="Map for {{ $exhibition->title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    @endif
                    @if ($exhibition->external_url !== null || $mapUrl !== null)
                        <div class="exhibition-entry__links">
                            @if ($exhibition->external_url !== null)<a href="{{ $exhibition->external_url }}" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_external_click" data-matomo-event-name="{{ $exhibition->title }}">More information</a>@endif
                            @if ($mapUrl !== null)<a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" data-matomo-event-category="Journal" data-matomo-event-action="exhibition_map_click" data-matomo-event-name="{{ $exhibition->title }}">Open map</a>@endif
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <p class="public-empty-state">No exhibitions are currently published.</p>
        @endforelse
    </section>
@endsection
