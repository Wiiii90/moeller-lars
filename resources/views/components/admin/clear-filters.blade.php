<button type="button" {{ $attributes->class(['admin-action', 'admin-action--with-icon']) }}>
    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" class="admin-action__icon" />
    <span class="admin-action__label">Clear</span>
</button>
