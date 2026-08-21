@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' · Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($section->publicPath()))

@section('content')
    <div class="custom-page" aria-label="{{ $section->title }}">
        <h2 class="category-heading">{{ $section->title }}</h2>

        @forelse ($blocks as $blockIndex => $block)
            @php
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $assetId = is_array($block) && is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                $asset = $assetId !== null ? $assets->get($assetId) : null;
                $variant = $asset !== null ? $media->thumbnailVariantForAsset($asset) : null;
            @endphp

            @if ($type === 'text' || $type === 'list')
                <section class="custom-page__component @if ($asset !== null && $variant !== null) has-media @endif">
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
                                    @continue(! is_array($item))
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
                <div class="custom-page__component custom-page__contact">
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
@endsection
