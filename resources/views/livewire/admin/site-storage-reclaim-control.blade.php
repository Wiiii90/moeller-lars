<div class="admin-storage__reclaim-control">
    <button
        class="admin-action admin-action--with-icon is-danger"
        type="button"
        wire:click="mountAction('freeStorage')"
        aria-label="Free storage"
        title="Frees quota by clearing Undo history, old restore snapshots and rebuildable thumbnails."
    >
        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::ReclaimStorage->mini()" class="admin-action__icon" />
        <span class="admin-action__label">Free storage</span>
    </button>

    <x-filament-actions::modals />
</div>
