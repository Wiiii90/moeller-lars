@props(['artwork'])

@php
    $title = trim((string) $artwork->title);
    $material = trim((string) ($artwork->medium ?? ''));
    $size = \App\Domain\Artwork\ArtworkDimensions::display($artwork->dimensions) ?? '';
    // Numeric legacy H × W [× 0] uses the editorial default cm.
    // Other artist-authored custom expressions remain unchanged.
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
