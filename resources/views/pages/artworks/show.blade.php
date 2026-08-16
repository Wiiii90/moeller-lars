@extends('layouts.app')

@php
    $year = $artwork->work_date?->format('Y');
    if (! $year && $artwork->date_precision === 'year' && preg_match('/(?<!\d)(\d{4})(?!\d)/', (string) $artwork->legacy_date_raw, $matches)) {
        $year = $matches[1];
    }
    $imageUrl = $media->originalUrl($artwork);
    $primaryMedia = $artwork->artworkMedia->firstWhere('role', 'primary');
    $primaryAsset = $primaryMedia?->mediaAsset;
@endphp

@section('title', $artwork->title.' — Lars Möller')

@section('content')
    <article class="artwork-detail">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
        @else
            <div class="missing-media" role="img" aria-label="Media unavailable">Media unavailable</div>
        @endif
        <div class="artwork-detail__metadata">
            <p>{{ $artwork->title }}@if ($year), {{ $year }}@endif</p>
            @if ($artwork->medium || $artwork->dimensions)
                <p>{{ $artwork->medium }}@if ($artwork->medium && $artwork->dimensions), @endif{{ $artwork->dimensions }}</p>
            @endif
            @if ($artwork->description)
                <p>{{ $artwork->description }}</p>
            @endif
            @if ($primaryAsset?->credit)
                <p class="artwork-credit">{{ $primaryAsset->credit }}</p>
            @endif
            @if ($primaryAsset?->copyright_notice)
                <p class="artwork-copyright">{{ $primaryAsset->copyright_notice }}</p>
            @endif
        </div>
    </article>
@endsection
