@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')
@section('meta_description', $section->metaDescriptionOr($category->name.' — Lars Möller'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath(app(\App\Routing\SiteNodeRoute::class)->path($section)))

@section('content')
    <x-public.page-heading :section="$section" />
    <div class="artwork-list{{ $divider !== 'none' ? ' artwork-list--divided' : '' }}"
        data-artwork-viewer-sequence data-gallery-image-buffer data-gallery-infinite
        data-gallery-viewer-manifest="{{ request()->fullUrlWithQuery(['gallery_viewer' => '1']) }}">
        @include('pages.artworks.partials.batch')
    </div>
    @if ($hasMore)
        <a class="gallery-load-more" data-gallery-load-more
            href="{{ request()->fullUrlWithQuery(['gallery_page' => $page + 1]) }}">Load more artworks</a>
    @endif
@endsection
