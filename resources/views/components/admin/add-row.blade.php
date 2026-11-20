@props([
    'type' => 'button',
    'icon' => null,
])

<button type="{{ $type }}" {{ $attributes->class(['admin-add-row']) }}>
    <span class="admin-add-row__mark" aria-hidden="true">
        <x-filament::icon :icon="($icon instanceof \App\Filament\Support\AdminIcon ? $icon : \App\Filament\Support\AdminIcon::Add)->mini()" />
    </span>
    <span>{{ $slot }}</span>
</button>
