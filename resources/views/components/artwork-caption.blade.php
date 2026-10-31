@props(['artwork'])

@php
    $title = trim((string) $artwork->title);
    // Historical preset names may contain HTML entities. Decode for display
    // only: persisted material values and existing Artwork associations stay intact.
    $material = html_entity_decode(trim((string) ($artwork->medium ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $size = \App\Domain\Artwork\ArtworkDimensions::display($artwork->dimensions) ?? '';
    $year = $artwork->work_year !== null ? trim((string) $artwork->work_year) : '';
    // Numeric legacy H × W [× 0] uses the editorial default cm.
    // Other artist-authored custom expressions remain unchanged.
    $details = implode(', ', array_filter([$size, $material], static fn (string $value): bool => $value !== ''));
@endphp

<span class="artwork-caption">
    <span class="artwork-caption__primary">
        <span class="artwork-caption__title">{{ $title }}</span>@if ($year !== '')<span class="artwork-caption__year">, {{ $year }}</span>@endif
    </span>
    @if ($details !== '')
        <span class="artwork-caption__secondary">{{ $details }}</span>
    @endif
</span>
