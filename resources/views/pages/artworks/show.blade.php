@extends('layouts.app')

@php
    $year = $artwork->work_date?->format('Y');
    $imageUrl = $media->originalUrl($artwork);
    $primaryMedia = $media->primaryMedia($artwork);
    $primaryAsset = $primaryMedia->getRelationValue('mediaAsset');
@endphp

@section('title', $artwork->title.' — Lars Möller')
@section('meta_description', $artwork->description ?: $artwork->title.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/artworks/'.$artwork->slug))

@section('content')
    <article class="artwork-detail" data-artwork-viewer-sequence>
        <a class="artwork-detail__viewer-trigger" href="{{ $imageUrl }}" data-artwork-viewer-trigger data-viewer-key="{{ $artwork->slug }}">
            <img class="artwork-image artwork-detail__image" src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
        </a>
        <div class="artwork-detail__metadata">
            <p>{{ $artwork->title }}@if ($year), {{ $year }}@endif</p>
            @if ($artwork->medium || $artwork->dimensions)
                <p>{{ $artwork->medium }}@if ($artwork->medium && $artwork->dimensions), @endif{{ $artwork->dimensions }}</p>
            @endif
            @if ($artwork->description)
                <p>{{ $artwork->description }}</p>
            @endif
            @if ($primaryAsset->credit)
                <p class="artwork-credit">{{ $primaryAsset->credit }}</p>
            @endif
            @if ($primaryAsset->copyright_notice)
                <p class="artwork-copyright">{{ $primaryAsset->copyright_notice }}</p>
            @endif
        </div>
        <div class="artwork-viewer-sequence-data" hidden aria-hidden="true">
            @foreach ($viewerArtworks as $viewerArtwork)
                @php($viewerMediaUrl = $media->originalUrl($viewerArtwork))
                <span
                    data-artwork-viewer-item
                    data-viewer-key="{{ $viewerArtwork->slug }}"
                    data-viewer-src="{{ $viewerMediaUrl }}"
                    data-viewer-alt="{{ $media->altText($viewerArtwork) }}"
                    data-viewer-title="{{ $viewerArtwork->title }}"
                    data-viewer-page="{{ route('artworks.show', $viewerArtwork->slug) }}"
                ></span>
            @endforeach
        </div>
    </article>
@endsection
