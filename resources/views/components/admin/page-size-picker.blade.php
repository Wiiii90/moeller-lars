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
@endphp

<div
    {{ $attributes->class(['admin-pager__size']) }}
    x-data="{ open: false }"
    x-bind:class="{ 'is-open': open }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
>
    <span>{{ $label }}</span>
    <div class="admin-pager-size-picker">
        <button
            class="admin-pager-size-picker__trigger"
            type="button"
            x-on:click="
                open = ! open;
                if (open) {
                    $nextTick(() => $refs.menu?.scrollIntoView({ block: 'nearest' }));
                }
            "
            x-bind:aria-expanded="open.toString()"
            aria-haspopup="listbox"
            aria-label="{{ $ariaLabel }}"
        >
            <span>{{ $currentValue }}</span>
            <span aria-hidden="true">▾</span>
        </button>

        <div
            class="admin-pager-size-picker__menu"
            role="listbox"
            aria-label="{{ $ariaLabel }}"
            x-ref="menu"
            x-show="open"
            x-cloak
        >
            @foreach ($sizeOptions as $sizeOption)
                @php
                    $optionUrl = is_array($urls) && isset($urls[$sizeOption]) ? (string) $urls[$sizeOption] : null;
                @endphp

                @if (is_string($optionUrl) && $optionUrl !== '')
                    <a
                        class="admin-pager-size-picker__option {{ $currentValue === $sizeOption ? 'is-active' : '' }}"
                        href="{{ $optionUrl }}"
                        role="option"
                        aria-selected="{{ $currentValue === $sizeOption ? 'true' : 'false' }}"
                        x-on:click="open = false"
                    >
                        <span class="admin-pager-size-picker__check" aria-hidden="true">{{ $currentValue === $sizeOption ? '✓' : '' }}</span>
                        <span>{{ $sizeOption }}</span>
                    </a>
                @else
                    <button
                        class="admin-pager-size-picker__option {{ $currentValue === $sizeOption ? 'is-active' : '' }}"
                        type="button"
                        role="option"
                        aria-selected="{{ $currentValue === $sizeOption ? 'true' : 'false' }}"
                        @if ($wireAction)
                            wire:click="{{ $wireAction }}({{ $sizeOption }})"
                        @elseif ($wireModel)
                            wire:click="$set('{{ $wireModel }}', {{ $sizeOption }})"
                        @endif
                        x-on:click="open = false"
                    >
                        <span class="admin-pager-size-picker__check" aria-hidden="true">{{ $currentValue === $sizeOption ? '✓' : '' }}</span>
                        <span>{{ $sizeOption }}</span>
                    </button>
                @endif
            @endforeach
        </div>
    </div>
</div>
