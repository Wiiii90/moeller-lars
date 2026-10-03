<span {{ $attributes->class(['admin-selection__trigger-label']) }}>
    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Selection->mini()" class="admin-action__icon admin-selection__trigger-icon" />
    <span class="admin-selection__trigger-text">{{ $slot }}</span>
</span>
