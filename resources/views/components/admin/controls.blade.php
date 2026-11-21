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
    @if ($ariaLabel) aria-label="{{ __($ariaLabel) }}" @endif
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
                openPanel() {
                    // Keep the fixed panel invisible until its measured
                    // position has been applied. Never paint an unpositioned
                    // full-width popover for one frame.
                    this.panelStyle = ''
                    this.open = true
                    this.$nextTick(() => {
                        if (this.open) this.positionPanel()
                    })
                },
                closePanel() {
                    this.open = false
                    this.panelStyle = ''
                },
                togglePanel() {
                    if (this.open) this.closePanel()
                    else this.openPanel()
                },
                positionPanel() {
                    if (! this.open) return
                    const trigger = this.$refs.trigger
                    if (! trigger) return

                    const panel = this.$refs.panel
                    if (! panel) return

                    const viewportMargin = 8
                    const panelGap = 6
                    const viewportWidth = document.documentElement.clientWidth
                    const viewportHeight = window.innerHeight
                    const panelWidth = Math.max(0, Math.min(320, viewportWidth - 2 * viewportMargin))
                    const rect = trigger.getBoundingClientRect()
                    const left = Math.min(
                        Math.max(rect.left, viewportMargin),
                        Math.max(viewportMargin, viewportWidth - panelWidth - viewportMargin),
                    )

                    // Read the actual content height after the shared popover
                    // layout becomes visible. A fixed 180px threshold cannot
                    // distinguish a short two-filter menu from a four-filter
                    // menu, and a forced 120px minimum can escape the viewport.
                    const contentHeight = panel.scrollHeight + (panel.offsetHeight - panel.clientHeight)
                    const below = Math.max(0, viewportHeight - rect.bottom - panelGap - viewportMargin)
                    const above = Math.max(0, rect.top - panelGap - viewportMargin)
                    const fitsBelow = contentHeight <= below
                    const fitsAbove = contentHeight <= above
                    const openBelow = fitsBelow || (! fitsAbove && below >= above)
                    const availableHeight = openBelow ? below : above

                    if (openBelow) {
                        this.panelStyle = `left:${left}px;top:${rect.bottom + panelGap}px;bottom:auto;width:${panelWidth}px;max-height:${availableHeight}px;visibility:visible;`
                    } else {
                        this.panelStyle = `left:${left}px;top:auto;bottom:${viewportHeight - rect.top + panelGap}px;width:${panelWidth}px;max-height:${availableHeight}px;visibility:visible;`
                    }
                },
                syncPanelToLayout() {
                    if (! this.open) return
                    // The CSS container, not JS width thresholds, determines
                    // whether Filters is a popover. Close on return to inline.
                    if (getComputedStyle(this.$refs.trigger).display === 'none') {
                        this.closePanel()
                        return
                    }
                    this.$nextTick(() => {
                        if (this.open) this.positionPanel()
                    })
                },
            }"
            x-bind:data-open="open ? 'true' : 'false'"
            x-on:keydown.escape.window="closePanel()"
            x-on:resize.window="syncPanelToLayout()"
            x-on:scroll.window="closePanel()"
            x-on:click.outside="closePanel()"
        >
            <button
                class="admin-action admin-action--with-icon admin-filter-overflow__trigger"
                type="button"
                x-ref="trigger"
                x-on:click="togglePanel()"
                x-bind:aria-expanded="open.toString()"
                aria-haspopup="true"
                aria-label="{{ __('Filters') }}"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Filter->mini()" class="admin-action__icon" />
                <span class="admin-action__label">{{ __('Filters') }}</span>
            </button>

            <div
                class="admin-data-controls__filters"
                role="group"
                aria-label="{{ __('Filters') }}"
                x-ref="panel"
                x-bind:style="open && !panelStyle ? 'visibility:hidden;' : panelStyle"
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
