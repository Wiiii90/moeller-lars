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
                        <img src="{{ $media->variantUrl($variant) }}" alt="{{ $imageAlt }}" loading="{{ $loading }}" decoding="async">
                    </figure>
                @endif
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
                @php
                    $listAssetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $listAsset = $listAssetId !== null ? $assets->get($listAssetId) : null;
                    $listVariant = $listAsset !== null && $listAsset->getAttribute('state') === 'available'
                        ? $media->thumbnailVariantForAsset($listAsset)
                        : null;
                @endphp
                <section class="custom-page__component">
                    <div class="custom-page__list-layout{{ $listVariant !== null ? ' custom-page__list-layout--with-media' : '' }}">
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
                        @if ($listVariant !== null && $listAsset instanceof \App\Models\MediaAsset)
                            <img
                                class="custom-page__list-media"
                                src="{{ $media->variantUrl($listVariant) }}"
                                alt="{{ $media->altTextForAsset($listAsset) }}"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif
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
                    <x-contact :general-settings="$generalSettings" :children="$settings->contactChildren($block)" />
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