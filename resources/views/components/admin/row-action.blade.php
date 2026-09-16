@props([
    'action',
    'label' => null,
    'href' => null,
])

@php
    $rowAction = $action instanceof \App\Filament\Support\AdminRowAction
        ? $action
        : \App\Filament\Support\AdminRowAction::from((string) $action);
    $resolvedLabel = $label ?? $rowAction->label();
    $classes = [
        'admin-action',
        'admin-action--with-icon',
        'admin-order-action' => $rowAction->isOrderAction(),
        'admin-order-action--labeled' => $rowAction->isOrderAction(),
        'admin-action--state' => $rowAction->isStateAction(),
        'is-danger' => $rowAction->isDangerAction(),
    ];
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        <x-filament::icon :icon="$rowAction->icon()->mini()" class="admin-action__icon" />
        <span class="admin-action__label">{{ $resolvedLabel }}</span>
    </a>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        <x-filament::icon :icon="$rowAction->icon()->mini()" class="admin-action__icon" />
        <span class="admin-action__label">{{ $resolvedLabel }}</span>
    </button>
@endif
