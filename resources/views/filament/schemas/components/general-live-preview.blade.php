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
        fitScale: 1,
        magnifier: false,
        magnifierX: null,
        magnifierY: null,
        zoomFactor: 1.8,
        init() {
            this.$nextTick(() => {
                this.fitPreview()
                this.resizeObserver = new ResizeObserver(() => this.fitPreview())
                this.resizeObserver.observe(this.$refs.viewport)
            })
        },
        destroy() {
            window.clearTimeout(this.refreshTimer)
            this.resizeObserver?.disconnect()
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
            this.applyPreviewTransform()
        },
        applyPreviewTransform() {
            const frame = this.$refs.frame
            const page = this.$refs.page
            if (! frame || ! page) return

            const zoom = this.magnifier ? this.zoomFactor : 1
            const x = this.magnifierX ?? (page.clientWidth / 2)
            const y = this.magnifierY ?? (page.clientHeight / 2)
            frame.style.transformOrigin = 'top left'
            frame.style.left = this.magnifier ? `${x * (1 - zoom)}px` : '0px'
            frame.style.top = this.magnifier ? `${y * (1 - zoom)}px` : '0px'
            frame.style.transform = `scale(${this.fitScale * zoom})`
        },
        toggleMagnifier() {
            this.magnifier = ! this.magnifier
            if (! this.magnifier) {
                this.magnifierX = null
                this.magnifierY = null
            }
            this.applyPreviewTransform()
        },
        moveMagnifier(event) {
            if (! this.magnifier) return

            const page = this.$refs.page
            if (! page) return
            const rect = page.getBoundingClientRect()
            this.magnifierX = Math.max(0, Math.min(rect.width, event.clientX - rect.left))
            this.magnifierY = Math.max(0, Math.min(rect.height, event.clientY - rect.top))
            this.applyPreviewTransform()
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
                x-on:click="toggleMagnifier()"
                x-bind:class="{ 'is-active': magnifier }"
                x-bind:aria-pressed="magnifier.toString()"
                title="Magnify preview"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewZoom->mini()" />
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
                x-bind:class="{ 'is-magnifying': magnifier }"
                x-on:mousemove="moveMagnifier($event)"
            >
                <iframe
                    x-ref="frame"
                    src="{{ $previewUrl }}"
                    title="Interactive live preview of the public homepage"
                    loading="eager"
                    tabindex="0"
                    allowfullscreen
                    x-on:load="$nextTick(() => fitPreview())"
                ></iframe>
            </div>
        </div>
    </div>
</aside>
