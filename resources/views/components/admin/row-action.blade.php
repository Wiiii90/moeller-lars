@props([
    'action',
    'label' => null,
    'href' => null,
    'disabled' => false,
    'iconOnly' => false,
])

@php
    $rowAction = $action instanceof \App\Filament\Support\AdminRowAction
        ? $action
        : \App\Filament\Support\AdminRowAction::from((string) $action);
    $resolvedLabel = __($label ?? $rowAction->label());
    $actionAttributes = $attributes->merge([
        'title' => $resolvedLabel,
        'aria-label' => $resolvedLabel,
    ]);
    $classes = [
        'admin-action',
        'admin-action--with-icon',
        'admin-order-action' => $rowAction->isOrderAction(),
        'admin-order-action--labeled' => $rowAction->isOrderAction() && ! $iconOnly,
        'admin-action--icon-only' => $iconOnly,
        'admin-action--state' => $rowAction->isStateAction(),
        'is-danger' => $rowAction->isDangerAction(),
    ];
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $actionAttributes->class($classes) }}>
        <x-filament::icon :icon="$rowAction->icon()->mini()" :size="\Filament\Support\Enums\IconSize::Small" class="admin-action__icon" />
        @unless ($iconOnly)
            <span class="admin-action__label">{{ $resolvedLabel }}</span>
        @endunless
    </a>
@else
    <button type="button" {{ $actionAttributes->class($classes) }} @disabled($disabled)>
        <x-filament::icon :icon="$rowAction->icon()->mini()" :size="\Filament\Support\Enums\IconSize::Small" class="admin-action__icon" />
        @unless ($iconOnly)
            <span class="admin-action__label">{{ $resolvedLabel }}</span>
        @endunless
    </button>
@endif
