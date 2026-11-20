@props([
    'viewMode' => 'list',
    'label' => 'View options',
])

@php
    $modes = [
        ['value' => 'list', 'label' => 'List', 'icon' => \App\Filament\Support\AdminIcon::ViewList],
        ['value' => 'grid', 'label' => 'Grid', 'icon' => \App\Filament\Support\AdminIcon::ViewGrid],
        ['value' => 'dense', 'label' => 'Dense', 'icon' => \App\Filament\Support\AdminIcon::ViewDense],
    ];
@endphp

<div {{ $attributes->class(['media-workspace__view-options', 'admin-editorial-actions']) }}
     role="group" aria-label="{{ __($label) }}">
    @foreach ($modes as $mode)
        <button
            class="admin-action media-workspace__view-option {{ $viewMode === $mode['value'] ? 'is-active' : '' }}"
            type="button"
            wire:click="setViewMode('{{ $mode['value'] }}')"
            aria-label="{{ __($mode['label']) }}"
            title="{{ __($mode['label']) }}"
            aria-pressed="{{ $viewMode === $mode['value'] ? 'true' : 'false' }}"
        >
            <x-filament::icon :icon="$mode['icon']->mini()" class="media-workspace__view-icon" />
            <span class="admin-action__label">{{ __($mode['label']) }}</span>
        </button>
    @endforeach
</div>
