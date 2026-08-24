<x-filament-panels::page>
    <x-admin.workspace :title="$galleryContext['name']">
        <x-admin.metrics :columns="6" aria-label="Gallery overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <div class="admin-gallery-upload" aria-label="Add artwork from media">
            <div
                class="admin-gallery-upload__dropzone"
                x-data="{
                    phase: 'idle',
                    progress: 0,
                    fileCount: 0,
                    result: '',
                    resultTimer: null,
                    showResult(message) {
                        window.clearTimeout(this.resultTimer)
                        this.result = message
                        this.phase = 'result'
                        this.resultTimer = window.setTimeout(() => {
                            this.phase = 'idle'
                            this.progress = 0
                            this.fileCount = 0
                            this.result = ''
                        }, 3800)
                    },
                }"
                x-bind:aria-busy="(phase === 'uploading' || phase === 'processing').toString()"
                x-on:livewire-upload-start="window.clearTimeout(resultTimer); phase = 'uploading'; progress = 0; result = ''"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                x-on:livewire-upload-finish="
                    progress = 100
                    phase = 'processing'
                    $wire.processDirectPrimaryMedia()
                        .then((response) => showResult(response?.summary ?? 'Upload complete'))
                        .catch(() => showResult('Upload failed'))
                "
                x-on:livewire-upload-error="$wire.set('directPrimaryMedia', []); showResult('Upload failed')"
            >
                <input
                    class="admin-gallery-upload__input"
                    type="file"
                    wire:model="directPrimaryMedia"
                    x-on:change="fileCount = $event.target.files.length"
                    x-bind:disabled="phase === 'uploading' || phase === 'processing'"
                    accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                    aria-label="Upload primary images or videos for new artworks"
                    multiple
                >
                <div class="admin-gallery-upload__copy">
                    <strong
                        x-text="
                            phase === 'uploading'
                                ? `Uploading ${fileCount} ${fileCount === 1 ? 'file' : 'files'} · ${progress}%`
                                : phase === 'processing'
                                    ? `Processing ${fileCount} ${fileCount === 1 ? 'file' : 'files'}…`
                                    : phase === 'result'
                                        ? result
                                        : 'Drop images or videos here or choose from your device'
                        "
                    >Drop images or videos here or choose from your device</strong>
                    <span x-show="phase === 'idle'">JPEG, PNG, WebP, MP4 or WebM.</span>
                </div>
                <div
                    class="admin-gallery-upload__progress"
                    x-show="phase === 'uploading'"
                    x-cloak
                    role="progressbar"
                    aria-label="Upload progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    x-bind:aria-valuenow="progress"
                >
                    <span class="admin-gallery-upload__progress-track" aria-hidden="true">
                        <span class="admin-gallery-upload__progress-fill" x-bind:style="`width: ${progress}%`"></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="gallery-workspace__controls" aria-label="Gallery controls">
            <label class="gallery-workspace__field gallery-workspace__search">
                <span>Search artworks</span>
                <input
                    type="search"
                    wire:model.change="search"
                    x-on:keydown.enter.prevent="$el.blur()"
                    placeholder="Search artworks"
                    autocomplete="off"
                >
            </label>

            <label class="gallery-workspace__field">
                <span>Status</span>
                <select wire:model.change="statusFilter">
                    <option value="any">Any</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </label>

            <label class="gallery-workspace__field">
                <span>Readiness</span>
                <select wire:model.change="readinessFilter">
                    <option value="any">Any</option>
                    <option value="ready">Ready</option>
                    <option value="needs-attention">Needs attention</option>
                </select>
            </label>

            <div class="gallery-workspace__control-group">
                <span class="gallery-workspace__control-label">Filter</span>
                <button class="admin-action" type="button" wire:click="resetFilters">Reset</button>
            </div>

            <div class="gallery-workspace__control-group gallery-workspace__gallery">
                <span class="gallery-workspace__control-label">Gallery</span>
                <div class="gallery-workspace__gallery-actions">
                    <button class="admin-action" type="button" wire:click="mountAction('gallerySettings')">Settings</button>
                    <button class="admin-action" type="button" wire:click="mountAction('addArtwork')">Add artwork</button>
                    <button class="admin-action" type="button" wire:click="mountAction('materialPresets')">Materials</button>
                    @if ($galleryContext['public_url'])
                        <a class="admin-action" href="{{ $galleryContext['public_url'] }}" target="_blank" rel="noopener">Preview</a>
                    @else
                        <button class="admin-action" type="button" disabled title="Publish the Gallery to open its public URL">Preview</button>
                    @endif
                </div>
            </div>

            <div class="gallery-workspace__control-group gallery-workspace__selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                <span class="gallery-workspace__control-label">Selection</span>
                <div class="gallery-workspace__selection-anchor">
                    <button
                        class="admin-action gallery-workspace__selection-trigger"
                        type="button"
                        x-on:click="open = !open"
                        x-bind:aria-expanded="open"
                        aria-haspopup="menu"
                        @disabled(count($selectedArtworkIds) === 0)
                    >
                        Selected artworks
                        <span class="gallery-workspace__selection-count">{{ count($selectedArtworkIds) }}</span>
                    </button>
                    <div class="gallery-workspace__selection-menu" x-show="open" x-cloak x-on:click.outside="open = false" role="menu">
                        @if ($moveTargets !== [])
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('moveSelectedToGallery')">Move to Gallery…</button>
                        @endif
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="moveSelectedArtworks('up')">Move selected up</button>
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="moveSelectedArtworks('down')">Move selected down</button>
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('publishSelectedArtworks')">Publish selected</button>
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('unpublishSelectedArtworks')">Unpublish selected</button>
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('removeSelectedArtworks')">Remove selected</button>
                        <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('deleteSelectedArtworks')">Delete selected</button>
                    </div>
                </div>
            </div>
        </div>

        @if ($artworks !== [])
            <section class="admin-gallery-grid" aria-label="Artwork sequence for {{ $galleryContext['name'] }}">
                @foreach ($artworks as $artwork)
                    <article class="admin-gallery-grid__item" wire:key="gallery-artwork-{{ $artwork['id'] }}">
                        @if ($artwork['media_preview_url'])
                            <a class="admin-gallery-grid__image gallery-workspace__visual" href="{{ $artwork['media_preview_url'] }}" aria-label="Open media preview for {{ $artwork['title'] }}">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                @elseif ($artwork['primary_kind'] === 'video' && $artwork['primary_original_url'])
                                    <video src="{{ $artwork['primary_original_url'] }}" preload="metadata" muted playsinline aria-label="Video preview for {{ $artwork['title'] }}"></video>
                                @else
                                    <span>No primary media</span>
                                @endif
                                <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                            </a>
                        @else
                            <div class="admin-gallery-grid__image">
                                <span>No primary media</span>
                                <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        @endif

                        <div class="admin-gallery-grid__caption">
                            <div class="admin-gallery-grid__identity">
                                <div
                                    class="gallery-workspace__title-editor"
                                    x-data="{
                                        editing: false,
                                        saving: false,
                                        original: '',
                                        value: '',
                                        normalize(value) { return value.trim().replace(/\s+/g, ' ') },
                                        start() {
                                            this.value = this.original
                                            this.editing = true
                                            this.$nextTick(() => this.$refs.input.select())
                                        },
                                        cancel() {
                                            this.value = this.original
                                            this.editing = false
                                            this.saving = false
                                        },
                                        commit() {
                                            if (! this.editing || this.saving) return

                                            const next = this.normalize(this.value)
                                            if (next === this.normalize(this.original)) {
                                                this.value = this.original
                                                this.editing = false
                                                return
                                            }

                                            this.saving = true
                                            $wire.renameArtwork({{ $artwork['id'] }}, next)
                                                .then((saved) => {
                                                    if (saved) {
                                                        this.original = saved
                                                        this.value = saved
                                                    } else {
                                                        this.value = this.original
                                                    }
                                                    this.editing = false
                                                    this.saving = false
                                                })
                                                .catch(() => {
                                                    this.value = this.original
                                                    this.editing = false
                                                    this.saving = false
                                                })
                                        },
                                    }"
                                    x-init="original = $refs.initialTitle.textContent; value = original"
                                >
                                    <span x-ref="initialTitle" hidden>{{ $artwork['title'] }}</span>
                                    <button class="gallery-workspace__title-button" type="button" x-show="!editing" x-on:click="start" title="Rename artwork">
                                        <strong x-text="original">{{ $artwork['title'] }}</strong>
                                    </button>
                                    <input
                                        class="gallery-workspace__title-input"
                                        x-ref="input"
                                        x-show="editing"
                                        x-cloak
                                        x-model="value"
                                        x-on:keydown.enter.prevent="commit"
                                        x-on:keydown.escape.stop.prevent="cancel"
                                        x-on:blur="commit"
                                        maxlength="240"
                                        aria-label="Rename {{ $artwork['title'] }}"
                                    >
                                </div>
                                <span>
                                    @if ($artwork['year']){{ $artwork['year'] }}@endif
                                    @if ($artwork['medium']){{ $artwork['year'] ? ' · ' : '' }}{{ $artwork['medium'] }}@endif
                                    @if ($artwork['dimensions']){{ ($artwork['year'] || $artwork['medium']) ? ' · ' : '' }}{{ $artwork['dimensions'] }}@endif
                                </span>
                                <small class="admin-gallery-grid__analytics">
                                    @if ($artwork['analytics']['available'])
                                        30d · {{ number_format($artwork['analytics']['views']) }} views · {{ number_format($artwork['analytics']['opens']) }} opens · {{ number_format($artwork['analytics']['zooms']) }} zooms · {{ $artwork['analytics']['attention'] }} attention
                                    @else
                                        30d analytics unavailable
                                    @endif
                                </small>
                            </div>
                            <span class="admin-gallery-grid__state {{ $artwork['state'] === 'published' ? 'is-published' : '' }}">
                                {{ $artwork['state_label'] }}
                                @if ($artwork['state'] !== 'published')
                                    <span>· {{ $artwork['readiness_label'] }}</span>
                                @endif
                            </span>
                        </div>

                        <div class="admin-gallery-grid__actions">
                            <label class="gallery-workspace__selection-checkbox">
                                <input type="checkbox" wire:model.live="selectedArtworkIds" value="{{ $artwork['id'] }}" aria-label="Select {{ $artwork['title'] }}">
                            </label>
                            <div class="gallery-workspace__card-actions">
                                <button class="admin-action gallery-workspace__edit-action" type="button" wire:click="mountAction('editArtwork', { artwork: {{ $artwork['id'] }} })">Edit</button>
                                @if ($artwork['public_url'])
                                    <a class="gallery-workspace__icon-action" href="{{ $artwork['public_url'] }}" target="_blank" rel="noopener" title="View public artwork" aria-label="View {{ $artwork['title'] }} on the public site">
                                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" />
                                    </a>
                                @endif
                                @if ($artwork['state'] === 'published')
                                    <button class="gallery-workspace__icon-action" type="button" wire:click="mountAction('unpublishArtwork', { artwork: {{ $artwork['id'] }} })" title="Unpublish artwork" aria-label="Unpublish {{ $artwork['title'] }}">
                                        <x-filament::icon icon="heroicon-m-eye-slash" />
                                    </button>
                                @else
                                    <button class="gallery-workspace__icon-action" type="button" wire:click="mountAction('publishArtwork', { artwork: {{ $artwork['id'] }} })" title="Publish artwork" aria-label="Publish {{ $artwork['title'] }}">
                                        <x-filament::icon icon="heroicon-m-eye" />
                                    </button>
                                @endif
                                <button class="admin-action gallery-workspace__order-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'up')" aria-label="Move {{ $artwork['title'] }} earlier" @disabled(! $artwork['can_move_up'])>↑</button>
                                <button class="admin-action gallery-workspace__order-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'down')" aria-label="Move {{ $artwork['title'] }} later" @disabled(! $artwork['can_move_down'])>↓</button>
                                @if ($moveTargets !== [])
                                    <button class="gallery-workspace__icon-action" type="button" wire:click="mountAction('moveArtworkToGallery', { artwork: {{ $artwork['id'] }} })" title="Move to Gallery" aria-label="Move {{ $artwork['title'] }} to another Gallery">
                                        <x-filament::icon icon="heroicon-m-arrows-right-left" />
                                    </button>
                                @endif
                                <button class="gallery-workspace__icon-action" type="button" wire:click="mountAction('removeArtwork', { artwork: {{ $artwork['id'] }} })" title="Remove from Gallery" aria-label="Remove {{ $artwork['title'] }} from Gallery">
                                    <x-filament::icon icon="heroicon-m-link-slash" />
                                </button>
                                <button
                                    class="gallery-workspace__icon-action"
                                    type="button"
                                    wire:click="mountAction('deleteArtwork', { artwork: {{ $artwork['id'] }} })"
                                    @disabled($artwork['state'] !== 'draft')
                                    title="{{ $artwork['state'] === 'draft' ? 'Delete artwork' : 'Unpublish before deleting' }}"
                                    aria-label="Delete {{ $artwork['title'] }}"
                                >
                                    <x-filament::icon icon="heroicon-m-trash" />
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @elseif ($unfilteredArtworkCount > 0)
            <x-admin.empty-state title="No matching artworks" minimal>
                <x-slot:actions>
                    <button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button>
                </x-slot:actions>
            </x-admin.empty-state>
        @else
            <x-admin.empty-state title="No artworks added to this Gallery" minimal>
                <x-slot:actions>
                    <button class="admin-action" type="button" wire:click="mountAction('addArtwork')">Add artwork</button>
                </x-slot:actions>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
