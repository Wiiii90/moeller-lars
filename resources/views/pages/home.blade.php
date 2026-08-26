@extends('layouts.app')

@section('title', 'Lars Möller')
@section('meta_description', 'Lars Möller — artist')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'))

@if ($gateActive)
    @section('hide_navigation', '1')
@endif

@section('content')
    @if ($template === \App\Domain\Content\HomeTemplate::Artwork)
        @include('pages.home.artwork')
    @elseif (in_array($template, [\App\Domain\Content\HomeTemplate::UnderConstruction, \App\Domain\Content\HomeTemplate::Custom], true))
        @include('pages.home.components')
    @else
        <div class="custom-page" aria-label="Home">
            <p class="public-empty-state">No public page is currently available after Home.</p>
        </div>
    @endif
@endsection