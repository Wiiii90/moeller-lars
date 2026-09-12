@php
    $settings = \App\Models\PublicContentSetting::general();
    $assetId = $settings->getAttribute('favicon_media_asset_id');
    $faviconAsset = is_numeric($assetId)
        ? \App\Models\MediaAsset::query()
            ->where('state', 'available')
            ->whereIn('mime_type', \App\Domain\Media\MediaTypePolicy::IMAGE_MIME_TYPES)
            ->with('variants')
            ->find((int) $assetId)
        : null;
    $faviconThumbnail = $faviconAsset?->getRelationValue('variants')->first(static function (\App\Models\MediaVariant $variant): bool {
        return $variant->getAttribute('variant_kind') === \App\Domain\Media\MediaIngestService::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === \App\Domain\Media\MediaIngestService::TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available';
    });
    $directPreviewUrl = route('preview.home');
    $previewUrl = route('preview.home', ['appearance' => now()->timestamp]);
    $previewHost = parse_url($directPreviewUrl, PHP_URL_HOST) ?: 'preview';
    $previewPath = parse_url($directPreviewUrl, PHP_URL_PATH) ?: '/preview';
    $device = isset($generalPage) && $generalPage instanceof \App\Filament\Pages\General
        ? $generalPage->previewDevice
        : 'desktop';
    $previewWidth = $device === 'mobile' ? 390 : 1280;
    $previewHeight = $device === 'mobile' ? 844 : 720;
    $previewLabel = $device === 'mobile' ? 'iPhone · 390 × 844' : 'Desktop · 1280 × 720';
@endphp

<aside
    class="general-appearance-stage__preview admin-visual-stage__pane"
    aria-label="Live public preview"
    x-data="{
        refreshTimer: null,
        resizeObserver: null,
        zoomHudTimer: null,
        zoomHudVisible: false,
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
                this.fitPreview()
                this.resizeObserver = new ResizeObserver(() => this.fitPreview())
                this.resizeObserver.observe(this.$refs.viewport)
            })
        },
        destroy() {
            window.clearTimeout(this.refreshTimer)
            window.clearTimeout(this.zoomHudTimer)
            this.resizeObserver?.disconnect()
        },
        zoomPercent() {
            return `${Math.round(this.zoom * 100)}%`
        },
        showZoomHud() {
            window.clearTimeout(this.zoomHudTimer)
            this.zoomHudVisible = true
            this.zoomHudTimer = window.setTimeout(() => {
                this.zoomHudVisible = false
            }, 900)
        },
        fitPreview() {
            const viewport = this.$refs.viewport
            const page = this.$refs.page
            const frame = this.$refs.frame
            const device = this.$refs.device
            const browser = this.$refs.browser
            if (! viewport || ! page || ! frame || ! device || ! browser) return

            const targetWidth = Number(device.dataset.previewWidth)
            const targetHeight = Number(device.dataset.previewHeight)
            if (! targetWidth || ! targetHeight) return

            const viewportStyle = window.getComputedStyle(viewport)
            const deviceStyle = window.getComputedStyle(device)
            const horizontalPadding = parseFloat(viewportStyle.paddingLeft) + parseFloat(viewportStyle.paddingRight)
            const verticalPadding = parseFloat(viewportStyle.paddingTop) + parseFloat(viewportStyle.paddingBottom)
            const horizontalBorder = parseFloat(deviceStyle.borderLeftWidth) + parseFloat(deviceStyle.borderRightWidth)
            const verticalBorder = parseFloat(deviceStyle.borderTopWidth) + parseFloat(deviceStyle.borderBottomWidth)
            const availableWidth = Math.max(1, viewport.clientWidth - horizontalPadding)
            const availableHeight = Math.max(1, viewport.clientHeight - verticalPadding)
            const browserHeight = browser.offsetHeight
            if (! availableWidth || ! availableHeight || ! browserHeight) return

            const scale = Math.min(
                Math.max(1, availableWidth - horizontalBorder) / targetWidth,
                Math.max(1, availableHeight - browserHeight - verticalBorder) / targetHeight,
            )
            const renderedWidth = Math.max(1, Math.floor(targetWidth * scale))
            const renderedHeight = Math.max(1, Math.floor(targetHeight * scale))

            this.fitScale = scale
            device.style.width = `${renderedWidth + horizontalBorder}px`
            device.style.height = `${renderedHeight + browserHeight + verticalBorder}px`
            page.style.width = `${renderedWidth}px`
            page.style.height = `${renderedHeight}px`

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
            this.showZoomHud()
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
            this.showZoomHud()
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
                && target.closest('a, button, input, textarea, select, [contenteditable="true"], [role="button"]') !== null
        },
        handleSpaceDown(event) {
            if (event.code !== 'Space' || this.zoom <= this.minZoom || event.repeat || this.shortcutTargetIsInteractive(event.target)) return

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
        bindFrameShortcuts() {
            const frame = this.$refs.frame
            if (! frame) return

            let documentRef = null
            let windowRef = null
            try {
                documentRef = frame.contentDocument
                windowRef = frame.contentWindow
            } catch (_) {
                return
            }
            if (! documentRef || ! windowRef) return

            documentRef.addEventListener('keydown', (event) => this.handleSpaceDown(event))
            documentRef.addEventListener('keyup', (event) => this.handleSpaceUp(event))
            windowRef.addEventListener('blur', () => this.cancelSpacePan())
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
            window.clearTimeout(this.refreshTimer)
            this.refreshTimer = window.setTimeout(() => {
                const url = new URL(this.$refs.frame.src, window.location.origin)
                url.searchParams.set('_appearance', Date.now().toString())
                this.$refs.frame.src = url.toString()
            }, 700)
        },
    }"
    x-on:general-appearance-updated.window="refreshPreview()"
    x-on:keydown.window="handleSpaceDown($event)"
    x-on:keyup.window="handleSpaceUp($event)"
    x-on:blur.window="cancelSpacePan()"
