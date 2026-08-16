@extends('layouts.app')

@section('title', 'CV & Exhibitions · Lars Möller')

@section('content')
    <div class="cv-page">
        @if ($settings->cv_enabled)
            <section class="cv-section" aria-labelledby="cv-heading">
                <h2 id="cv-heading" class="category-heading">CV</h2>

                @php($currentSection = null)
                @foreach ($cvEntries as $entry)
                    @if ($currentSection !== $entry->section)
                        @php($currentSection = $entry->section)
                        <h3 class="cv-section__heading">{{ $currentSection }}</h3>
                    @endif

                    <article class="cv-entry">
                        <div class="cv-entry__date">{{ $entry->year_text }}</div>
                        <div class="cv-entry__content">
                            <h4>{{ $entry->title }}</h4>

                            @if ($entry->organisation !== null)
                                <div>{{ $entry->organisation }}</div>
                            @endif
                            @if ($entry->location !== null)
                                <div>{{ $entry->location }}</div>
                            @endif
                            @if ($entry->body !== null)
                                <div class="rich-text">{!! $richText->render($entry->body) !!}</div>
                            @endif
                            @if ($entry->external_url !== null)
                                <p><a href="{{ $entry->external_url }}" rel="noopener noreferrer">More information</a></p>
                            @endif
                            @if ($entry->imageMediaAsset !== null)
                                <img
                                    class="cv-entry__image"
                                    src="{{ $media->originalUrlForAsset($entry->imageMediaAsset) }}"
                                    alt="{{ $media->altTextForAsset($entry->imageMediaAsset) }}"
                                    loading="lazy"
                                >
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($settings->exhibitions_enabled)
            <section class="exhibitions-section" aria-labelledby="exhibitions-heading">
                <h2 id="exhibitions-heading" class="category-heading">Exhibitions</h2>

                @foreach ($exhibitions as $exhibition)
                    <article class="exhibition-entry">
                        <div class="exhibition-entry__date">{{ $exhibition->date_text }}</div>
                        <div class="exhibition-entry__content">
                            <h3>{{ $exhibition->title }}</h3>

                            @if ($exhibition->venue !== null)
                                <div>{{ $exhibition->venue }}</div>
                            @endif
                            @if ($exhibition->city !== null || $exhibition->country !== null)
                                <div>{{ collect([$exhibition->city, $exhibition->country])->filter(fn ($value) => $value !== null)->implode(', ') }}</div>
                            @endif

                            @if ($exhibition->description !== null)
                                <div class="rich-text">{!! $richText->render($exhibition->description) !!}</div>
                            @endif

                            @if ($exhibition->external_url !== null || $exhibition->directions_url !== null)
                                <p class="exhibition-entry__links">
                                    @if ($exhibition->external_url !== null)
                                        <a href="{{ $exhibition->external_url }}" rel="noopener noreferrer">More information</a>
                                    @endif
                                    @if ($exhibition->directions_url !== null)
                                        <a href="{{ $exhibition->directions_url }}" rel="noopener noreferrer">Directions</a>
                                    @endif
                                </p>
                            @endif

                            @if ($exhibition->mediaUsages->isNotEmpty())
                                <div class="exhibition-media">
                                    @foreach ($exhibition->mediaUsages as $usage)
                                        <figure class="exhibition-media__item" @if ($usage->role === 'hero') data-role="hero" @endif>
                                            <img
                                                src="{{ $media->thumbnailUrlForAsset($usage->mediaAsset) }}"
                                                alt="{{ $media->altTextForAsset($usage->mediaAsset, $usage->alt_text_override) }}"
                                                loading="lazy"
                                            >
                                            @if ($usage->mediaAsset->credit !== null || $usage->mediaAsset->copyright_notice !== null)
                                                <figcaption>
                                                    @if ($usage->mediaAsset->credit !== null){{ $usage->mediaAsset->credit }}@endif
                                                    @if ($usage->mediaAsset->copyright_notice !== null){{ $usage->mediaAsset->copyright_notice }}@endif
                                                </figcaption>
                                            @endif
                                        </figure>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($settings->public_email !== null || $settings->instagram_handle !== null)
            <section class="public-contact-details" aria-labelledby="public-contact-heading">
                <h2 id="public-contact-heading" class="category-heading">Contact</h2>
                @if ($settings->public_email !== null)
                    <p><a href="mailto:{{ $settings->public_email }}">{{ $settings->public_email }}</a></p>
                @endif
                @if ($settings->instagram_handle !== null)
                    <p><a href="https://www.instagram.com/{{ $settings->instagram_handle }}/" rel="noopener noreferrer">Instagram: {{ $settings->instagram_handle }}</a></p>
                @endif
            </section>
        @endif

        @if ($settings->contact_state !== 'hidden')
            <x-contact :settings="$settings" />
        @endif

        @if ($settings->legal_disclaimer !== null)
            <section class="legal-disclaimer" aria-labelledby="legal-disclaimer-heading">
                <h2 id="legal-disclaimer-heading">Haftungsablehnung</h2>
                <p>{{ $settings->legal_disclaimer }}</p>
            </section>
        @endif
    </div>
@endsection
