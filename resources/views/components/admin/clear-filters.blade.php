@props(['href' => null, 'disabled' => false])

@if ($href !== null && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class(['admin-action', 'admin-action--with-icon']) }}>
        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" class="admin-action__icon" />
        <span class="admin-action__label">Clear</span>
    </a>
@else
    <button type="button" {{ $attributes->except('wire:navigate')->class(['admin-action', 'admin-action--with-icon']) }} @disabled($disabled)>
        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" class="admin-action__icon" />
        <span class="admin-action__label">Clear</span>
    </button>
@endif
