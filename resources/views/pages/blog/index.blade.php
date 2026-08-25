@extends('layouts.app')

@php $pageTitle = $settings->listing_title ?: $section->title; @endphp
@section('title', $pageTitle.' · Lars Möller')
@section('meta_description', $settings->listing_intro ?: $pageTitle.' by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    @php $preview = app(\App\Domain\Content\SitePreviewContext::class); @endphp
    <section class="blog-page" aria-labelledby="blog-heading">
        <h2 id="blog-heading" class="category-heading">{{ $pageTitle }}</h2>
        @if ($settings->listing_intro !== null)<div class="rich-text blog-intro">{!! $richText->render($settings->listing_intro) !!}</div>@endif
        @foreach ($posts as $post)
            @php
                $postUrl = $preview->url(route('journal.show', ['section' => $section->slug, 'slug' => $post->slug]));
                $cover = $post->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
            @endphp
            <article class="blog-entry">
                <h3><a href="{{ $postUrl }}">{{ $post->title }}</a></h3>
                @if ($cover instanceof \App\Models\JournalEntryMedia)
                    <a href="{{ $postUrl }}" class="blog-entry__cover">{!! $journalContent->renderMedia($cover, 'journal-entry-media journal-entry-media--listing-cover', $loop->first) !!}</a>
                @endif
                @if ($post->excerpt !== null)<p>{{ $post->excerpt }}</p>@endif
            </article>
        @endforeach
    </section>
@endsection
