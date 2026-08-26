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
            {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--cover blog-post__cover-media', true) !!}
        @endif
        @if (trim((string) ($post->body ?? '')) !== '')
            <div class="rich-text journal-entry-content">{!! $richText->render((string) $post->body) !!}</div>
        @endif
        @if ($gallery->isNotEmpty())
            <div class="journal-entry-gallery" aria-label="Post images">
                @foreach ($gallery as $usage){!! $journalMedia->render($usage, 'journal-entry-media journal-entry-media--gallery') !!}@endforeach
            </div>
        @endif
    </article>
@endsection
