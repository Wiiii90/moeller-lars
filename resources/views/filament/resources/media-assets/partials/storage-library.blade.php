<section class="media-workspace__library admin-visual-stage-followup" aria-label="Media library">
    @php
        $selectableAssetIds = collect($assets)
            ->filter(static fn (array $asset): bool => ($asset['selectable'] ?? false) === true)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $visibleSelectedCount = count(array_intersect($selectableAssetIds, $selectedAssets));
        $allVisibleSelected = $selectableAssetIds !== []
            && $visibleSelectedCount === count($selectableAssetIds);
        $someVisibleSelected = $visibleSelectedCount > 0 && ! $allVisibleSelected;
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        $libraryHasRecords = $total > 0 || \App\Models\MediaAsset::query()
            ->whereIn('mime_type', \App\Domain\Media\MediaTypePolicy::acceptedMimeTypes())
            ->exists();
    @endphp

    <x-admin.controls class="media-workspace__controls" aria-label="File search and filters">
        <x-slot:search>
            <label class="admin-data-field">
                <span>Search media</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filename, ALT, credit…">
            </label>
        </x-slot:search>

        <x-slot:filters>
            <label class="admin-data-field">
                <span>Type</span>
                <select wire:model.live="type">
                    <option value="all">All types</option>
                    <option value="image">All images</option>
                    <option value="video">All video</option>
                    <option value="audio">All audio</option>
                    <option value="image/jpeg">JPEG</option>
                    <option value="image/png">PNG</option>
                    <option value="image/webp">WebP</option>
                    <option value="video/mp4">MP4</option>
                    <option value="video/webm">WebM</option>
                    <option value="audio/mpeg">MP3</option>
                    <option value="audio/mp4">M4A / AAC</option>
                    <option value="audio/ogg">Ogg audio</option>
                    <option value="audio/wav">WAV</option>
                </select>
            </label>

            <label class="admin-data-field">
                <span>Usage</span>
                <select wire:model.live="usage">
                    <option value="all">Any</option>
                    <option value="in-use">In use</option>
                    <option value="unreferenced">Unreferenced</option>
                    @foreach ($usageGroups as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach ($group['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            <label class="admin-data-field">
                <span>Status</span>
                <select wire:model.live="state">
                    <option value="available">Available</option>
                    <option value="all">All states</option>
                    <option value="quarantined">Quarantined</option>
                    <option value="deleted">Deleted</option>
                </select>
            </label>
        </x-slot:filters>

        <x-slot:reset>
            <div class="admin-data-control-group">
                <span class="admin-data-control-label">Filter</span>
                <button class="admin-action" type="button" wire:click="resetFilters">Reset</button>
            </div>
        </x-slot:reset>

        <x-slot:actions>
            <div class="admin-data-control-group media-workspace__view-group">
                <span class="admin-data-control-label">View</span>
                <div class="media-workspace__view-options" role="group" aria-label="Media view">
                    <button
                        class="media-workspace__view-option {{ $viewMode === 'list' ? 'is-active' : '' }}"
                        type="button"
                        wire:click="setViewMode('list')"
                        aria-label="List"
                        title="List"
                        aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                    >
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::ViewList->mini()" class="media-workspace__view-icon" />
                    </button>
                    <button
                        class="media-workspace__view-option {{ $viewMode === 'grid' ? 'is-active' : '' }}"
                        type="button"
                        wire:click="setViewMode('grid')"
                        aria-label="Grid"
                        title="Grid"
                        aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}"
                    >
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::ViewGrid->mini()" class="media-workspace__view-icon" />
                    </button>
                    <button
                        class="media-workspace__view-option {{ $viewMode === 'dense' ? 'is-active' : '' }}"
                        type="button"
                        wire:click="setViewMode('dense')"
                        aria-label="Dense"
                        title="Dense"
                        aria-pressed="{{ $viewMode === 'dense' ? 'true' : 'false' }}"
                    >
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::ViewDense->mini()" class="media-workspace__view-icon" />
                    </button>
                </div>
            </div>
        </x-slot:actions>

        <x-slot:selection>
            <div
                class="admin-data-control-group media-workspace__multi-action"
                x-data="{ open: false }"
                x-on:click.outside="open = false"
                x-on:keydown.escape.window="open = false"
            >
                <span class="admin-data-control-label">Selection</span>
                <div class="media-workspace__multi-action-anchor">
                    <button
                        class="admin-action media-workspace__selection-trigger"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-haspopup="menu"
                        @disabled($selectedAssets === [])
                    >
                        Selected files
                        <span class="media-workspace__selection-count">{{ count($selectedAssets) }}</span>
                    </button>
                    <div class="media-workspace__multi-action-menu" role="menu" x-show="open" x-cloak>
                        <a
                            class="admin-action"
                            role="menuitem"
                            href="{{ route('admin.media.download-selected', ['ids' => $selectedAssets]) }}"
                            x-on:click="open = false"
                        >Download selected</a>
                        <button
                            class="admin-action is-danger"
                            type="button"
                            role="menuitem"
                            wire:click="mountAction('deleteSelected')"
                            x-on:click="open = false"
                        >Delete selected</button>
                    </div>
                </div>
            </div>
        </x-slot:selection>
    </x-admin.controls>

    @if ($assets !== [])
        @if ($viewMode === 'grid')
            <section class="media-workspace__grid" aria-label="Media assets grid">
                @foreach ($assets as $asset)
                    @php($selected = in_array($asset['id'], $selectedAssets, true))
                    @php($displayFilename = pathinfo($asset['filename'], PATHINFO_FILENAME))
                    <article class="media-workspace__grid-item {{ $selected ? 'is-selected' : '' }}" wire:key="media-grid-{{ $asset['id'] }}">
                        <button
                            class="media-workspace__visual"
                            type="button"
                            wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })"
                            aria-label="Preview {{ $asset['filename'] }}"
                        >
                            @if ($asset['thumbnail_url'])
                                <img
                                    src="{{ $asset['thumbnail_url'] }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    @if ($asset['thumbnail_width']) width="{{ $asset['thumbnail_width'] }}" @endif
                                    @if ($asset['thumbnail_height']) height="{{ $asset['thumbnail_height'] }}" @endif
                                >
                            @else
                                @include('filament.resources.media-assets.partials.media-type-placeholder', [
                                    'kind' => $asset['kind'],
                                    'typeLabel' => $asset['type_label'],
                                ])
                            @endif
                            @if ($asset['shared'])
                                <em>Shared</em>
                            @elseif ($asset['usage'] === 0)
                                <em>Unreferenced</em>
                            @endif
                        </button>
                        <div class="media-workspace__grid-meta">
                            <button
                                class="media-workspace__filename-button"
                                type="button"
                                wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })"
                                title="{{ $asset['filename'] }}"
                            ><strong>{{ $displayFilename }}</strong></button>
                            <span>{{ $asset['type_label'] }} · {{ $asset['size'] }}</span>
                            @if ($asset['references'] !== [])
                                <small>{{ $asset['references'][0]['type'] }} — {{ $asset['references'][0]['label'] }}@if ($asset['reference_overflow'] > 0) · +{{ $asset['reference_overflow'] }} more @endif</small>
                            @else
                                <small>Unreferenced</small>
                            @endif
                        </div>
                        <div class="media-workspace__actions media-workspace__grid-actions">
                            <div class="media-workspace__grid-actions-left">
                                <label class="media-workspace__selection-checkbox">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleAssetSelection({{ $asset['id'] }})"
                                        x-bind:checked="$wire.selectedAssets.map(Number).includes({{ $asset['id'] }})"
                                        @disabled(! $asset['selectable'])
                                        aria-label="Toggle selection for {{ $asset['filename'] }}"
                                    >
                                </label>
                                @if ($asset['state'] === 'available')
                                    <a
                                        class="media-workspace__icon-action"
                                        href="{{ route('admin.media.download', ['mediaAsset' => $asset['id']]) }}"
                                        aria-label="Download"
                                        title="Download"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Download->mini()" class="media-workspace__action-icon" />
                                    </a>
                                @else
                                    <button
                                        class="media-workspace__icon-action"
                                        type="button"
                                        aria-label="Download"
                                        title="Download"
                                        disabled
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Download->mini()" class="media-workspace__action-icon" />
                                    </button>
                                @endif
                            </div>
                            <div class="media-workspace__grid-actions-right">
                                <button class="admin-action" type="button" wire:click="mountAction('edit', { asset: {{ $asset['id'] }} })" @disabled(! $asset['editable'])>Edit</button>
                                <button class="admin-action is-danger" type="button" wire:click="mountAction('delete', { asset: {{ $asset['id'] }} })" @disabled(! $asset['deletable'])>Delete</button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <x-admin.table class="media-workspace__table-wrap {{ $viewMode === 'dense' ? 'is-dense' : '' }}">
                <table class="media-workspace__table">
                    <thead>
                        <tr>
                            @if ($viewMode === 'list')
                                <th scope="col" class="media-workspace__thumb-head">Preview</th>
                            @endif
                            <th scope="col">Media</th>
                            <th scope="col">Type</th>
                            <th scope="col">Used in</th>
                            <th scope="col">Status</th>
                            <th scope="col">Size</th>
                            <th scope="col">Actions</th>
                            <th scope="col" class="media-workspace__selection-head media-workspace__selection-head--trailing">
                                <input
                                    type="checkbox"
                                    x-data="{}"
                                    wire:click.prevent="toggleVisibleSelection"
                                    x-effect="
                                        const visibleIds = @js($selectableAssetIds);
                                        const selectedIds = $wire.selectedAssets.map(Number);
                                        const selectedCount = visibleIds.filter((id) => selectedIds.includes(id)).length;
                                        $el.checked = visibleIds.length > 0 && selectedCount === visibleIds.length;
                                        $el.indeterminate = selectedCount > 0 && selectedCount < visibleIds.length;
                                        $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                                    "
                                    @disabled($selectableAssetIds === [])
                                    aria-label="Toggle selection for visible files"
                                >
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            @php($selected = in_array($asset['id'], $selectedAssets, true))
                            @php($displayFilename = pathinfo($asset['filename'], PATHINFO_FILENAME))
                            <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="media-row-{{ $asset['id'] }}">
                                @if ($viewMode === 'list')
                                    <td class="media-workspace__thumb">
                                        <button
                                            type="button"
                                            wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })"
                                            aria-label="Preview {{ $asset['filename'] }}"
                                        >
                                            @if ($asset['thumbnail_url'])
                                                <img src="{{ $asset['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                            @else
                                                @include('filament.resources.media-assets.partials.media-type-placeholder', [
                                                    'kind' => $asset['kind'],
                                                    'typeLabel' => $asset['type_label'],
                                                ])
                                            @endif
                                        </button>
                                    </td>
                                @endif
                                <td class="media-workspace__identity">
                                    <button
                                        class="media-workspace__filename-button"
                                        type="button"
                                        wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })"
                                        title="{{ $asset['filename'] }}"
                                    ><strong>{{ $displayFilename }}</strong></button>
                                    <small>
                                        @if ($asset['credit'] !== ''){{ $asset['credit'] }} · @endif
                                        {{ $asset['created'] }}
                                        @if ($asset['alt_missing']) · ALT missing @endif
                                    </small>
                                </td>
                                <td>
                                    <strong class="media-workspace__type">{{ $asset['type_label'] }}</strong>
                                    @if ($asset['dimensions'])<small>{{ $asset['dimensions'] }}</small>@endif
                                </td>
                                <td>
                                    @if ($asset['references'] === [])
                                        <span class="media-workspace__unreferenced">Unreferenced</span>
                                    @else
                                        <div class="media-workspace__references">
                                            @foreach ($asset['references'] as $reference)
                                                <span>
                                                    <strong>{{ $reference['type'] }}</strong>
                                                    <small>{{ $reference['label'] }}</small>
                                                </span>
                                            @endforeach
                                            @if ($asset['reference_overflow'] > 0)
                                                <em>+{{ $asset['reference_overflow'] }} more</em>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="media-workspace__state is-{{ $asset['state'] }}">{{ ucfirst($asset['state']) }}</span>
                                </td>
                                <td class="media-workspace__size">{{ $asset['size'] }}</td>
                                <td class="media-workspace__actions">
                                    <div class="admin-row-actions admin-row-actions--canonical admin-toolbar">
                                        <button class="admin-action" type="button" wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })">
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Details->mini()" class="media-workspace__action-icon" />
                                            Details
                                        </button>
                                        <button class="admin-action" type="button" wire:click="mountAction('edit', { asset: {{ $asset['id'] }} })" @disabled(! $asset['editable'])>
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="media-workspace__action-icon" />
                                            Edit
                                        </button>
                                        @if ($asset['state'] === 'available')
                                            <a class="admin-action" href="{{ route('admin.media.download', ['mediaAsset' => $asset['id']]) }}">
                                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Download->mini()" class="media-workspace__action-icon" />
                                                Download
                                            </a>
                                        @else
                                            <button class="admin-action" type="button" disabled>
                                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Download->mini()" class="media-workspace__action-icon" />
                                                Download
                                            </button>
                                        @endif
                                        <button class="admin-action is-danger" type="button" wire:click="mountAction('delete', { asset: {{ $asset['id'] }} })" @disabled(! $asset['deletable'])>
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="media-workspace__action-icon" />
                                            Delete
                                        </button>
                                    </div>
                                </td>
                                <td class="media-workspace__selection-cell media-workspace__selection-cell--trailing">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleAssetSelection({{ $asset['id'] }})"
                                        x-bind:checked="$wire.selectedAssets.map(Number).includes({{ $asset['id'] }})"
                                        @disabled(! $asset['selectable'])
                                        aria-label="Toggle selection for {{ $asset['filename'] }}"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table>
        @endif
    @elseif ($libraryHasRecords)
        <x-admin.empty-state title="No matching files">
            <p>Adjust the search or filters.</p>
            <x-slot:actions>
                <button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button>
            </x-slot:actions>
        </x-admin.empty-state>
    @else
        <x-admin.empty-state title="No media files yet">
            <p>Upload a supported file to start the library.</p>
        </x-admin.empty-state>
    @endif

    <x-admin.add-row
        class="admin-add-row--compact"
        type="button"
        x-on:click="document.getElementById('storage-upload')?.click()"
        aria-controls="storage-upload"
    >Add Media File</x-admin.add-row>

    <footer class="media-workspace__pager">
        <label class="media-workspace__pager-size">
            <span>Per page</span>
            <select wire:model.live.number="pageSize">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </label>

        <span class="media-workspace__pager-range">
            @if ($total === 0)
                0 of 0
            @else
                {{ $resultStart }}–{{ $resultEnd }} of {{ $total }}
            @endif
        </span>

        <div class="media-workspace__pager-actions admin-toolbar">
            <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
            <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
        </div>
    </footer>
</section>
