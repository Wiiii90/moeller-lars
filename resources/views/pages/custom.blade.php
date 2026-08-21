@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' · Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($section->publicPath()))

@section('content')
    @php
        $isPreview = app(\App\Domain\Content\SitePreviewContext::class)->active();
        $pageSlug = (string) $section->slug;
        $isLegacyProfile = $pageSlug === 'cv';
        $isContactPage = $pageSlug === 'contact';
    @endphp

    @if ($isLegacyProfile)
        @php
            $profileBlocks = collect($blocks)->filter(fn ($block): bool => is_array($block));
            $listBlocks = $profileBlocks->filter(fn (array $block): bool => ($block['type'] ?? null) === 'list');
            $textBlocks = $profileBlocks->filter(fn (array $block): bool => ($block['type'] ?? null) === 'text');
            $contactBlock = $profileBlocks->first(fn (array $block): bool => ($block['type'] ?? null) === 'contact');
            $portraitBlock = $profileBlocks->first(fn (array $block): bool => is_numeric($block['media_asset_id'] ?? null));
            $portraitAssetId = is_array($portraitBlock) && is_numeric($portraitBlock['media_asset_id'] ?? null)
                ? (int) $portraitBlock['media_asset_id']
                : null;
            $portraitAsset = $portraitAssetId !== null ? $assets->get($portraitAssetId) : null;
            $portraitVariant = $portraitAsset !== null ? $media->thumbnailVariantForAsset($portraitAsset) : null;
            $portraitWidth = $portraitVariant !== null ? (int) ($portraitVariant->width ?? 0) : 0;
            $portraitHeight = $portraitVariant !== null ? (int) ($portraitVariant->height ?? 0) : 0;
        @endphp

        <div class="cv-page">
            @if ($listBlocks->isNotEmpty() || ($portraitAsset !== null && $portraitVariant !== null))
                <section class="cv-section" aria-label="CV">
                    <div class="cv-legacy-layout">
                        <div class="cv-legacy-copy">
                            <div class="cv-biography">
                                @foreach ($listBlocks as $block)
                                    @foreach (($block['items'] ?? []) as $item)
                                        @continue(! is_array($item) || (! $isPreview && (($item['visible'] ?? true) !== true)))
                                        <article class="cv-entry">
                                            <div class="cv-entry__content">
                                                <div class="cv-entry__line">
                                                    @if (filled($item['date'] ?? null))
                                                        <span class="cv-entry__date">{{ $item['date'] }}</span>
                                                    @endif
                                                    <span>{{ $item['title'] ?? '' }}</span>
                                                </div>
                                                @if (filled($item['meta'] ?? null))<div>{{ $item['meta'] }}</div>@endif
                                                @if (filled($item['location'] ?? null))<div>{{ $item['location'] }}</div>@endif
                                                @if (filled($item['body'] ?? null))
                                                    <div class="rich-text">{!! $richText->render((string) $item['body']) !!}</div>
                                                @endif
                                                @if (filled($item['url'] ?? null))
                                                    <p><a href="{{ $item['url'] }}" rel="noopener noreferrer">More information</a></p>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                    @if ((bool) ($block['divider'] ?? true))<div class="cv-component-divider" aria-hidden="true"></div>@endif
                                @endforeach
                            </div>
                        </div>

                        @if ($portraitAsset !== null && $portraitVariant !== null)
                            <img
                                class="cv-portrait"
                                src="{{ route('media.variant', $portraitVariant) }}"
                                alt="{{ $media->altTextForAsset($portraitAsset) }}"
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
            @endif

            @if (is_array($contactBlock))
                <div @class(['cv-contact-area', 'has-divider' => (bool) ($contactBlock['divider'] ?? true)])>
                    <x-contact
                        :general-settings="$generalSettings"
                        :contact-settings="$contactSettings"
                        :show-status="true"
                        :show-email="(bool) ($contactBlock['show_email'] ?? true)"
                        :show-form="(bool) ($contactBlock['show_form'] ?? true)"
                        :social-platforms="is_array($contactBlock['social_platforms'] ?? null) ? $contactBlock['social_platforms'] : []"
                    />
                </div>
            @endif

            @if ($textBlocks->isNotEmpty())
                <section class="cv-text-blocks" aria-label="Additional information">
                    @foreach ($textBlocks as $block)
                        <article @class(['cv-text-block', 'has-divider' => (bool) ($block['divider'] ?? true)])>
                            @if (filled($block['title'] ?? null))<h2>{{ $block['title'] }}</h2>@endif
                            @if (filled($block['body'] ?? null))
                                <div class="rich-text">{!! $richText->render((string) $block['body']) !!}</div>
                            @endif
                        </article>
                    @endforeach
                </section>
            @endif

            @if ($generalSettings->legal_disclaimer !== null)
                <section class="legal-disclaimer cv-bottom-disclaimer" aria-labelledby="legal-disclaimer-heading">
                    <h2 id="legal-disclaimer-heading">Haftungsablehnung</h2>
                    <p>{{ $generalSettings->legal_disclaimer }}</p>
                </section>
            @endif
        </div>
    @else
        <div @class(['contact-page' => $isContactPage, 'custom-page', 'custom-page--'.$pageSlug]) aria-label="{{ $section->title }}">
            @unless ($isContactPage)
                <h2 class="category-heading">{{ $section->title }}</h2>
            @endunless

            @forelse ($blocks as $blockIndex => $block)
                @php
                    $type = is_array($block) ? ($block['type'] ?? null) : null;
                    $assetId = is_array($block) && is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $asset = $assetId !== null ? $assets->get($assetId) : null;
                    $variant = $asset !== null ? $media->thumbnailVariantForAsset($asset) : null;
                    $divider = (bool) ($block['divider'] ?? true);
                @endphp

                @if ($type === 'text' || $type === 'list')
                    <section @class([
                        'custom-page__component',
                        'has-media' => $asset !== null && $variant !== null,
                        'has-divider' => $divider,
                    ])>
                        <div class="custom-page__copy">
                            @if (filled($block['title'] ?? null))
                                <h3>{{ $block['title'] }}</h3>
                            @endif

                            @if ($type === 'text' && filled($block['body'] ?? null))
                                <div class="rich-text">{!! $richText->render((string) $block['body']) !!}</div>
                            @endif

                            @if ($type === 'list')
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
                            @endif
                        </div>

                        @if ($asset !== null && $variant !== null)
                            <figure class="custom-page__media">
                                <img
                                    src="{{ route('media.variant', $variant) }}"
                                    alt="{{ $media->altTextForAsset($asset) }}"
                                    @if ((int) ($variant->width ?? 0) > 0 && (int) ($variant->height ?? 0) > 0)
                                        width="{{ $variant->width }}"
                                        height="{{ $variant->height }}"
                                    @endif
                                    loading="{{ $blockIndex === 0 ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                >
                            </figure>
                        @endif
                    </section>
                @elseif ($type === 'contact')
                    <div class="custom-page__component custom-page__contact @if ($divider) has-divider @endif">
                        <x-contact
                            :general-settings="$generalSettings"
                            :contact-settings="$contactSettings"
                            :show-status="true"
                            :show-email="(bool) ($block['show_email'] ?? true)"
                            :show-form="(bool) ($block['show_form'] ?? true)"
                            :social-platforms="is_array($block['social_platforms'] ?? null) ? $block['social_platforms'] : []"
                        />
                    </div>
                @endif
            @empty
                <p class="public-empty-state">This page does not have published content yet.</p>
            @endforelse
        </div>
    @endif
@endsection
