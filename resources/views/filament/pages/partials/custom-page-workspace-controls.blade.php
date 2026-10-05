        <x-admin.controls class="admin-data-controls--content-minimum custom-page-workspace__controls" aria-label="Component table tools">
            <x-slot:search>
                <label class="admin-data-field custom-page-workspace__search">
                    <span>Search</span>
                    <x-admin.search-input model="componentSearch" placeholder="Search components and entries" />
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
                    <x-admin.clear-filters wire:click="resetComponentFilters" />
                </div>
            </x-slot:reset>

            <x-slot:actions>
                <div class="admin-data-control-group custom-page-workspace__page">
                    <span class="admin-data-control-label">Custom Page</span>
                    <div class="admin-toolbar admin-editorial-actions custom-page-workspace__page-actions">
                        <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('pageSettings')" aria-label="Custom Page settings">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                            <span class="admin-action__label">Settings</span>
                        </button>
                        <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addComponent')" aria-label="Add component">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::CustomPage->mini()" class="admin-action__icon" />
                            <span class="admin-action__label">Add component</span>
                        </button>
                        @if ($previewUrl)
                            <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener" aria-label="Preview Custom Page">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Preview</span>
                            </a>
                        @else
                            <button class="admin-action admin-action--with-icon" type="button" disabled aria-label="Preview Custom Page">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Preview</span>
                            </button>
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
                            <x-admin.selection-trigger-label>Selected</x-admin.selection-trigger-label>
                        </button>
                        <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('up')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected up</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelected('down')" x-on:click="open = false" @disabled(! $canMoveSelected)>Move selected down</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="publishSelected" x-on:click="open = false" @disabled(! $canPublishSelected)>Publish selected</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelected" x-on:click="open = false" @disabled(! $canUnpublishSelected)>Unpublish selected</button>
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelected')" x-on:click="open = false" @disabled(! $canDeleteSelected)>Delete selected</button>
                        </div>
                    </div>
                    <span class="admin-selection__count" aria-label="{{ $selectedItemCount }} selected">{{ $selectedItemCount }}</span>
                </div>
            </x-slot:selection>
        </x-admin.controls>
