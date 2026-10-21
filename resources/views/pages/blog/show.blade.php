@extends('layouts.app')

@section('title', $post->title.' · Lars Möller')
@section('meta_description', $post->excerpt ?: $post->title)
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section).'/'.$post->slug))

@section('content')
    @php
        $preview = app(\App\Domain\Content\SitePreviewContext::class);
        $cover = $post->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
        $gallery = $post->mediaUsages->where('role', \App\Models\JournalEntryMedia::ROLE_GALLERY)->sortBy('position');
        $postDate = $post->published_at ?? $post->scheduled_at;
        $galleryPresentation = $post->galleryPresentation();
    @endphp
    <article class="blog-post" @if ($post->image_viewer_enabled) data-artwork-viewer-sequence @endif data-matomo-event-category="Journal" data-matomo-event-on-load="blog_view" data-matomo-event-name="{{ $post->title }}">
        <header class="blog-post__header">
            @if ($postDate instanceof \DateTimeInterface)
                <time class="blog-post__date" datetime="{{ $postDate->format('Y-m-d') }}">{{ \App\Domain\Content\RegionalFormats::publicDate($postDate) }}</time>
            @endif
            <h2 class="blog-post__title">{{ $post->title }}</h2>
        </header>
        @if ($cover instanceof \App\Models\JournalEntryMedia)
            <div class="blog-post__cover-mount">
                {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--cover blog-post__cover-media', true, includeInheritedCopyright: false, includeCopyrightNotice: false, showCaption: false, enableViewer: (bool) $post->image_viewer_enabled) !!}
            </div>
        @endif
        @if (trim((string) ($post->body ?? '')) !== '')
            <div class="rich-text journal-entry-content blog-post__body">{!! $post->image_viewer_enabled ? $journalMedia->renderRichText((string) $post->body) : $richText->render((string) $post->body) !!}</div>
        @endif
        @if ($gallery->isNotEmpty())
            <section class="blog-post__gallery" aria-label="Post images">
                <x-journal-gallery :gallery="$gallery" :presentation="$galleryPresentation" label="Post images" :journal-media="$journalMedia" :include-copyright-notice="false" :enable-viewer="(bool) $post->image_viewer_enabled" />
            </section>
        @endif
        <footer class="blog-post__footer">
            <a href="{{ $preview->url($siteNodeRoute->url($section)) }}"><x-public-icon name="arrow-left" /> Back to {{ $section->title }}</a>
        </footer>
    </article>
@endsection
