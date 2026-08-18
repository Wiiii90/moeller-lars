@extends('layouts.app')

@section('title', $post->title.' · Lars Möller')
@section('meta_description', $post->excerpt ?: $post->title)
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/blog/'.$post->slug))

@section('content')
    <article
        class="blog-post"
        data-matomo-event-category="Blog"
        data-matomo-event-on-load="blog_view"
        data-matomo-event-name="{{ $post->title }}"
    >
        <h2 class="category-heading">{{ $post->title }}</h2>
        @if ($post->coverMedia !== null)
            <img class="blog-post__cover" src="{{ $media->thumbnailUrlForAsset($post->coverMedia) }}" alt="{{ $media->altTextForAsset($post->coverMedia) }}">
        @endif
        <div class="rich-text">{!! $richText->render($post->body) !!}</div>
    </article>
@endsection
