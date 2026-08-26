@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' · Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    @php
        $isPreview = app(\App\Domain\Content\SitePreviewContext::class)->active();
    @endphp

    <div class="custom-page" aria-label="{{ $section->title }}">
        <h2 class="category-heading">{{ $section->title }}</h2>

        @if ($blocks === [])
            <p class="public-empty-state">This page does not have published content yet.</p>
        @else
            @foreach ($blocks as $blockIndex => $block)
                @php
                    $type = is_array($block) ? ($block['type'] ?? null) : null;
                @endphp

                @if ($type === 'image')
                    @php
                        $assetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                        $asset = $assetId !== null ? $assets->get($assetId) : null;
                        $variant = $asset !== null ? $media->thumbnailVariantForAsset($asset) : null;
                        $decorative = (bool) ($block['image_decorative'] ?? false);
                        $imageAlt = $asset !== null && ! $decorative ? $media->altTextForAsset($asset) : '';
                        $loading = $blockIndex === 0 ? 'eager' : 'lazy';
                    @endphp

                    @if ($asset !== null && $variant !== null)
                        <figure class="custom-page__component custom-page__media custom-page__image">
                            <img
                                src="{{ route('media.variant', $variant) }}"
                                alt="{{ $imageAlt }}"
                                loading="{{ $loading }}"
                                decoding="async"
                            >
                        </figure>
                    @endif
                @endif

                @if ($type === 'cv_list')
                    <section class="custom-page__component" aria-label="CV entries">
                        <div class="cv-biography">
                            @foreach ($cvEntries as $entry)
                                <article class="cv-entry">
                                    <div class="cv-entry__content">
                                        <div class="cv-entry__line">
                                            @if (filled($entry->year_text))
                                                <span class="cv-entry__date">{{ $entry->year_text }}</span>
                                            @endif
                                            <span>{{ $entry->title }}</span>
                                        </div>
                                        @if (filled($entry->organisation))<div>{{ $entry->organisation }}</div>@endif
                                        @if (filled($entry->location))<div>{{ $entry->location }}</div>@endif
                                        @if (filled($entry->body))
                                            <div class="rich-text">{!! $richText->render((string) $entry->body) !!}</div>
                                        @endif
                                        @if (filled($entry->external_url))
                                            <p><a href="{{ $entry->external_url }}" rel="noopener noreferrer">More information</a></p>
                                        @endif
                                        @if ($isPreview && $entry->state !== 'published')
                                            <small class="public-preview-state">{{ ucfirst((string) $entry->state) }}</small>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($type === 'text')
                    <section class="custom-page__component">
                        <div class="custom-page__copy">
                            @if (filled($block['title'] ?? null))
                                <h3>{{ $block['title'] }}</h3>
                            @endif
                            @if (filled($block['body'] ?? null))
                                <div class="rich-text">{!! $richText->render((string) $block['body']) !!}</div>
                            @endif
                        </div>
                    </section>
                @endif

                @if ($type === 'list')
                    <section class="custom-page__component">
                        <div class="custom-page__copy">
                            @if (filled($block['title'] ?? null))
                                <h3>{{ $block['title'] }}</h3>
                            @endif
                            <div class="custom-page__list">
                                @foreach (($block['items'] ?? []) as $item)
                                    @continue(! is_array($item) || (! $isPreview && (($item['visible'] ?? true) !== true)))
                                    <article class="custom-page__list-item">
                                        <div class="custom-page__list-line">
                                            @if (filled($item['date'] ?? null))
                                                <span class="custom-page__date">{{ $item['date'] }}</span>
                                            @endif
                                            <strong>{{ $item['title'] ?? '' }}</strong>
                                        </div>
                                        @if (filled($item['meta'] ?? null))<div>{{ $item['meta'] }}</div>@endif
                                        @if (filled($item['location'] ?? null))<div>{{ $item['location'] }}</div>@endif
                                        @if (filled($item['body'] ?? null))
                                            <div class="rich-text">{!! $richText->render((string) $item['body']) !!}</div>
                                        @endif
                                        @if (filled($item['url'] ?? null))
                                            <p><a href="{{ $item['url'] }}" rel="noopener noreferrer">More information</a></p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if ($type === 'divider')
                    @php
                        $dividerVariant = is_string($block['variant'] ?? null)
                            && in_array($block['variant'], \App\Models\CustomPageSetting::DIVIDER_VARIANTS, true)
                                ? $block['variant']
                                : 'thin';
                    @endphp
                    <div class="custom-page__divider custom-page__divider--{{ $dividerVariant }}" aria-hidden="true"></div>
                @endif

                @if ($type === 'contact')
                    @php
                        $formState = is_string($block['form_state'] ?? null) ? $block['form_state'] : 'enabled';
                        $statusText = is_string($block['status_text'] ?? null) ? $block['status_text'] : null;
                        $showEmail = (bool) ($block['show_email'] ?? true);
                        $showForm = (bool) ($block['show_form'] ?? true);
                        $socialPlatforms = is_array($block['social_platforms'] ?? null) ? $block['social_platforms'] : [];
                    @endphp
                    <div class="custom-page__component custom-page__contact">
                        <x-contact
                            :general-settings="$generalSettings"
                            :form-state="$formState"
                            :status-text="$statusText"
                            :show-status="true"
                            :show-email="$showEmail"
                            :show-form="$showForm"
                            :social-platforms="$socialPlatforms"
                        />
                    </div>
                @endif
            @endforeach
        @endif

        @if ($generalSettings->legal_disclaimer !== null)
            <section class="legal-disclaimer" aria-labelledby="legal-disclaimer-heading">
                <h2 id="legal-disclaimer-heading">Haftungsablehnung</h2>
                <p>{{ $generalSettings->legal_disclaimer }}</p>
            </section>
        @endif
    </div>
@endsection
