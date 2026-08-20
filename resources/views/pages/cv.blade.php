@extends('layouts.app')

@section('title', 'CV · Lars Möller')
@section('meta_description', 'Biography and contact information for Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/cv'))

@section('content')
    @php
        $portraitEntry = $cvEntries->first(fn ($entry) => $entry->imageMediaAsset !== null);
        $portraitVariant = $portraitEntry !== null
            ? $media->thumbnailVariantForAsset($portraitEntry->imageMediaAsset)
            : null;
        $portraitWidth = $portraitVariant !== null ? (int) ($portraitVariant->getAttribute('width') ?? 0) : 0;
        $portraitHeight = $portraitVariant !== null ? (int) ($portraitVariant->getAttribute('height') ?? 0) : 0;
        $showContactArea = ((bool) $settings->show_public_email && $settings->public_email !== null)
            || ((bool) $settings->show_instagram && $settings->instagram_handle !== null)
            || $settings->contact_state !== 'hidden';
        $profileTextBlocks = collect($settings->profile_text_blocks ?? [])
            ->filter(fn ($block) => is_array($block) && filled($block['title'] ?? null) && filled($block['body'] ?? null));
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
                </div>

                @if ($portraitEntry !== null && $portraitVariant !== null)
                    <img
                        class="cv-portrait"
                        src="{{ route('media.variant', $portraitVariant) }}"
                        alt="{{ $media->altTextForAsset($portraitEntry->imageMediaAsset) }}"
                        @if ($portraitWidth > 0 && $portraitHeight > 0)
                            width="{{ $portraitWidth }}"
                            height="{{ $portraitHeight }}"
                        @endif
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                @endif
            </div>
        </section>

        @if ($showContactArea)
            <div class="cv-contact-area">
                <x-contact :settings="$settings" :show-status="true" />
            </div>
        @endif

        @if ($profileTextBlocks->isNotEmpty())
            <section class="cv-text-blocks" aria-label="Additional information">
                @foreach ($profileTextBlocks as $block)
                    <article class="cv-text-block">
                        <h2>{{ $block['title'] }}</h2>
                        <p>{!! nl2br(e($block['body'])) !!}</p>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($settings->legal_disclaimer !== null)
            <section class="legal-disclaimer cv-bottom-disclaimer" aria-labelledby="legal-disclaimer-heading">
                <h2 id="legal-disclaimer-heading">Haftungsablehnung</h2>
                <p>{{ $settings->legal_disclaimer }}</p>
            </section>
        @endif
    </div>
@endsection
