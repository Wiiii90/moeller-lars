@props([
    'value',
    'options' => [25, 50, 100],
    'wireModel' => null,
    'wireAction' => null,
    'label' => 'Per page',
    'ariaLabel' => 'Items per page',
])

@php
    $currentValue = (int) $value;
    $sizeOptions = collect($options)->map(static fn (mixed $option): int => (int) $option)->values()->all();
@endphp

<label {{ $attributes->class(['admin-pager__size']) }}>
    <span>{{ $label }}</span>

    <select
        class="admin-inline-select"
        aria-label="{{ $ariaLabel }}"
        @if (is_string($wireAction) && $wireAction !== '')
            wire:change="{{ $wireAction }}($event.target.value)"
        @elseif (is_string($wireModel) && $wireModel !== '')
            wire:model.change="{{ $wireModel }}"
        @endif
    >
        @foreach ($sizeOptions as $sizeOption)
            <option value="{{ $sizeOption }}" @selected($currentValue === $sizeOption)>{{ $sizeOption }}</option>
        @endforeach
    </select>
</label>
