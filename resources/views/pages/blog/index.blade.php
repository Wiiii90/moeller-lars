@extends('layouts.app')

@section('title', ($settings->listing_title ?: 'Blog').' · Lars Möller')
@section('meta_description', $settings->listing_intro ?: 'Blog by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/blog'))

@section('content')
    <section class="blog-page" aria-labelledby="blog-heading">
        <h2 id="blog-heading" class="category-heading">{{ $settings->listing_title ?: 'Blog' }}</h2>

        @if ($settings->listing_intro !== null)
            <div class="rich-text blog-intro">{!! $richText->render($settings->listing_intro) !!}</div>
        @endif

        @foreach ($posts as $post)
            <article class="blog-entry">
                <h3><a href="{{ route('blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h3>
                @if ($post->coverMedia !== null)
                    <a href="{{ route('blog.show', ['slug' => $post->slug]) }}" class="blog-entry__cover">
                        <img src="{{ $media->thumbnailUrlForAsset($post->coverMedia) }}" alt="{{ $media->altTextForAsset($post->coverMedia) }}" loading="lazy">
                    </a>
                @endif
                @if ($post->excerpt !== null)
                    <p>{{ $post->excerpt }}</p>
                @endif
            </article>
        @endforeach
    </section>
@endsection
