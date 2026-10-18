<div class="general-appearance-switcher">
    <x-admin.stage-switcher
        storage-key="admin-general.appearance"
        default-pane="global"
        :panes="['global' => 'Global', 'title' => 'Title', 'menu' => 'Menu', 'page' => 'Page']"
        aria-label="Appearance"
        external-state="appearanceTab"
    />

    <span class="general-appearance-switcher__divider" aria-hidden="true"></span>

    <button
        class="general-live-preview__mode general-appearance-switcher__expand"
        type="button"
        title="More space"
        aria-label="More space"
        x-on:click="moreSpace = ! moreSpace"
        x-bind:class="{ 'is-active': moreSpace }"
        x-bind:title="moreSpace ? 'Less space' : 'More space'"
        x-bind:aria-label="moreSpace ? 'Less space' : 'More space'"
        x-bind:aria-pressed="moreSpace.toString()"
    >
        <span x-show="! moreSpace">
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" />
        </span>
        <span x-show="moreSpace" x-cloak>
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" />
        </span>
    </button>
</div>
