@props([
    'url' => null,
    'frameTitle' => 'Interactive public preview',
    'heading' => true,
    'refreshOnAppearance' => false,
])

<aside
    {{ $attributes->class(['admin-live-preview']) }}
    aria-label="Live public preview"
    x-bind:data-preview-device="previewDevice"
    x-data="{
        resizeObserver: null,
        fitFrame: null,
        frameShortcuts: [],
        pointerInside: false,
        previewDevice: 'desktop',
        // Both pages use fixed real browser layout viewports, visually
        // fitted to the Admin pane without changing public media queries.
        fitScale: 1,
        zoom: 1,
        minZoom: 1,
        maxZoom: 3,
        panX: 0,
        panY: 0,
        panMode: false,
        spacePan: false,
        dragging: false,
        pointerId: null,
        dragStartX: 0,
        dragStartY: 0,
        dragStartPanX: 0,
        dragStartPanY: 0,
        init() {
            this.$nextTick(() => {
                this.resizeObserver = new ResizeObserver(() => this.scheduleFit())
                if (this.$refs.viewport) this.resizeObserver.observe(this.$refs.viewport)
                this.scheduleFit()
            })
        },
        destroy() {
            this.resizeObserver?.disconnect()
            if (this.fitFrame !== null) cancelAnimationFrame(this.fitFrame)
            this.unbindFrameShortcuts()
            this.cancelPanGesture()
        },
        scheduleFit() {
            if (this.fitFrame !== null) return
            this.fitFrame = requestAnimationFrame(() => {
                this.fitFrame = null
                this.fitPreview()
            })
        },
        setDevice(device) {
            if (! ['desktop', 'mobile'].includes(device) || this.previewDevice === device) return
            this.cancelPanGesture()
            this.previewDevice = device
            this.zoom = this.minZoom
            this.panX = 0
            this.panY = 0
            this.panMode = false
            this.spacePan = false
            this.$nextTick(() => this.scheduleFit())
        },
        zoomPercent() {
            return `${Math.round(this.zoom * 100)}%`
        },
        fitPreview() {
            const viewport = this.$refs.viewport
            const surface = this.$refs.surface
            const page = this.$refs.page
            const frame = this.$refs.frame
            if (! viewport || ! surface || ! page || ! frame) return
            // A real CSS-pixel layout viewport; only the outer visual is scaled.
            const targetWidth = this.previewDevice === 'mobile' ? 390 : 1440
            const targetHeight = this.previewDevice === 'mobile' ? 844 : 900
            const style = getComputedStyle(viewport)
            const width = viewport.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
            const height = viewport.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)
            if (width <= 0 || height <= 0) return
            // No artificial browser chrome, device border or integer rounding.
            const scale = Math.min(width / targetWidth, height / targetHeight)
            if (! Number.isFinite(scale) || scale <= 0) return
            this.fitScale = scale
            surface.style.width = `${targetWidth * scale}px`
            surface.style.height = `${targetHeight * scale}px`
            page.style.width = `${targetWidth * scale}px`
            page.style.height = `${targetHeight * scale}px`
            frame.style.width = `${targetWidth}px`
            frame.style.height = `${targetHeight}px`
            this.clampPan()
            this.applyPreviewTransform()
        },
        applyPreviewTransform() {
            const frame = this.$refs.frame
            if (! frame) return

            frame.style.transformOrigin = 'top left'
            frame.style.left = `${this.panX}px`
            frame.style.top = `${this.panY}px`
            frame.style.transform = `scale(${this.fitScale * this.zoom})`
        },
        clampPan() {
            const page = this.$refs.page
            if (! page) return

            if (this.zoom <= this.minZoom) {
                this.zoom = this.minZoom
                this.panX = 0
                this.panY = 0
                return
            }

            const minX = Math.min(0, page.clientWidth * (1 - this.zoom))
            const minY = Math.min(0, page.clientHeight * (1 - this.zoom))
            this.panX = Math.max(minX, Math.min(0, this.panX))
            this.panY = Math.max(minY, Math.min(0, this.panY))
        },
        resetZoom() {
            this.cancelPanGesture()
            this.zoom = this.minZoom
            this.panX = 0
            this.panY = 0
            this.panMode = false
            this.spacePan = false
            this.applyPreviewTransform()
        },
        changeZoom(step) {
            const page = this.$refs.page
            if (! page) return

            const previousZoom = this.zoom
            const nextZoom = Math.max(this.minZoom, Math.min(this.maxZoom, Math.round((previousZoom + step) * 100) / 100))
            if (nextZoom === previousZoom) return

            if (nextZoom === this.minZoom) {
                this.resetZoom()
                return
            }

            const ratio = nextZoom / previousZoom
            const centerX = page.clientWidth / 2
            const centerY = page.clientHeight / 2
            this.panX = centerX - ((centerX - this.panX) * ratio)
            this.panY = centerY - ((centerY - this.panY) * ratio)
            this.zoom = nextZoom
            this.clampPan()
            this.applyPreviewTransform()
        },
        togglePanMode() {
            if (this.zoom <= this.minZoom) return

            this.panMode = ! this.panMode
            if (! this.panMode && ! this.spacePan) {
                this.cancelPanGesture()
            }
        },
        shortcutTargetIsInteractive(target) {
            return target && typeof target.closest === 'function'
                && target.closest('a, button, input, textarea, select, [contenteditable], [role=button]') !== null
        },
        handleSpaceDown(event) {
            if (event.code !== 'Space' || this.zoom <= this.minZoom || event.repeat || this.shortcutTargetIsInteractive(event.target)) return

            // Do not hijack Space on unrelated Admin controls or workspaces.
            if (! this.pointerInside && ! this.$el.contains(document.activeElement)) return
            event.preventDefault()
            this.spacePan = true
        },
        handleSpaceUp(event) {
            if (event.code !== 'Space' || ! this.spacePan) return

            event.preventDefault()
            this.spacePan = false
            if (! this.panMode) {
                this.cancelPanGesture()
            }
        },
        cancelSpacePan() {
            if (! this.spacePan) return

            this.spacePan = false
            if (! this.panMode) {
                this.cancelPanGesture()
            }
        },
        unbindFrameShortcuts() {
            for (const remove of this.frameShortcuts) remove()
            this.frameShortcuts = []
        },
        bindFrameShortcuts() {
            this.unbindFrameShortcuts()
            const frame = this.$refs.frame
            if (! frame || frame.tagName !== 'IFRAME') return

            let documentRef = null
            let windowRef = null
            try {
                documentRef = frame.contentDocument
                windowRef = frame.contentWindow
            } catch (_) {
                return
            }
            if (! documentRef || ! windowRef) return

            const down = (event) => this.handleSpaceDown(event)
            const up = (event) => this.handleSpaceUp(event)
            const blur = () => this.cancelSpacePan()
            documentRef.addEventListener('keydown', down)
            documentRef.addEventListener('keyup', up)
            windowRef.addEventListener('blur', blur)
            this.frameShortcuts.push(
                () => documentRef.removeEventListener('keydown', down),
                () => documentRef.removeEventListener('keyup', up),
                () => windowRef.removeEventListener('blur', blur),
            )
        },
        startPan(event) {
            if (this.zoom <= this.minZoom || (! this.panMode && ! this.spacePan) || (event.button !== undefined && event.button !== 0)) return

            event.preventDefault()
            this.dragging = true
            this.pointerId = event.pointerId
            this.dragStartX = event.clientX
            this.dragStartY = event.clientY
            this.dragStartPanX = this.panX
            this.dragStartPanY = this.panY
            this.$refs.panLayer?.setPointerCapture?.(event.pointerId)
        },
        movePan(event) {
            if (! this.dragging || event.pointerId !== this.pointerId) return

            this.panX = this.dragStartPanX + (event.clientX - this.dragStartX)
            this.panY = this.dragStartPanY + (event.clientY - this.dragStartY)
            this.clampPan()
            this.applyPreviewTransform()
        },
        endPan(event) {
            if (! this.dragging || (event && event.pointerId !== this.pointerId)) return

            const pointerId = this.pointerId
            if (pointerId !== null && this.$refs.panLayer?.hasPointerCapture?.(pointerId)) {
                this.$refs.panLayer.releasePointerCapture(pointerId)
            }
            this.dragging = false
            this.pointerId = null
        },
        cancelPanGesture() {
            this.endPan(null)
        },
        refreshPreview() {
            const frame = this.$refs.frame
            if (! frame || frame.tagName !== 'IFRAME') return

            // An already-persisted Appearance change must not wait on a
            // timeout that can be cancelled when Livewire morphs this pane.
            const url = new URL(frame.src, window.location.origin)
            url.searchParams.set('_appearance', Date.now().toString())
            frame.src = url.toString()
        },
    }"
    @if ($refreshOnAppearance) x-on:general-appearance-updated.window="refreshPreview()" @endif
    x-on:resize.window="scheduleFit()"
    x-on:keydown.window="handleSpaceDown($event)"
    x-on:keyup.window="handleSpaceUp($event)"
    x-on:blur.window="cancelSpacePan()"
