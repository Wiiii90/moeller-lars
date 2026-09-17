@props([
    'ariaLabel' => null,
    'metricGrid' => false,
    'filterCount' => null,
])

@php
    $detectedFilterCount = isset($filters) ? substr_count($filters->toHtml(), '<select') : 0;
    $normalizedFilterCount = max((int) ($filterCount ?? $detectedFilterCount), 0);
    $hasUtility = isset($reset) || isset($actions) || isset($selection);
@endphp

<div
    {{ $attributes->class([
        'admin-data-controls',
        'admin-data-controls--has-search' => isset($search),
        'admin-data-controls--filters-'.$normalizedFilterCount,
        'admin-data-controls--six-cell' => $metricGrid,
        'admin-data-controls--six-cell-filters-'.$normalizedFilterCount => $metricGrid,
    ]) }}
    @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
>
    @isset($search)
        {{ $search }}
    @endisset

    @isset($filters)
        {{ $filters }}
    @endisset

    @if ($hasUtility)
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
    @endif
</div>
