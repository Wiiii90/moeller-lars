@extends('layouts.app')

@section('title', 'CV · Lars Möller')
@section('meta_description', 'Biography and contact information for Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/cv'))

@section('content')
    <div class="cv-page">
        <section class="cv-section" aria-labelledby="cv-heading">
            <h2 id="cv-heading" class="category-heading">cv</h2>

            <div class="cv-biography">
                @foreach ($cvEntries as $entry)
                    <article class="cv-entry">
                        @if ($entry->imageMediaAsset !== null)
                            <img
                                class="cv-entry__image"
                                src="{{ $media->originalUrlForAsset($entry->imageMediaAsset) }}"
                                alt="{{ $media->altTextForAsset($entry->imageMediaAsset) }}"
                                loading="lazy"
                            >
                        @endif

                        <div class="cv-entry__content">
                            <div class="cv-entry__line">
                                @if ($entry->year_text !== null)
                                    <span class="cv-entry__date">{{ $entry->year_text }}</span>
                                @endif
                                <span>{{ $entry->title }}</span>
                            </div>
                            @if ($entry->organisation !== null)
                                <div>{{ $entry->organisation }}</div>
                            @endif
                            @if ($entry->location !== null && ($entry->organisation !== null || $entry->body !== null))
                                <div>{{ $entry->location }}</div>
                            @endif
                            @if ($entry->body !== null)
                                <div class="rich-text">{!! $richText->render($entry->body) !!}</div>
                            @endif
                            @if ($entry->external_url !== null)
                                <p><a href="{{ $entry->external_url }}" rel="noopener noreferrer">More information</a></p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($settings->public_email !== null || $settings->instagram_handle !== null)
            <section class="public-contact-details" aria-labelledby="public-contact-heading">
                <h2 id="public-contact-heading">contact</h2>
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
