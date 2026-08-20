@extends('layouts.app')

@section('title', 'Exhibitions · Lars Möller')
@section('meta_description', 'Exhibitions by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/exhibitions'))

@section('content')
    <section class="exhibitions-page" aria-label="Exhibitions">
        @forelse ($exhibitions as $exhibition)
            @php
                $description = trim((string) ($exhibition->description ?? ''));
                $openingText = null;

                if ($description !== '' && preg_match('/\bVernissage:\s*(.+)$/ui', $description, $openingMatch) === 1) {
                    $openingText = trim($openingMatch[1]);
                    $description = trim((string) preg_replace('/\s*\bVernissage:\s*.+$/ui', '', $description));
                }

                $descriptionPlain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description)));
                $hasLongDescription = mb_strlen($descriptionPlain) > 280;
                $descriptionPreview = $hasLongDescription
                    ? \Illuminate\Support\Str::limit($descriptionPlain, 220)
                    : null;
            @endphp

            <article
                class="exhibition-entry"
                data-matomo-event-view="exhibition_view"
                data-matomo-event-category="Exhibition"
                data-matomo-event-name="{{ $exhibition->title }}"
            >
                <div class="exhibition-entry__schedule" aria-label="Exhibition dates">
                    <div class="exhibition-entry__date">{{ $exhibition->date_text }}</div>

                    @if ($openingText !== null && $openingText !== '')
                        <div class="exhibition-entry__opening">
                            <span class="exhibition-entry__opening-label">Vernissage</span>
                            <span>{{ $openingText }}</span>
                        </div>
                    @endif
                </div>

                <div class="exhibition-entry__content">
                    @if ($exhibition->kind !== null)
                        <p class="exhibition-entry__eyebrow">{{ $exhibition->kind }}</p>
                    @endif

                    <h3>{{ $exhibition->title }}</h3>

                    @if ($exhibition->venue !== null || $exhibition->location_text !== null || $exhibition->city !== null || $exhibition->country !== null)
                        <div class="exhibition-entry__facts">
                            @if ($exhibition->venue !== null)
                                <div class="exhibition-entry__fact">
                                    <span class="exhibition-entry__fact-label">Venue</span>
                                    <span class="exhibition-entry__fact-value">{{ $exhibition->venue }}</span>
                                </div>
                            @endif

                            @if ($exhibition->location_text !== null)
                                <div class="exhibition-entry__fact">
                                    <span class="exhibition-entry__fact-label">Location</span>
                                    <span class="exhibition-entry__fact-value">{{ $exhibition->location_text }}</span>
                                </div>
                            @elseif ($exhibition->city !== null || $exhibition->country !== null)
                                <div class="exhibition-entry__fact">
                                    <span class="exhibition-entry__fact-label">Location</span>
                                    <span class="exhibition-entry__fact-value">{{ collect([$exhibition->city, $exhibition->country])->filter(fn ($value) => $value !== null)->implode(', ') }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($description !== '')
                        @if ($hasLongDescription)
                            <details class="exhibition-entry__description exhibition-entry__description-details">
                                <summary>
                                    <span class="exhibition-entry__description-preview">{{ $descriptionPreview }}</span>
                                </summary>
                                <div class="rich-text exhibition-entry__description-full">{!! $richText->render($description) !!}</div>
                            </details>
                        @else
                            <div class="rich-text exhibition-entry__description">{!! $richText->render($description) !!}</div>
                        @endif
                    @endif

                    @if ($exhibition->external_url !== null || $exhibition->directions_url !== null)
                        <div class="exhibition-entry__links">
                            @if ($exhibition->external_url !== null)
                                <a
                                    href="{{ $exhibition->external_url }}"
                                    rel="noopener noreferrer"
                                    data-matomo-event-category="Exhibition"
                                    data-matomo-event-action="exhibition_external_click"
                                    data-matomo-event-name="{{ $exhibition->title }}"
                                >More information</a>
                            @endif
                            @if ($exhibition->directions_url !== null)
                                <a
                                    href="{{ $exhibition->directions_url }}"
                                    rel="noopener noreferrer"
                                    data-matomo-event-category="Exhibition"
                                    data-matomo-event-action="exhibition_directions_click"
                                    data-matomo-event-name="{{ $exhibition->title }}"
                                >Directions</a>
                            @endif
                        </div>
                    @endif

                    @if ($exhibition->mediaUsages->isNotEmpty())
                        <div class="exhibition-media" aria-label="Exhibition images">
                            @foreach ($exhibition->mediaUsages as $usage)
                                @php
                                    $variant = $media->thumbnailVariantForAsset($usage->mediaAsset);
                                    $variantWidth = (int) ($variant->getAttribute('width') ?? 0);
                                    $variantHeight = (int) ($variant->getAttribute('height') ?? 0);
                                    $prioritizeMedia = $loop->parent->first && $loop->first;
                                @endphp
                                <figure class="exhibition-media__item" @if ($usage->role === 'hero') data-role="hero" @endif>
                                    <img
                                        src="{{ route('media.variant', $variant) }}"
                                        alt="{{ $media->altTextForAsset($usage->mediaAsset, $usage->alt_text_override) }}"
                                        @if ($variantWidth > 0 && $variantHeight > 0)
                                            width="{{ $variantWidth }}"
                                            height="{{ $variantHeight }}"
                                        @endif
                                        loading="{{ $prioritizeMedia ? 'eager' : 'lazy' }}"
                                        decoding="async"
                                        fetchpriority="{{ $prioritizeMedia ? 'high' : 'auto' }}"
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
        @empty
            <p class="public-empty-state">No exhibitions are currently published.</p>
        @endforelse
    </section>
@endsection
