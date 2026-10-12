@props(['section'])

@if ($section instanceof \App\Models\SiteSection && (bool) $section->getAttribute('content_heading_enabled'))
    @php
        $alignment = (string) ($section->getAttribute('content_heading_alignment') ?: 'left');
        $alignment = array_key_exists($alignment, \App\Domain\Content\SitePageHeading::ALIGNMENTS) ? $alignment : 'left';
        $divider = (string) ($section->getAttribute('content_heading_divider') ?: 'none');
        $divider = array_key_exists($divider, \App\Domain\Content\SitePageHeading::DIVIDERS) ? $divider : 'none';
    @endphp
    <header class="public-page-heading public-page-heading--{{ $alignment }}">
        <h2 class="category-heading">{{ \App\Domain\Content\SitePageHeading::text($section) }}</h2>
        @if ($divider !== 'none')
            <div class="custom-page__divider custom-page__divider--{{ $divider }} public-page-heading__divider" aria-hidden="true"></div>
        @endif
    </header>
@endif
