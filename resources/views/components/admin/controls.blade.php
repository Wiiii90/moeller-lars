@props([
    'ariaLabel' => null,
    'metricGrid' => false,
    'filterCount' => null,
])

@php
    $hasCompleteDataToolbar = isset($search) && isset($filters) && isset($reset) && isset($actions) && isset($selection);
    $usesMetricGrid = (bool) $metricGrid || $hasCompleteDataToolbar;
    $detectedFilterCount = isset($filters) ? substr_count($filters->toHtml(), '<select') : 0;
    $normalizedFilterCount = min(max((int) ($filterCount ?? $detectedFilterCount), 0), 2);
@endphp

<div
    {{ $attributes->class([
        'admin-data-controls',
        'admin-data-controls--has-search' => isset($search),
        'admin-data-controls--six-cell' => $usesMetricGrid,
        'admin-data-controls--six-cell-filters-'.$normalizedFilterCount => $usesMetricGrid,
    ]) }}
    @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
>
    @isset($search)
        {{ $search }}
    @endisset

    @isset($filters)
        {{ $filters }}
    @endisset

    @if ($usesMetricGrid)
        <div class="admin-data-controls__utility">
            @isset($reset)
                {{ $reset }}
            @endisset

            @isset($actions)
                {{ $actions }}
            @endisset

            @isset($selection)
                {{ $selection }}
            @endisset
        </div>
    @else
        @isset($reset)
            {{ $reset }}
        @endisset

        @isset($actions)
            {{ $actions }}
        @endisset

        @isset($selection)
            {{ $selection }}
        @endisset
    @endif
</div>
