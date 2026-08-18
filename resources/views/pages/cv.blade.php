@extends('layouts.app')

@section('title', 'CV · Lars Möller')
@section('meta_description', 'Biography and contact information for Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/cv'))

@section('content')
    @php
        $portraitEntry = $cvEntries->first(fn ($entry) => $entry->imageMediaAsset !== null);
    @endphp

    <div class="cv-page">
        <section class="cv-section" aria-label="CV">
            <div class="cv-legacy-layout">
                <div class="cv-legacy-copy">
                    <div class="cv-biography">
                        @foreach ($cvEntries as $entry)
                            <article class="cv-entry">
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

                    @if ($settings->public_email !== null || $settings->instagram_handle !== null)
                        <div class="cv-inline-contact" aria-label="Contact details">
                            @if ($settings->public_email !== null)
                                <div class="cv-inline-contact__row">
                                    <span class="cv-inline-label">E-Mail</span>
                                    <a href="mailto:{{ $settings->public_email }}">{{ $settings->public_email }}</a>
                                </div>
                            @endif
                            @if ($settings->instagram_handle !== null)
                                <div class="cv-inline-contact__row">
                                    <span class="cv-inline-label">Instagram</span>
                                    <a href="https://www.instagram.com/{{ $settings->instagram_handle }}/" rel="noopener noreferrer">{{ $settings->instagram_handle }}</a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($portraitEntry !== null)
                    <img
                        class="cv-portrait"
                        src="{{ $media->originalUrlForAsset($portraitEntry->imageMediaAsset) }}"
                        alt="{{ $media->altTextForAsset($portraitEntry->imageMediaAsset) }}"
                        loading="lazy"
                    >
                @endif
            </div>
        </section>

        <div class="cv-contact-area">
            <x-contact :settings="$settings" :show-status="false" />
        </div>

        @if ($settings->legal_disclaimer !== null)
            <section class="legal-disclaimer cv-bottom-disclaimer" aria-labelledby="legal-disclaimer-heading">
                <h2 id="legal-disclaimer-heading">Haftungsablehnung</h2>
                <p>{{ $settings->legal_disclaimer }}</p>
            </section>
        @endif
    </div>
@endsection
