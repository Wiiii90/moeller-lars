@props([
    'ariaLabel' => null,
    'metricGrid' => false,
    'filterCount' => 0,
])

@php
    $normalizedFilterCount = min(max((int) $filterCount, 0), 2);
@endphp

<div
    {{ $attributes->class([
        'admin-data-controls',
        'admin-data-controls--has-search' => isset($search),
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

    @if ($metricGrid)
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