>
    @if ($heading)
        <h2 class="admin-section__kicker admin-live-preview__title admin-visual-stage__heading">Live preview</h2>
    @endif
    <x-admin.stage-tool-row label="Live preview controls">
        <div class="admin-stage-tool-group" role="group" aria-label="Preview device">
                <button
                    class="admin-action admin-stage-tool-icon"
                    type="button"
                    x-on:click="setDevice('desktop')"
                    x-bind:class="{ 'is-primary': previewDevice === 'desktop' }"
                    x-bind:aria-pressed="(previewDevice === 'desktop').toString()"
                    title="Desktop preview · 1440 × 900 CSS pixels"
                >
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceDesktop->mini()" />
                </button>
                <button
                    class="admin-action admin-stage-tool-icon"
                    type="button"
                    x-on:click="setDevice('mobile')"
                    x-bind:class="{ 'is-primary': previewDevice === 'mobile' }"
                    x-bind:aria-pressed="(previewDevice === 'mobile').toString()"
                    title="Phone preview · 390 × 844 CSS pixels"
                >
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceMobile->mini()" />
                </button>
        </div>
        <span class="admin-control-separator" aria-hidden="true"></span>
        <div class="admin-stage-tool-group" role="group" aria-label="Preview zoom">
            <button
                class="admin-action admin-stage-tool-icon"
                type="button"
                x-on:click="changeZoom(-0.25)"
                x-bind:disabled="zoom <= minZoom"
                title="Zoom out"
                aria-label="Zoom out preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewZoomOut->mini()" />
            </button>
            <button
                class="admin-live-preview__zoom-value"
                type="button"
                x-on:click="resetZoom()"
                x-bind:class="{ 'is-active': zoom > minZoom }"
                x-bind:disabled="zoom <= minZoom"
                title="Reset preview zoom to 100%"
                aria-label="Reset preview zoom to 100%"
                x-text="zoomPercent()"
            ></button>
            <button
                class="admin-action admin-stage-tool-icon"
                type="button"
                x-on:click="changeZoom(0.25)"
                x-bind:disabled="zoom >= maxZoom"
                title="Zoom in"
                aria-label="Zoom in preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Inspect->mini()" />
            </button>
        </div>
        <span class="admin-control-separator" aria-hidden="true"></span>
        <div class="admin-stage-tool-group" role="group" aria-label="Preview actions">
            <button
                class="admin-action admin-stage-tool-icon"
                type="button"
                x-on:click="togglePanMode()"
                x-bind:class="{ 'is-primary': panMode }"
                x-bind:disabled="zoom <= minZoom"
                x-bind:aria-pressed="panMode.toString()"
                title="Pan preview (or hold Space and drag)"
                aria-label="Toggle preview pan mode"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewPan->mini()" />
            </button>
            @if (filled($url))
            <a
                class="admin-action admin-stage-tool-icon"
                href="{{ $url }}"
                target="_blank"
                rel="noopener noreferrer"
                title="Open preview"
                aria-label="Open preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" />
            </a>
            @else
                <button class="admin-action admin-stage-tool-icon" type="button" disabled
                    title="No public preview URL" aria-label="Open preview unavailable">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" />
                </button>
            @endif
        </div>
    </x-admin.stage-tool-row>


    <div x-ref="viewport" class="admin-live-preview__viewport"
        x-on:pointerenter="pointerInside = true"
        x-on:pointerleave="pointerInside = false; cancelSpacePan()">
        <div x-ref="surface" wire:ignore.self class="admin-live-preview__surface">
            <div x-ref="page" wire:ignore.self class="admin-live-preview__page"
                x-bind:class="{
                    'is-zoomed': zoom > minZoom,
                    'is-pan-enabled': zoom > minZoom && (panMode || spacePan),
                    'is-dragging': dragging,
                }">
                @if (isset($content))
                    <div x-ref="frame" wire:ignore.self class="admin-live-preview__frame admin-live-preview__frame--content">
                        {{ $content }}
                    </div>
                @elseif (filled($url))
                    <iframe x-ref="frame" wire:ignore class="admin-live-preview__frame"
                        src="{{ $url }}" title="{{ $frameTitle }}"
                        loading="eager" tabindex="0" allowfullscreen
                        x-on:load="$nextTick(() => { scheduleFit(); bindFrameShortcuts(); })"></iframe>
                @else
                    <div x-ref="frame" wire:ignore.self class="admin-live-preview__frame admin-live-preview__frame--content">
                        <x-admin.empty-state title="Preview unavailable" minimal />
                    </div>
                @endif
                <div x-ref="panLayer" class="admin-live-preview__pan-layer"
                    x-show="zoom > minZoom && (panMode || spacePan)" x-cloak
                    aria-hidden="true" x-on:pointerdown="startPan($event)"
                    x-on:pointermove="movePan($event)" x-on:pointerup="endPan($event)"
                    x-on:pointercancel="endPan($event)" x-on:wheel.prevent.stop></div>
            </div>
        </div>
    </div>
</aside>