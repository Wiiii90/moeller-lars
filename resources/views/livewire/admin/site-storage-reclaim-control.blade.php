<div class="admin-storage__reclaim-control">
    <button
        class="admin-action is-danger"
        type="button"
        wire:click="mountAction('freeStorage')"
    >
        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::ReclaimStorage->mini()" class="admin-action__icon" />
        <span class="admin-action__label">Free storage now</span>
    </button>

    <x-filament-actions::modals />
</div>
