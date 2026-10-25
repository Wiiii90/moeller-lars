@props(['name'])

<svg
    {{ $attributes->class(['public-icon']) }}
    xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @switch($name)
        @case('corner-up-left')
            <path d="M9 14 4 9l5-5M20 20v-7a4 4 0 0 0-4-4H4" />
            @break
        @case('chevron-left')
            <path d="m15 18-6-6 6-6" />
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @case('close')
            <path d="M18 6 6 18M6 6l12 12" />
            @break
        @case('external-link')
            <path d="M14 4h6v6M20 4l-9 9M20 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h6" />
            @break
        @case('arrow-left')
            <path d="M20 12H4m7-7-7 7 7 7" />
            @break
        @case('arrow-right')
            <path d="M4 12h16m-7-7 7 7-7 7" />
            @break
        @case('volume-muted')
            <path d="M11 5 6 9H3v6h3l5 4zM17 9l5 6m0-6-5 6" />
            @break
        @case('volume-on')
            <path d="M11 5 6 9H3v6h3l5 4zM15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13" />
            @break
    @endswitch
</svg>
