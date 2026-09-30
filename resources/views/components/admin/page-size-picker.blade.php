@props([
    'value',
    'options' => [25, 50, 100],
    'wireModel' => null,
    'wireAction' => null,
    'urls' => [],
    'label' => 'Per page',
    'ariaLabel' => 'Items per page',
])

@php
    $currentValue = (int) $value;
    $sizeOptions = collect($options)->map(static fn (mixed $option): int => (int) $option)->values()->all();
    $usesUrls = is_array($urls) && $urls !== [];
@endphp

<label {{ $attributes->class(['admin-pager__size']) }}>
    <span>{{ $label }}</span>

    <select
        class="admin-inline-select"
        aria-label="{{ $ariaLabel }}"
        @if ($usesUrls)
            x-on:change="
                const url = $event.target.selectedOptions[0]?.dataset.url;
                if (url) window.location.assign(url);
            "
        @elseif (is_string($wireAction) && $wireAction !== '')
            wire:change="{{ $wireAction }}($event.target.value)"
        @elseif (is_string($wireModel) && $wireModel !== '')
            wire:model.change="{{ $wireModel }}"
        @endif
    >
        @foreach ($sizeOptions as $sizeOption)
            @php
                $optionUrl = $usesUrls && isset($urls[$sizeOption]) ? (string) $urls[$sizeOption] : '';
            @endphp

            <option
                value="{{ $sizeOption }}"
                @if ($optionUrl !== '') data-url="{{ $optionUrl }}" @endif
                @selected($currentValue === $sizeOption)
            >{{ $sizeOption }}</option>
        @endforeach
    </select>
</label>
