        <div class="custom-page-workspace__controls" aria-label="Component table tools">
            <label class="custom-page-workspace__field custom-page-workspace__search">
                <span>Search</span>
                <input type="search" wire:model.live.debounce.300ms="componentSearch" placeholder="Search components and entries">
            </label>

            <label class="custom-page-workspace__field">
                <span>Type</span>
                <select wire:model.live="componentType">
                    <option value="any">All components</option>
                    @foreach ($componentTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="custom-page-workspace__control-group">
                <span class="custom-page-workspace__control-label">Filter</span>
                <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
            </div>

            <div class="custom-page-workspace__control-group custom-page-workspace__page">
                <span class="custom-page-workspace__control-label">Custom Page</span>
                <div class="admin-toolbar custom-page-workspace__page-actions">
                    <button class="admin-action" type="button" wire:click="mountAction('pageSettings')">Settings</button>
                    <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                    @if ($previewUrl)
                        <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                    @else
                        <button class="admin-action" type="button" disabled>Preview</button>
                    @endif
                </div>
            </div>

            <div
                class="custom-page-workspace__control-group custom-page-workspace__selection"
                x-data="{ open: false }"
                x-on:click.outside="open = false"
                x-on:keydown.escape.window="open = false"
            >
                <span class="custom-page-workspace__control-label">Selection</span>
                <div class="custom-page-workspace__selection-anchor">
                    <button
                        class="admin-action custom-page-workspace__selection-trigger"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-haspopup="menu"
                        @disabled($selectedItemCount === 0)
                    >
                        Selected items
                        <span class="custom-page-workspace__selection-count">{{ $selectedItemCount }}</span>
                    </button>
                    <div class="custom-page-workspace__selection-menu" role="menu" x-show="open" x-cloak>
                        <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('up')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected up</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('down')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected down</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="publishSelected" x-on:click="open = false" @disabled(! $canPublishSelected)>Publish selected</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelected" x-on:click="open = false" @disabled(! $canUnpublishSelected)>Unpublish selected</button>
                        <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelected')" x-on:click="open = false" @disabled(! $canDeleteSelected)>Delete selected</button>
                    </div>
                </div>
            </div>
        </div>
