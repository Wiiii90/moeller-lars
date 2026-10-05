@props([
    'ariaLabel' => null,
    'metricGrid' => false,
    'filterCount' => null,
    'searchSpan' => null,
])

@php
    $detectedFilterCount = isset($filters) ? substr_count($filters->toHtml(), '<label') : 0;
    $normalizedFilterCount = min(max((int) ($filterCount ?? $detectedFilterCount), 0), 4);
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
>
    @isset($search)
        {{ $search }}
    @endisset

    @isset($filters)
        <div
            class="admin-filter-overflow"
            x-data="{
                open: false,
                panelStyle: '',
                positionPanel() {
                    const trigger = this.$refs.trigger
                    if (! trigger) return

                    const viewportMargin = 8
                    const panelGap = 6
                    const preferredWidth = 272
                    const panelWidth = Math.max(0, Math.min(preferredWidth, window.innerWidth - (viewportMargin * 2)))
                    const rect = trigger.getBoundingClientRect()
                    const maxLeft = Math.max(viewportMargin, window.innerWidth - panelWidth - viewportMargin)
                    const left = Math.min(Math.max(rect.left, viewportMargin), maxLeft)
                    const below = window.innerHeight - rect.bottom - viewportMargin - panelGap
                    const above = rect.top - viewportMargin - panelGap

                    if (below >= 180 || below >= above) {
                        this.panelStyle = `left:${left}px;top:${rect.bottom + panelGap}px;bottom:auto;width:${panelWidth}px;max-height:${Math.max(120, below)}px;`
                    } else {
                        this.panelStyle = `left:${left}px;top:auto;bottom:${window.innerHeight - rect.top + panelGap}px;width:${panelWidth}px;max-height:${Math.max(120, above)}px;`
                    }
                },
            }"
            x-bind:data-open="open ? 'true' : 'false'"
            x-on:keydown.escape.window="open = false"
            x-on:resize.window="if (open) $nextTick(() => positionPanel())"
            x-on:scroll.window="open = false"
            x-on:click.outside="open = false"
        >
            <button
                class="admin-action admin-action--with-icon admin-filter-overflow__trigger"
                type="button"
                x-ref="trigger"
                x-on:click="open = ! open; if (open) $nextTick(() => positionPanel())"
                x-bind:aria-expanded="open"
                aria-haspopup="true"
                aria-label="Filters"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Filter->mini()" class="admin-action__icon" />
                <span class="admin-action__label">Filters</span>
            </button>

            <div
                class="admin-data-controls__filters"
                role="group"
                aria-label="Filters"
                x-ref="panel"
                x-bind:style="panelStyle"
            >
                {{ $filters }}

                @isset($reset)
                    <div class="admin-filter-overflow__reset">
                        {{ $reset }}
                    </div>
                @endisset
            </div>
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
