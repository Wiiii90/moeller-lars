@props(['artwork'])

@php
    $title = trim((string) $artwork->title);
    $material = trim((string) ($artwork->medium ?? ''));
    $size = trim((string) ($artwork->dimensions ?? ''));
    // Structured dimensions contain their saved unit. A custom size is
    // already artist-authored and must not receive an invented suffix.
    $details = [];
    if ($artwork->work_year !== null) {
        $details[] = (string) $artwork->work_year;
    }
    if ($size !== '') {
        $details[] = $size;
    }
@endphp

<span class="artwork-caption">
    <span class="artwork-caption__primary">
        <span class="artwork-caption__title">{{ $title }}</span>@if ($material !== '')<span class="artwork-caption__material">, {{ $material }}</span>@endif
    </span>
    @if ($details !== [])
        <span class="artwork-caption__secondary">{{ implode(', ', $details) }}</span>
    @endif
</span>
