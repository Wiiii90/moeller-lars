@foreach ($artworks as $artwork)
    <x-artwork-card :artwork="$artwork" :media="$media"
        :eager="$page === 1 && $loop->index < 2"
        :priority="$page === 1 && $loop->first"
        :gallery-buffered="$page > 1 || $loop->index >= 2"
        :gallery-index="true"
        :gallery-shift="$galleryOffsets[(int) $artwork->getKey()] ?? 0"
        :gallery-gap="$galleryGaps[(int) $artwork->getKey()] ?? 0" />
    @if ($divider !== 'none' && (! $loop->last || $hasMore))
        {{-- Preserve precisely one separator between adjacent canonical Artworks,
             including across server-delivered batch boundaries. --}}
        <div class="custom-page__divider custom-page__divider--{{ $divider }} artwork-list__divider artwork-list__divider--gap-{{ $galleryGaps[(int) $artwork->getKey()] ?? 0 }}" aria-hidden="true"></div>
    @endif
@endforeach
