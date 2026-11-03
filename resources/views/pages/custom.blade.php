@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->metaDescriptionOr($section->title.' · Lars Möller'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    <x-public.page-heading :section="$section" />
    @php
        $isPreview = app(\App\Domain\Content\SitePreviewContext::class)->active();
    @endphp

    @include('pages.partials.component-sequence', [
        'blocks' => $blocks,
        'componentSettings' => $settings,
        'label' => $section->title,
        'isHome' => false,
    ])

@endsection