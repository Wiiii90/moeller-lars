@php
    $year = $artwork->work_date?->format('Y');
    if (! $year && $artwork->date_precision === 'year' && preg_match('/(?<!\d)(\d{4})(?!\d)/', (string) $artwork->legacy_date_raw, $matches)) {
        $year = $matches[1];
    }
    $imageUrl = $media->thumbnailUrl($artwork);
@endphp

<article class="artwork-card">
    <a href="{{ route('artworks.show', $artwork->slug) }}">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
        @else
            <div class="missing-media" role="img" aria-label="Media unavailable">Media unavailable</div>
        @endif
        <div class="artwork-card__metadata">
            <p>{{ $artwork->title }}@if ($year), {{ $year }}@endif</p>
            @if ($artwork->medium || $artwork->dimensions)
                <p>{{ $artwork->medium }}@if ($artwork->medium && $artwork->dimensions), @endif{{ $artwork->dimensions }}</p>
            @endif
            @if ($artwork->description)
                <p>{{ $artwork->description }}</p>
            @endif
        </div>
    </a>
</article>