>
    <div class="general-live-preview__toolbar">
        <h2 class="admin-section__kicker general-live-preview__title">Live preview</h2>

        <div class="general-live-preview__toolbar-meta">
            <span class="general-live-preview__preset">{{ $previewLabel }}</span>
            <div class="general-live-preview__modes" role="group" aria-label="Preview device">
                <button
                    class="general-live-preview__mode {{ $device === 'desktop' ? 'is-active' : '' }}"
                    type="button"
                    wire:click="setPreviewDevice('desktop')"
                    aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}"
                    title="Desktop preview"
                >
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceDesktop->mini()" />
                </button>
                <button
                    class="general-live-preview__mode {{ $device === 'mobile' ? 'is-active' : '' }}"
                    type="button"
                    wire:click="setPreviewDevice('mobile')"
                    aria-pressed="{{ $device === 'mobile' ? 'true' : 'false' }}"
                    title="Phone preview"
                >
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceMobile->mini()" />
                </button>
            </div>
            <span class="general-live-preview__tool-divider" aria-hidden="true"></span>
            <button
                class="general-live-preview__mode general-live-preview__tool"
                type="button"
                x-on:click="changeZoom(-0.25)"
                x-bind:disabled="zoom <= minZoom"
                title="Zoom out"
                aria-label="Zoom out preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewZoomOut->mini()" />
            </button>
            <button
                class="general-live-preview__zoom-value"
                type="button"
                x-on:click="resetZoom()"
                x-bind:class="{ 'is-active': zoom > minZoom }"
                x-bind:disabled="zoom <= minZoom"
                title="Reset preview zoom to 100%"
                aria-label="Reset preview zoom to 100%"
                x-text="zoomPercent()"
            ></button>
            <button
                class="general-live-preview__mode general-live-preview__tool"
                type="button"
                x-on:click="changeZoom(0.25)"
                x-bind:disabled="zoom >= maxZoom"
                title="Zoom in"
                aria-label="Zoom in preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Inspect->mini()" />
            </button>
            <button
                class="general-live-preview__mode general-live-preview__tool"
                type="button"
                x-on:click="togglePanMode()"
                x-bind:class="{ 'is-active': panMode }"
                x-bind:disabled="zoom <= minZoom"
                x-bind:aria-pressed="panMode.toString()"
                title="Pan preview (or hold Space and drag)"
                aria-label="Toggle preview pan mode"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewPan->mini()" />
            </button>
            <a
                class="general-live-preview__mode general-live-preview__tool"
                href="{{ $directPreviewUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                title="Open preview"
                aria-label="Open preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" />
            </a>
        </div>
    </div>

    <div x-ref="viewport" class="general-live-preview__viewport">
        <div
            x-ref="device"
            class="general-live-preview__device is-{{ $device }}"
            data-preview-width="{{ $previewWidth }}"
            data-preview-height="{{ $previewHeight }}"
        >
            <div x-ref="browser" class="general-live-preview__browser" aria-hidden="true">
                <span class="general-live-preview__browser-dots">
                    <i></i><i></i><i></i>
                </span>
                <span class="general-live-preview__address">
                    @if ($faviconThumbnail instanceof \App\Models\MediaVariant)
                        <img src="{{ route('admin.media.variant', $faviconThumbnail) }}" alt="">
                    @endif
                    <span>{{ $previewHost }}{{ $previewPath }}</span>
                </span>
            </div>
            <div
                x-ref="page"
                class="general-live-preview__page"
                x-bind:class="{ 'is-zoomed': zoom > minZoom, 'is-pan-enabled': zoom > minZoom && (panMode || spacePan), 'is-dragging': dragging }"
            >
                <iframe
                    x-ref="frame"
                    src="{{ $previewUrl }}"
                    title="Interactive live preview of the public homepage"
                    loading="eager"
                    tabindex="0"
                    allowfullscreen
                    x-on:load="$nextTick(() => { fitPreview(); bindFrameShortcuts(); })"
                ></iframe>

                <div
                    x-ref="panLayer"
                    class="general-live-preview__pan-layer"
                    x-show="zoom > minZoom && (panMode || spacePan)"
                    x-cloak
                    aria-hidden="true"
                    x-on:pointerdown="startPan($event)"
                    x-on:pointermove="movePan($event)"
                    x-on:pointerup="endPan($event)"
                    x-on:pointercancel="endPan($event)"
                    x-on:wheel.prevent.stop
                ></div>

                <div
                    class="general-live-preview__zoom-hud"
                    x-show="zoomHudVisible"
                    x-transition.opacity.duration.120ms
                    x-cloak
                    aria-hidden="true"
                    x-text="zoomPercent()"
                ></div>
            </div>
        </div>
    </div>
</aside>
