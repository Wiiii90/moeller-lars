@props([
    'type' => 'button',
    'icon' => null,
])

<button type="{{ $type }}" {{ $attributes->class(['admin-add-row']) }}>
    <span class="admin-add-row__mark" aria-hidden="true">
        @if ($icon instanceof \App\Filament\Support\AdminIcon)
            <x-filament::icon :icon="$icon->mini()" />
        @else
            +
        @endif
    </span>
    <span>{{ $slot }}</span>
</button>
