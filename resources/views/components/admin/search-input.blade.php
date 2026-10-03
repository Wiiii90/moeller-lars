@props(['model'])

<input
    type="search"
    wire:model.live.debounce.300ms="{{ $model }}"
    x-on:keydown.enter.prevent="void 0"
    {{ $attributes->merge(['autocomplete' => 'off']) }}
>
