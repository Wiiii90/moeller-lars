@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' · Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    @php
        $isPreview = app(\App\Domain\Content\SitePreviewContext::class)->active();
    @endphp

    <div class="custom-page" aria-label="{{ $section->title }}">
        @foreach ($blocks as $blockIndex => $block)
            @php
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $componentPublished = is_array($block) ? \App\Models\CustomPageSetting::componentPublished($block) : false;
            @endphp
            @continue(! $isPreview && ! $componentPublished)

            @if ($type === 'image')
                @php
                    $assetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $asset = $assetId !== null ? $assets->get($assetId) : null;
                    $variant = $asset !== null && $asset->getAttribute('state') === 'available' ? $media->thumbnailVariantForAsset($asset) : null;
                    $decorative = (bool) ($block['image_decorative'] ?? false);
                    $imageAlt = $asset !== null && ! $decorative ? $media->altTextForAsset($asset) : '';
                    $loading = $blockIndex === 0 ? 'eager' : 'lazy';
                @endphp

                @if ($asset !== null && $variant !== null)
                    <figure class="custom-page__component custom-page__media custom-page__image">
                        <img
                            src="{{ $media->variantUrl($variant) }}"
                            alt="{{ $imageAlt }}"
                            loading="{{ $loading }}"
                            decoding="async"
                        >
                    </figure>
                @endif
            @endif

            @if ($type === 'cv_list')
                @php
                    $cvAssetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $cvAsset = $cvAssetId !== null ? $assets->get($cvAssetId) : null;
                    $cvVariant = $cvAsset !== null && $cvAsset->getAttribute('state') === 'available'
                        ? $media->thumbnailVariantForAsset($cvAsset)
                        : null;
                @endphp
                <section class="custom-page__component" aria-label="CV entries">
                    @if ($cvVariant !== null && $cvAsset instanceof \App\Models\MediaAsset)
                        <div class="cv-legacy-layout">
                            <div class="cv-legacy-copy cv-biography">
                                @foreach ($cvEntries as $entry)
                                    <article class="cv-entry">
                                        <div class="cv-entry__line">
                                            @if (filled($entry->year_text))
                                                <span class="cv-entry__date">{{ $entry->year_text }}</span>
                                            @endif
                                            <span>{{ $entry->title }}</span>
                                        </div>
                                        @if (filled($entry->organisation))<div>{{ $entry->organisation }}</div>@endif
                                        @if (filled($entry->location))<div>{{ $entry->location }}</div>@endif
                                        @if (filled($entry->external_url))
                                            <p><a href="{{ $entry->external_url }}" rel="noopener noreferrer">More information</a></p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            <img
                                class="cv-portrait"
                                src="{{ $media->variantUrl($cvVariant) }}"
                                alt="{{ $media->altTextForAsset($cvAsset) }}"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    @else
                        <div class="cv-biography">
                            @foreach ($cvEntries as $entry)
                                <article class="cv-entry">
                                    <div class="cv-entry__line">
                                        @if (filled($entry->year_text))
                                            <span class="cv-entry__date">{{ $entry->year_text }}</span>
                                        @endif
                                        <span>{{ $entry->title }}</span>
                                    </div>
                                    @if (filled($entry->organisation))<div>{{ $entry->organisation }}</div>@endif
                                    @if (filled($entry->location))<div>{{ $entry->location }}</div>@endif
                                    @if (filled($entry->external_url))
                                        <p><a href="{{ $entry->external_url }}" rel="noopener noreferrer">More information</a></p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
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
                                @continue(! is_array($item) || (! $isPreview && ! \App\Models\CustomPageSetting::listItemPublished($item)))
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
                <div class="custom-page__component custom-page__contact">
                    <x-contact
                        :general-settings="$generalSettings"
                        :children="$settings->contactChildren($block)"
                    />
                </div>
            @endif

            @if ($type === 'legal_disclaimer' && $generalSettings->legal_disclaimer !== null)
                <section class="legal-disclaimer" aria-labelledby="legal-disclaimer-heading-{{ $blockIndex }}">
                    <h2 id="legal-disclaimer-heading-{{ $blockIndex }}">Haftungsablehnung</h2>
                    <p>{{ $generalSettings->legal_disclaimer }}</p>
                </section>
            @endif
        @endforeach
    </div>
@endsection
