@props([
    'ariaLabel' => null,
    'metricGrid' => false,
    'filterCount' => null,
    'searchSpan' => null,
])

@php
    $detectedFilterCount = isset($filters) ? substr_count($filters->toHtml(), '<label') : 0;
    $actualFilterCount = max((int) ($filterCount ?? $detectedFilterCount), 0);
    $normalizedFilterCount = min($actualFilterCount, 4);
    $normalizedSearchSpan = $searchSpan === null
        ? null
        : min(max((int) $searchSpan, 1), 6);
    $hasUtility = isset($reset) || isset($actions) || isset($selection);
@endphp

<div
    {{ $attributes->class([
        'admin-data-controls',
        'admin-data-controls--has-search' => isset($search),
        'admin-data-controls--filters-'.$normalizedFilterCount,
        'admin-data-controls--six-cell' => $metricGrid,
        'admin-data-controls--six-cell-filters-'.$normalizedFilterCount => $metricGrid,
        'admin-data-controls--six-cell-search-'.$normalizedSearchSpan => $metricGrid && $normalizedSearchSpan !== null,
    ]) }}
    @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    @if (isset($filters) && ! $metricGrid)
        x-data="{ adminFiltersOpen: false }"
        x-on:keydown.escape.window="adminFiltersOpen = false"
        x-on:click.outside="adminFiltersOpen = false"
    @endif
>
    @isset($search)
        {{ $search }}
    @endisset

    @isset($filters)
        @if (! $metricGrid)
            <button
                class="admin-data-controls__filter-trigger"
                type="button"
                x-on:click="adminFiltersOpen = ! adminFiltersOpen"
                x-bind:aria-expanded="adminFiltersOpen.toString()"
                aria-haspopup="true"
                aria-label="Toggle filters"
            >
                <span>Filters</span>
                <span class="admin-data-controls__filter-trigger-count">{{ $actualFilterCount }}</span>
            </button>
        @endif
        <div
            class="admin-data-controls__filters"
            @if (! $metricGrid) x-bind:class="{ 'is-open': adminFiltersOpen }" @endif
            role="group"
            aria-label="Filters"
        >
            {{ $filters }}
        </div>
    @endisset

    @if ($hasUtility)
        <div @class([
            'admin-data-controls__utility',
            'admin-data-controls__utility--has-reset' => isset($reset),
            'admin-data-controls__utility--has-actions' => isset($actions),
            'admin-data-controls__utility--has-selection' => isset($selection),
        ])>
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
