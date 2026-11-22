@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')
@section('meta_description', $section->metaDescriptionOr($category->name.' — Lars Möller'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath(app(\App\Routing\SiteNodeRoute::class)->path($section)))

@section('content')
    <x-public.page-heading :section="$section" />
    @include('pages.artworks.partials.list')
@endsection
