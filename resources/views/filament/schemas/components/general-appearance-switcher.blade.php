<x-admin.stage-switcher
    storage-key="admin-general.appearance"
    default-pane="global"
    :panes="['global' => 'Global', 'title' => 'Title', 'menu' => 'Menu', 'page' => 'Page']"
    aria-label="Appearance"
    external-state="appearanceTab"
    x-on:click="if ($event.target.closest('[data-admin-stage-slot]')) { const scroll = $el.closest('.general-appearance-stage')?.querySelector('.general-appearance-stage__scroll'); if (scroll) scroll.scrollTop = 0 }"
>
    <x-slot:tools>
        <button
            class="admin-action admin-stage-tabs__tool"
            type="button"
            title="More space"
            aria-label="More space"
            x-on:click="moreSpace = ! moreSpace"
            x-bind:title="moreSpace ? 'Less space' : 'More space'"
            x-bind:aria-label="moreSpace ? 'Less space' : 'More space'"
            x-bind:aria-pressed="moreSpace.toString()"
        >
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" />
        </button>
    </x-slot:tools>
</x-admin.stage-switcher>
