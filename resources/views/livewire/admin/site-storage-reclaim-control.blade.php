<div class="admin-storage__reclaim-control">
    <button
        class="admin-action is-danger"
        type="button"
        wire:click="mountAction('freeStorage')"
    >Free storage now</button>

    <x-filament-actions::modals />
</div>
