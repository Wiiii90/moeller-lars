@props([
    'start' => 0,
    'end' => 0,
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'pageSize',
    'pageSizeOptions' => [25, 50, 100],
    'pageSizeWireModel' => null,
    'pageSizeWireAction' => null,
    'pageSizeUrls' => [],
    'pageSizeLabel' => 'Per page',
    'pageSizeAriaLabel' => 'Items per page',
    'previousWireAction' => null,
    'nextWireAction' => null,
    'previousUrl' => null,
    'nextUrl' => null,
    'ariaLabel' => 'Pagination',
])

@php
    $currentPage = max(1, (int) $page);
    $lastPage = max(1, (int) $pages);
    $previousDisabled = $currentPage <= 1;
    $nextDisabled = $currentPage >= $lastPage;
    $hasPreviousUrl = is_string($previousUrl) && $previousUrl !== '';
    $hasNextUrl = is_string($nextUrl) && $nextUrl !== '';
    $hasPreviousAction = is_string($previousWireAction) && $previousWireAction !== '';
    $hasNextAction = is_string($nextWireAction) && $nextWireAction !== '';
@endphp

<footer {{ $attributes->class(['admin-pager']) }} aria-label="{{ $ariaLabel }}">
    <x-admin.page-size-picker
        :value="$pageSize"
        :options="$pageSizeOptions"
        :wire-model="$pageSizeWireModel"
        :wire-action="$pageSizeWireAction"
        :urls="$pageSizeUrls"
        :label="$pageSizeLabel"
        :aria-label="$pageSizeAriaLabel"
    />

    <span class="admin-pager__range">
        @if ((int) $total === 0)
            0 of 0
        @else
            {{ (int) $start }}–{{ (int) $end }} of {{ (int) $total }}
        @endif
    </span>

    <div class="admin-pager__actions admin-toolbar">
        @if (! $previousDisabled && $hasPreviousUrl)
            <a class="admin-action" href="{{ $previousUrl }}">Previous</a>
        @else
            <button
                class="admin-action"
                type="button"
                @if (! $previousDisabled && $hasPreviousAction)
                    wire:click="{{ $previousWireAction }}"
                @endif
                @disabled($previousDisabled || (! $hasPreviousAction && ! $hasPreviousUrl))
            >Previous</button>
        @endif

        @if (! $nextDisabled && $hasNextUrl)
            <a class="admin-action" href="{{ $nextUrl }}">Next</a>
        @else
            <button
                class="admin-action"
                type="button"
                @if (! $nextDisabled && $hasNextAction)
                    wire:click="{{ $nextWireAction }}"
                @endif
                @disabled($nextDisabled || (! $hasNextAction && ! $hasNextUrl))
            >Next</button>
        @endif
    </div>
</footer>
