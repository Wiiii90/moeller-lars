@extends('layouts.app')

@section('title', $section->getAttribute('title'))
@section('meta_description', $section->metaDescriptionOr('Lars Möller — artist'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath(app(\App\Routing\SiteNodeRoute::class)->path($section)))

{{-- Every Gallery Hero uses the fitted Artwork presentation; General owns
     the Menu/Page paper, divider and padding. --}}
@section('content_class', 'site-content--gallery-hero-fitted')

@section('content')
    <x-public.page-heading :section="$section" />
    @include('pages.gallery.hero-artwork')
@endsection
