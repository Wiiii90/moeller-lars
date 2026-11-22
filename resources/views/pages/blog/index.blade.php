@extends('layouts.app')

@section('title', $section->title.' · Lars Möller')
@section('meta_description', $section->metaDescriptionOr($section->title.' by Lars Möller'))
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath($siteNodeRoute->path($section)))

@section('content')
    <x-public.page-heading :section="$section" />
    @include('pages.blog.partials.list')
@endsection
