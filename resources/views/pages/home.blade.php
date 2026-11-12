@extends('layouts.app')

@section('title', 'Lars Möller')
@section('meta_description', $pageSection->metaDescriptionOr('Lars Möller — artist'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'))

@if ($template === \App\Domain\Content\HomeTemplate::Artwork)
    @section('content_class', ($expandLandscape || $expandPortrait) ? 'site-content--fitted-artwork site-content--home-hero-fitted' : '')
    @section('artwork_header_divider', \App\Domain\Artwork\GalleryPresentation::artworkPageDividerForMode($artworkPageSurfaceMode) ?? '')
    @section('artwork_menu_surface_wide', $artworkMenuSurfaceWide ? '1' : '')
@endif

@if ($gateActive)
    @section('hide_navigation', '1')
@endif

@section('content')
    <x-public.page-heading :section="$pageSection" />
    @if ($template === \App\Domain\Content\HomeTemplate::Artwork)
        @include('pages.home.artwork')
    @elseif (in_array($template, [\App\Domain\Content\HomeTemplate::UnderConstruction, \App\Domain\Content\HomeTemplate::Custom], true))
        @include('pages.home.components')
    @endif
@endsection