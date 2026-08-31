@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->title.' by Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    @php $preview = app(\App\Domain\Content\SitePreviewContext::class); @endphp
    <section class="blog-page" aria-labelledby="blog-heading">
        <h2 id="blog-heading" class="category-heading">{{ $section->title }}</h2>
        @foreach ($posts as $post)
            @php
                $postUrl = $preview->url(route('journal.show', ['section' => $section->slug, 'slug' => $post->slug]));
                $cover = $post->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
            @endphp
            <article class="blog-entry">
                <h3><a href="{{ $postUrl }}">{{ $post->title }}</a></h3>
                @if ($cover instanceof \App\Models\JournalEntryMedia)
                    <a href="{{ $postUrl }}" class="blog-entry__cover">{!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--listing-cover', $loop->first) !!}</a>
                @endif
                @if ($post->excerpt !== null)<p>{{ $post->excerpt }}</p>@endif
            </article>
        @endforeach
    </section>
@endsection
