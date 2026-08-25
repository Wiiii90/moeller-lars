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

        @php($reorderEnabled = trim($search) === '' && $statusFilter === 'any' && $readinessFilter === 'any')

        <div
            class="gallery-workspace__result-surface"
            x-data="{
                filtering: false,
                filterRevision: 0,
                stateRequest: Promise.resolve(),
                filterValues() {
                    return {
                        search: this.$refs.search.value,
                        status: this.$refs.status.value,
                        readiness: this.$refs.readiness.value,
                    }
                },
                queueFilters() {
                    const revision = ++this.filterRevision
                    const filters = this.filterValues()
                    this.filtering = true
                    this.stateRequest = this.stateRequest
                        .catch(() => {})
                        .then(() => $wire.applyFilters(filters.search, filters.status, filters.readiness))
                        .finally(() => {
                            if (this.filterRevision === revision) this.filtering = false
                        })
                    return this.stateRequest
                },
                queueSelection(artworkId, selected) {
                    this.stateRequest = this.stateRequest
                        .catch(() => {})
                        .then(() => $wire.setArtworkSelected(artworkId, selected))
                    return this.stateRequest
                },
                resetFilters() {
                    this.$refs.search.value = ''
                    this.$refs.status.value = 'any'
                    this.$refs.readiness.value = 'any'
                    return this.queueFilters()
                },
                async settleWorkspaceState() {
                    let pending
                    do {
                        pending = this.stateRequest
                        try {
                            await pending
                        } catch (_) {}
                    } while (pending !== this.stateRequest)
                },
                async mountAfterState(name, arguments = {}) {
                    await this.settleWorkspaceState()
                    await $wire.mountAction(name, arguments)
                },
                async openArtwork(artworkId) {
                    await this.settleWorkspaceState()
                    await $wire.mountAction('previewArtwork', { artwork: artworkId })
                },
            }"
            x-bind:aria-busy="filtering.toString()"
        >
            <div class="gallery-workspace__controls" aria-label="Gallery controls">
                <label class="gallery-workspace__field gallery-workspace__search">
                    <span>Search artworks</span>
                    <input
                        type="search"
                        wire:ignore
                        x-ref="search"
                        value="{{ $search }}"
                        x-on:blur="queueFilters()"
                        x-on:keydown.enter.prevent="$el.blur()"
                        placeholder="Title, material, dimensions"
                        autocomplete="off"
                    >
                </label>

                <label class="gallery-workspace__field">
                    <span>Status</span>
                    <select wire:ignore x-ref="status" x-on:change="queueFilters()">
                        <option value="any" @selected($statusFilter === 'any')>Any</option>
                        <option value="published" @selected($statusFilter === 'published')>Published</option>
                        <option value="draft" @selected($statusFilter === 'draft')>Draft</option>
                    </select>
                </label>

                <label class="gallery-workspace__field">
                    <span>Readiness</span>
                    <select wire:ignore x-ref="readiness" x-on:change="queueFilters()">
                        <option value="any" @selected($readinessFilter === 'any')>Any</option>
                        <option value="ready" @selected($readinessFilter === 'ready')>Ready</option>
                        <option value="needs-attention" @selected($readinessFilter === 'needs-attention')>Needs attention</option>
                    </select>
                </label>

                <div class="gallery-workspace__control-group">
                    <span class="gallery-workspace__control-label">Filter</span>
                    <button class="admin-action" type="button" x-on:click="resetFilters()">Reset</button>
                </div>

                <div class="gallery-workspace__control-group gallery-workspace__gallery">
                    <span class="gallery-workspace__control-label">Gallery</span>
                    <div class="gallery-workspace__gallery-actions">
                        <button class="admin-action" type="button" x-on:click="mountAfterState('gallerySettings')">Settings</button>
                        <button class="admin-action" type="button" x-on:click="mountAfterState('addArtwork')">Add artwork</button>
                        <button class="admin-action" type="button" x-on:click="mountAfterState('materialPresets')">Materials</button>
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
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; mountAfterState('moveSelectedToGallery')">Move to Gallery…</button>
                            @endif
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; settleWorkspaceState().then(() => $wire.moveSelectedArtworks('up'))" @disabled(! $reorderEnabled)>Move selected up</button>
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; settleWorkspaceState().then(() => $wire.moveSelectedArtworks('down'))" @disabled(! $reorderEnabled)>Move selected down</button>
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; mountAfterState('publishSelectedArtworks')">Publish selected</button>
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; mountAfterState('unpublishSelectedArtworks')">Unpublish selected</button>
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; mountAfterState('removeSelectedArtworks')">Remove selected</button>
                            <button class="admin-action" type="button" role="menuitem" x-on:click="open = false; mountAfterState('deleteSelectedArtworks')">Delete selected</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($artworks !== [])
                <section
                    class="admin-gallery-grid"
                    aria-label="Artwork sequence for {{ $galleryContext['name'] }}"
                    data-reorder-enabled="{{ $reorderEnabled ? 'true' : 'false' }}"
                    x-data="{
                        draggingId: null,
                        overId: null,
                        canReorder() {
                            return ! filtering && this.$root.dataset.reorderEnabled === 'true'
                        },
                        orderedIds() {
                            return Array.from(this.$root.querySelectorAll('[data-gallery-artwork-id]'))
                                .map((card) => Number(card.dataset.galleryArtworkId))
                        },
                        startDrag(id, event) {
                            if (! this.canReorder()) {
                                event.preventDefault()
                                return
                            }

                            this.draggingId = id
                            this.overId = null
                            event.dataTransfer.effectAllowed = 'move'
                            event.dataTransfer.setData('text/plain', String(id))
                        },
                        dragOver(id, event) {
                            if (! this.canReorder() || this.draggingId === null) return

                            event.preventDefault()
                            event.dataTransfer.dropEffect = 'move'
                            this.overId = id === this.draggingId ? null : id
                        },
                        dropOn(targetId, event) {
                            if (! this.canReorder() || this.draggingId === null) return

                            event.preventDefault()
                            const current = this.orderedIds()
                            const from = current.indexOf(this.draggingId)
                            const to = current.indexOf(targetId)

                            if (from < 0 || to < 0 || from === to) {
                                this.endDrag()
                                return
                            }

                            const next = [...current]
                            const [moved] = next.splice(from, 1)
                            next.splice(to, 0, moved)
                            this.endDrag()
                            $wire.reorderArtworks(next)
                        },
                        endDrag() {
                            this.draggingId = null
                            this.overId = null
                        },
                    }"
                    x-on:dragend.window="endDrag"
                >
                    @foreach ($artworks as $artwork)
                        @php($selected = collect($selectedArtworkIds)->contains(fn ($id) => (int) $id === (int) $artwork['id']))
                        <article
                            class="admin-gallery-grid__item gallery-workspace__card"
                            wire:key="gallery-artwork-{{ $artwork['id'] }}"
                            data-gallery-artwork-id="{{ $artwork['id'] }}"
                            x-data="{ selected: {{ $selected ? 'true' : 'false' }} }"
                            x-bind:class="{
                                'is-selected': selected,
                                'is-dragging': draggingId === Number($el.dataset.galleryArtworkId),
                                'is-drop-target': overId === Number($el.dataset.galleryArtworkId),
                            }"
                            x-on:dragover="dragOver(Number($el.dataset.galleryArtworkId), $event)"
                            x-on:drop="dropOn(Number($el.dataset.galleryArtworkId), $event)"
                        >
                            <div
                                class="admin-gallery-grid__image gallery-workspace__visual"
                                role="button"
                                tabindex="0"
                                aria-label="Preview {{ $artwork['title'] }}"
                                x-on:click.prevent="openArtwork({{ $artwork['id'] }})"
                                x-on:keydown.enter.prevent.stop="openArtwork({{ $artwork['id'] }})"
                                x-on:keydown.space.prevent.stop="openArtwork({{ $artwork['id'] }})"
                            >
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                @elseif ($artwork['primary_kind'] === 'video' && $artwork['primary_original_url'])
                                    <video src="{{ $artwork['primary_original_url'] }}" preload="metadata" muted playsinline aria-label="Video preview for {{ $artwork['title'] }}" draggable="false"></video>
                                @else
                                    <span>No primary media</span>
                                @endif
                                <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                            </div>

                            <div class="gallery-workspace__primary-actions" aria-label="Primary actions for {{ $artwork['title'] }}">
                                <button
                                    class="gallery-workspace__icon-action gallery-workspace__primary-action"
                                    type="button"
                                    x-on:pointerdown.stop
                                    x-on:click.stop="openArtwork({{ $artwork['id'] }})"
                                    title="Preview artwork"
                                    aria-label="Preview {{ $artwork['title'] }}"
                                >
                                    <x-filament::icon icon="heroicon-m-magnifying-glass-plus" />
                                </button>
                                @if ($artwork['state'] === 'published')
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__primary-action"
                                        type="button"
                                        x-on:pointerdown.stop
                                        x-on:click.stop="mountAfterState('unpublishArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Unpublish artwork"
                                        aria-label="Unpublish {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon icon="heroicon-m-eye-slash" />
                                    </button>
                                @else
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__primary-action"
                                        type="button"
                                        x-on:pointerdown.stop
                                        x-on:click.stop="mountAfterState('publishArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Publish artwork"
                                        aria-label="Publish {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon icon="heroicon-m-eye" />
                                    </button>
                                @endif
                                <button
                                    class="gallery-workspace__icon-action gallery-workspace__primary-action gallery-workspace__drag-handle"
                                    type="button"
                                    draggable="{{ $reorderEnabled ? 'true' : 'false' }}"
                                    aria-disabled="{{ $reorderEnabled ? 'false' : 'true' }}"
                                    title="{{ $reorderEnabled ? 'Drag to reorder' : 'Clear filters to reorder' }}"
                                    aria-label="{{ $reorderEnabled ? 'Drag '.$artwork['title'].' to reorder' : 'Clear filters to reorder '.$artwork['title'] }}"
                                    x-on:pointerdown.stop
                                    x-on:click.prevent.stop
                                    x-on:dragstart.stop="startDrag(Number($el.closest('[data-gallery-artwork-id]').dataset.galleryArtworkId), $event)"
                                    x-on:dragend.stop="endDrag"
                                >
                                    <x-filament::icon icon="heroicon-m-arrows-up-down" />
                                </button>
                            </div>

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
                                    <input
                                        type="checkbox"
                                        value="{{ $artwork['id'] }}"
                                        @checked($selected)
                                        x-on:change="
                                            const nextSelected = $event.target.checked
                                            selected = nextSelected
                                            queueSelection({{ $artwork['id'] }}, nextSelected)
                                        "
                                    >
                                    <span>Select</span>
                                </label>
                                <div class="gallery-workspace__card-actions">
                                    <button
                                        class="gallery-workspace__icon-action"
                                        type="button"
                                        x-on:click="mountAfterState('editArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Edit artwork"
                                        aria-label="Edit {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon icon="heroicon-m-pencil-square" />
                                    </button>
                                    @if ($artwork['public_url'])
                                        <a
                                            class="gallery-workspace__icon-action"
                                            href="{{ $artwork['public_url'] }}"
                                            target="_blank"
                                            rel="noopener"
                                            draggable="false"
                                            title="View public artwork"
                                            aria-label="View {{ $artwork['title'] }} on the public site"
                                        >
                                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" />
                                        </a>
                                    @endif
                                    <button
                                        class="admin-action gallery-workspace__order-action"
                                        type="button"
                                        x-on:click="settleWorkspaceState().then(() => $wire.moveArtwork({{ $artwork['id'] }}, 'up'))"
                                        title="{{ $reorderEnabled ? 'Move artwork earlier' : 'Clear filters to reorder' }}"
                                        aria-label="{{ $reorderEnabled ? 'Move '.$artwork['title'].' earlier' : 'Clear filters to reorder '.$artwork['title'] }}"
                                        @disabled(! $reorderEnabled || ! $artwork['can_move_up'])
                                    >↑</button>
                                    <button
                                        class="admin-action gallery-workspace__order-action"
                                        type="button"
                                        x-on:click="settleWorkspaceState().then(() => $wire.moveArtwork({{ $artwork['id'] }}, 'down'))"
                                        title="{{ $reorderEnabled ? 'Move artwork later' : 'Clear filters to reorder' }}"
                                        aria-label="{{ $reorderEnabled ? 'Move '.$artwork['title'].' later' : 'Clear filters to reorder '.$artwork['title'] }}"
                                        @disabled(! $reorderEnabled || ! $artwork['can_move_down'])
                                    >↓</button>
                                    @if ($moveTargets !== [])
                                        <button class="gallery-workspace__icon-action" type="button" x-on:click="mountAfterState('moveArtworkToGallery', { artwork: {{ $artwork['id'] }} })" title="Move to Gallery" aria-label="Move {{ $artwork['title'] }} to another Gallery">
                                            <x-filament::icon icon="heroicon-m-arrows-right-left" />
                                        </button>
                                    @endif
                                    <button class="gallery-workspace__icon-action" type="button" x-on:click="mountAfterState('removeArtwork', { artwork: {{ $artwork['id'] }} })" title="Remove from Gallery" aria-label="Remove {{ $artwork['title'] }} from Gallery">
                                        <x-filament::icon icon="heroicon-m-link-slash" />
                                    </button>
                                    <button
                                        class="gallery-workspace__icon-action"
                                        type="button"
                                        x-on:click="mountAfterState('deletePrimaryMedia', { artwork: {{ $artwork['id'] }} })"
                                        @disabled(! $artwork['primary_original_url'])
                                        title="{{ $artwork['primary_original_url'] ? 'Delete media file' : 'No primary media file to delete' }}"
                                        aria-label="{{ $artwork['primary_original_url'] ? 'Delete primary Media File for '.$artwork['title'] : 'No primary Media File to delete for '.$artwork['title'] }}"
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
                        <button class="admin-action" type="button" x-on:click="resetFilters()">Clear filters</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state title="No artworks added to this Gallery" minimal>
                    <x-slot:actions>
                        <button class="admin-action" type="button" x-on:click="mountAfterState('addArtwork')">Add artwork</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @endif
        </div>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>