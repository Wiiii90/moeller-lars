@props([
    'viewMode' => 'list',
    'label' => 'View options',
    'storageKey' => null,
])

@php
    $modes = [
        ['value' => 'list', 'label' => 'List', 'icon' => \App\Filament\Support\AdminIcon::ViewList],
        ['value' => 'grid', 'label' => 'Grid', 'icon' => \App\Filament\Support\AdminIcon::ViewGrid],
        ['value' => 'dense', 'label' => 'Dense', 'icon' => \App\Filament\Support\AdminIcon::ViewDense],
    ];
@endphp

<div {{ $attributes->class(['media-workspace__view-options', 'admin-editorial-actions']) }}
     role="group" aria-label="{{ __($label) }}"
     x-init="
         const key = @js($storageKey)
         if (key) {
             try {
                 const saved = window.sessionStorage.getItem(key)
                 if (['list', 'grid', 'dense'].includes(saved)) viewMode = saved
                 $watch('viewMode', next => {
                     if (['list', 'grid', 'dense'].includes(next)) {
                         try { window.sessionStorage.setItem(key, next) } catch (_) {}
                     }
                 })
             } catch (_) {}
         }
     ">
    @foreach ($modes as $mode)
        <button
            class="admin-action media-workspace__view-option"
            type="button"
            x-on:click="viewMode = @js($mode['value'])"
            x-bind:class="{ 'is-active': viewMode === @js($mode['value']) }"
            x-bind:aria-pressed="(viewMode === @js($mode['value'])).toString()"
            aria-label="{{ __($mode['label']) }}"
            title="{{ __($mode['label']) }}"
            aria-pressed="{{ $viewMode === $mode['value'] ? 'true' : 'false' }}"
        >
            <x-filament::icon :icon="$mode['icon']->mini()" class="media-workspace__view-icon" />
            <span class="admin-action__label">{{ __($mode['label']) }}</span>
        </button>
    @endforeach
</div>
