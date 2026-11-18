@extends('layouts.app')

@section('title', $section->getAttribute('title'))
@section('meta_description', $section->metaDescriptionOr('Lars Möller — artist'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath(app(\App\Routing\SiteNodeRoute::class)->path($section)))

@section('content_class', ($expandLandscape || $expandPortrait) ? 'site-content--fitted-artwork site-content--home-hero-fitted' : '')
@section('artwork_header_divider', \App\Domain\Content\GalleryHeroConfigurationService::artworkPageDividerForMode($artworkPageSurfaceMode) ?? '')
@section('artwork_menu_surface_wide', $artworkMenuSurfaceWide ? '1' : '')

@section('content')
    <x-public.page-heading :section="$section" />
    @include('pages.gallery.hero-artwork')
@endsection
