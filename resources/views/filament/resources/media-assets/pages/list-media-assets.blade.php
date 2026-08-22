<x-filament-panels::page>
    <x-admin.workspace title="Media Files" class="media-workspace">
        <x-admin.metrics :columns="6" aria-label="Media library">
            <x-admin.metric label="Files" :value="number_format($libraryFiles)">Available</x-admin.metric>
            <x-admin.metric label="Images" :value="number_format($libraryImages)">Available images</x-admin.metric>
            <x-admin.metric label="Videos" :value="number_format($libraryVideos)">Available videos</x-admin.metric>
            <x-admin.metric label="Audio" :value="number_format($libraryAudio)">Available audio</x-admin.metric>
            <x-admin.metric label="Unreferenced" :value="number_format($libraryUnreferenced)">No canonical consumer</x-admin.metric>
            <x-admin.metric label="Library size" :value="$librarySize">Available originals</x-admin.metric>
        </x-admin.metrics>

        <x-admin.section class="media-workspace__upload-section" aria-label="Upload media">
            <div
                class="media-workspace__dropzone"
                x-data="{ uploading: false, progress: 0 }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false; progress = 100"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
            >
                <input
                    class="media-workspace__file-input"
                    type="file"
                    wire:model="directMedia"
                    accept="{{ implode(',', \App\Domain\Media\MediaTypePolicy::uploadAcceptedMimeTypes()) }}"
                    aria-label="Upload a file"
                >
                <div class="media-workspace__dropzone-copy">
                    <strong>Drop a file here or choose from your device</strong>
                    <span>JPEG, PNG, WebP, H.264 MP4, VP8/VP9/AV1 WebM, MP3, M4A/AAC, Ogg audio, or WAV.</span>
                </div>
                <div class="media-workspace__upload-progress" x-show="uploading" x-cloak>
                    <progress max="100" x-bind:value="progress"></progress>
                    <span x-text="`${progress}%`"></span>
                </div>
            </div>

            @error('directMedia')
                <p class="media-workspace__upload-message is-error">{{ $message }}</p>
            @enderror
            @if ($directUploadMessage !== null)
                <p class="media-workspace__upload-message is-success">{{ $directUploadMessage }}</p>
            @endif
        </x-admin.section>

        <x-admin.section class="media-workspace__library" aria-label="Media library">
            <div class="media-workspace__controls" aria-label="File search and filters" style="grid-template-columns: minmax(15rem, 2fr) minmax(8rem, .8fr) minmax(12rem, 1.2fr) minmax(9rem, .8fr) auto;">
                <label class="media-workspace__field media-workspace__search">
                    <span>Search media</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filename, ALT, credit, copyright or MIME">
                </label>

                <label class="media-workspace__field">
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

                <label class="media-workspace__field">
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

                <label class="media-workspace__field">
                    <span>Status</span>
                    <select wire:model.live="state">
                        <option value="available">Available</option>
                        <option value="all">All states</option>
                        <option value="quarantined">Quarantined</option>
                        <option value="deleted">Deleted</option>
                    </select>
                </label>

                <button class="admin-action media-workspace__reset" type="button" wire:click="resetFilters">Reset</button>
            </div>

            <div class="media-workspace__library-bar">
                <div class="media-workspace__result-context">
                    <strong>{{ number_format($total) }} {{ $total === 1 ? 'file' : 'files' }}</strong>
                    <span>Available means validated and reusable, not necessarily publicly published.</span>
                </div>
                <div class="media-workspace__view-switcher admin-toolbar" role="group" aria-label="Media view mode">
                    <button class="admin-action {{ $viewMode === 'list' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('list')">List</button>
                    <button class="admin-action {{ $viewMode === 'grid' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('grid')">Grid</button>
                    <button class="admin-action {{ $viewMode === 'dense' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('dense')">Dense</button>
                </div>
            </div>

            @if ($assets !== [])
                @if ($viewMode === 'grid')
                    <section class="media-workspace__grid" aria-label="Media assets grid">
                        @foreach ($assets as $asset)
                            <article class="media-workspace__grid-item" wire:key="media-grid-{{ $asset['id'] }}">
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
                                    ><strong>{{ $asset['filename'] }}</strong></button>
                                    <span>{{ $asset['type_label'] }} · {{ $asset['size'] }}</span>
                                    @if ($asset['references'] !== [])
                                        <small>{{ $asset['references'][0]['type'] }} — {{ $asset['references'][0]['label'] }}@if ($asset['reference_overflow'] > 0) · +{{ $asset['reference_overflow'] }} more @endif</small>
                                    @else
                                        <small>Unreferenced</small>
                                    @endif
                                </div>
                                <div class="media-workspace__actions admin-toolbar">
                                    <button class="admin-action" type="button" wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })">Preview</button>
                                    @if ($asset['editable'])
                                        <button class="admin-action" type="button" wire:click="mountAction('edit', { asset: {{ $asset['id'] }} })">Edit</button>
                                    @endif
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
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assets as $asset)
                                    <tr wire:key="media-row-{{ $asset['id'] }}">
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
                                            ><strong title="{{ $asset['filename'] }}">{{ $asset['filename'] }}</strong></button>
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
                                            <div class="admin-toolbar">
                                                <button class="admin-action" type="button" wire:click="mountAction('preview', { asset: {{ $asset['id'] }} })">Preview</button>
                                                @if ($asset['editable'])
                                                    <button class="admin-action" type="button" wire:click="mountAction('edit', { asset: {{ $asset['id'] }} })">Edit</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-admin.table>
                @endif
            @else
                <x-admin.empty-state title="No matching files">
                    <p>Adjust the search or filters, or add a supported file above.</p>
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @endif

            <footer class="media-workspace__pager admin-toolbar">
                <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                <span>Page {{ $page }} of {{ $pages }}</span>
                <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
