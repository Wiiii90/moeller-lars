@extends('layouts.app')

@section('title', ($settings->listing_title ?: 'Blog').' · Lars Möller')
@section('meta_description', $settings->listing_intro ?: 'Blog by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/blog'))

@section('content')
    @php
        $preview = app(\App\Domain\Content\SitePreviewContext::class);
    @endphp
    <section class="blog-page" aria-labelledby="blog-heading">
        <h2 id="blog-heading" class="category-heading">{{ $settings->listing_title ?: 'Blog' }}</h2>

        @if ($settings->listing_intro !== null)
            <div class="rich-text blog-intro">{!! $richText->render($settings->listing_intro) !!}</div>
        @endif

        @foreach ($posts as $post)
            @php
                $postUrl = $preview->url(route('blog.show', ['slug' => $post->slug]));
            @endphp
            <article class="blog-entry">
                <h3><a href="{{ $postUrl }}">{{ $post->title }}</a></h3>
                @if ($post->coverMedia !== null)
                    @php
                        $variant = $media->thumbnailVariantForAsset($post->coverMedia);
                        $variantWidth = (int) ($variant->getAttribute('width') ?? 0);
                        $variantHeight = (int) ($variant->getAttribute('height') ?? 0);
                    @endphp
                    <a href="{{ $postUrl }}" class="blog-entry__cover">
                        <img
                            src="{{ route('media.variant', $variant) }}"
                            alt="{{ $media->altTextForAsset($post->coverMedia) }}"
                            @if ($variantWidth > 0 && $variantHeight > 0)
                                width="{{ $variantWidth }}"
                                height="{{ $variantHeight }}"
                            @endif
                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                            decoding="async"
                            fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                        >
                    </a>
                @endif
                @if ($post->excerpt !== null)
                    <p>{{ $post->excerpt }}</p>
                @endif
            </article>
        @endforeach
    </section>
@endsection
