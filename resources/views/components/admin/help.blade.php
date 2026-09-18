@php
    $tooltipId = 'admin-help-'.substr(hash('sha256', $label.'|'.$text), 0, 12);
@endphp

<span
    class="admin-help"
    x-data="{ open: false }"
    x-on:mouseenter="open = true"
    x-on:mouseleave="open = false"
    x-on:focusin="open = true"
    x-on:focusout="open = false"
>
    <button
        type="button"
        class="admin-help__trigger"
        aria-label="{{ $label }}"
        aria-describedby="{{ $tooltipId }}"
        aria-controls="{{ $tooltipId }}"
        x-bind:aria-expanded="open.toString()"
        x-on:click.stop="open = ! open"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 18h6M10 22h4M8.4 14.4A6 6 0 1 1 15.6 14.4C14.6 15.2 14 16 14 17h-4c0-1-.6-1.8-1.6-2.6Z" />
        </svg>
    </button>
    <span
        id="{{ $tooltipId }}"
        class="admin-help__popover"
        role="tooltip"
        x-cloak
        x-show="open"
    >{{ $text }}</span>
</span>
