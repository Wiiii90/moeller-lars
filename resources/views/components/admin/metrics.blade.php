@props([
    'columns' => null,
])

<div
    {{ $attributes->class(['admin-metrics']) }}
    @if ($columns !== null) style="--admin-metric-columns: {{ (int) $columns }}" @endif
>
    {{ $slot }}
</div>
