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

<div
    {{ $attributes->class(['admin-pager__size']) }}
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
>
    <span>{{ $label }}</span>

    <div class="admin-pager__size-control">
        <button
            class="admin-pager__size-trigger"
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open.toString()"
            aria-haspopup="listbox"
            aria-label="{{ $ariaLabel }}"
        >
            <span>{{ $currentValue }}</span>
        </button>

        <div
            class="admin-pager__size-menu"
            role="listbox"
            aria-label="{{ $ariaLabel }}"
            x-cloak
            x-show="open"
            x-on:click.outside="open = false"
        >
            @foreach ($sizeOptions as $sizeOption)
                <button
                    class="admin-pager__size-option {{ $currentValue === $sizeOption ? 'is-selected' : '' }}"
                    type="button"
                    role="option"
                    aria-selected="{{ $currentValue === $sizeOption ? 'true' : 'false' }}"
                    x-on:click="open = false"
                    @if (is_string($wireAction) && $wireAction !== '')
                        wire:click="{{ $wireAction }}({{ $sizeOption }})"
                    @elseif (is_string($wireModel) && $wireModel !== '')
                        wire:click="$set('{{ $wireModel }}', {{ $sizeOption }})"
                    @endif
                >{{ $sizeOption }}</button>
            @endforeach
        </div>
    </div>
</div>
