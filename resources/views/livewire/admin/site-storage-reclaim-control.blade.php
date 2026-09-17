<div class="admin-storage__attention-row">
    <span>Recovery storage</span>
    <button
        class="admin-action is-danger"
        type="button"
        wire:click="mountAction('freeStorage')"
    >Free storage now</button>

    <x-filament-actions::modals />
</div>
