<div
    class="admin-sidebar-close-shell"
    x-cloak
    x-data="{}"
    x-show="$store.sidebar.isOpen"
>
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-x-mark"
        label="Close navigation"
        title="Close navigation"
        aria-controls="fi-main-sidebar"
        x-bind:aria-expanded="$store.sidebar.isOpen"
        x-on:click="$store.sidebar.close()"
        class="admin-sidebar-close-button"
    />
</div>
