        <x-admin.controls class="custom-page-workspace__controls" aria-label="Component table tools">
            <x-slot:search>
                <label class="admin-data-field custom-page-workspace__search">
                    <span>Search</span>
                    <input type="search" wire:model.live.debounce.300ms="componentSearch" placeholder="Search components and entries">
                </label>
            </x-slot:search>

            <x-slot:filters>
                <label class="admin-data-field">
                    <span>Type</span>
                    <select wire:model.live="componentType">
                        <option value="any">All components</option>
                        @foreach ($componentTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </x-slot:filters>

            <x-slot:reset>
                <div class="admin-data-control-group">
                    <span class="admin-data-control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                </div>
            </x-slot:reset>

            <x-slot:actions>
                <div class="admin-data-control-group custom-page-workspace__page">
                    <span class="admin-data-control-label">Custom Page</span>
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
            </x-slot:actions>

            <x-slot:selection>
                <div
                    class="admin-data-control-group admin-selection custom-page-workspace__selection"
                    x-data="{ open: false }"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.window="open = false"
                >
                    <span class="admin-data-control-label">Selection</span>
                    <div class="admin-selection__anchor">
                        <button
                            class="admin-action admin-selection__trigger"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            @disabled($selectedItemCount === 0)
                        >
                            Selected items
                            <span class="admin-selection__count">{{ $selectedItemCount }}</span>
                        </button>
                        <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('up')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected up</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('down')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected down</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="publishSelected" x-on:click="open = false" @disabled(! $canPublishSelected)>Publish selected</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelected" x-on:click="open = false" @disabled(! $canUnpublishSelected)>Unpublish selected</button>
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelected')" x-on:click="open = false" @disabled(! $canDeleteSelected)>Delete selected</button>
                        </div>
                    </div>
                </div>
            </x-slot:selection>
        </x-admin.controls>
