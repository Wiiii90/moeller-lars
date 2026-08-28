@props([
    'ariaLabel' => null,
])

<div {{ $attributes->class('admin-data-controls') }} @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
    @isset($search)
        {{ $search }}
    @endisset

    @isset($filters)
        {{ $filters }}
    @endisset

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
