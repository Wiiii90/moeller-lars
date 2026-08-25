@extends('layouts.app')

@section('title', $post->title.' · Lars Möller')
@section('meta_description', $post->excerpt ?: $post->title)
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section).'/'.$post->slug))

@section('content')
    @php
        $cover = $post->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
        $gallery = $post->mediaUsages->where('role', \App\Models\JournalEntryMedia::ROLE_GALLERY)->sortBy('position');
    @endphp
    <article class="blog-post" data-matomo-event-category="Journal" data-matomo-event-on-load="blog_view" data-matomo-event-name="{{ $post->title }}">
        <h2 class="category-heading">{{ $post->title }}</h2>
        @if ($cover instanceof \App\Models\JournalEntryMedia)
            {!! $journalContent->renderMedia($cover, 'journal-entry-media journal-entry-media--cover blog-post__cover-media', true) !!}
        @endif
        <div class="rich-text journal-entry-content">{!! $journalContent->render($post) !!}</div>
        @if ($gallery->isNotEmpty())
            <div class="journal-entry-gallery" aria-label="Post images">
                @foreach ($gallery as $usage){!! $journalContent->renderMedia($usage, 'journal-entry-media journal-entry-media--gallery') !!}@endforeach
            </div>
        @endif
    </article>
@endsection
