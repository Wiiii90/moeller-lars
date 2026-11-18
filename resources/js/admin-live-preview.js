// Shared Gallery/General Live Preview: one layout viewport, one zoom/pan
// lifecycle and optional isolated browser-engine rendering.
export function adminLivePreview(element) {
    const profiles = JSON.parse(element.dataset.previewProfiles || '{}');
    return {
        resizeObserver: null,
        fitFrame: null,
        frameShortcuts: [],
        pointerInside: false,
        previewDevice: 'desktop',
        selectedProfile: 'desktop-laptop',
        rotated: false,
        engine: 'auto',
        comparisonUnavailable: false,
        currentUrl: element.dataset.previewUrl || '',
        snapshotUrl: '',
        snapshotLoading: false,
        horizontalOverflow: false,
        snapshotAbort: null,
        snapshotRequest: 0,
        snapshotScrollY: 0,
        snapshotScrollMax: 0,
        snapshotWheelDelay: null,
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
            this.snapshotAbort?.abort()
            if (this.snapshotWheelDelay !== null) clearTimeout(this.snapshotWheelDelay)
            if (this.snapshotUrl) URL.revokeObjectURL(this.snapshotUrl)
        },
        scheduleFit() {
            if (this.fitFrame !== null) return
            this.fitFrame = requestAnimationFrame(() => {
                this.fitFrame = null
                this.fitPreview()
            })
        },
        selectProfile(value) {
            if (! Object.hasOwn(profiles, value)) return
            this.selectedProfile = value
            this.previewDevice = profiles[value].category
            this.rotated = false
            this.snapshotScrollY = 0
            this.snapshotScrollMax = 0
            this.changeViewport()
        },
        activeEngine() {
            if (this.engine !== 'auto') return this.engine
            const platform = profiles[this.selectedProfile]?.platform
            // Desktop keeps its live, interactive iframe. iOS and Android
            // presets use their matching browser engine when available.
            return platform === 'ios' ? 'webkit'
                : platform === 'android' ? 'chromium' : 'live'
        },
        usesBrowserRenderer() {
            return this.currentUrl !== '' && this.activeEngine() !== 'live'
                && ! this.comparisonUnavailable
        },
        isShowingSnapshot() {
            return this.snapshotUrl !== '' && this.activeEngine() !== 'live'
        },
        rendererStatus() {
            if (! this.comparisonUnavailable || this.activeEngine() === 'live') return ''
            const name = this.activeEngine() === 'webkit' ? 'Safari'
                : this.activeEngine() === 'chromium' ? 'Chrome' : 'Firefox'
            return `${name} comparison unavailable. Showing the live browser view instead.`
        },
        setEngine(value) {
            if (! ['auto', 'live', 'chromium', 'webkit', 'firefox'].includes(value)) return
            this.engine = value
            // A deliberate mode change is also an explicit retry after an
            // unavailable browser renderer has since been started.
            this.comparisonUnavailable = false
            this.snapshotAbort?.abort()
            this.releaseSnapshot()
            this.snapshotLoading = false
            this.snapshotScrollY = 0
            this.snapshotScrollMax = 0
            if (this.usesBrowserRenderer()) this.requestSnapshot()
            else this.updateViewportDiagnostics()
        },
        onFrameLoad() {
            this.scheduleFit()
            this.bindFrameShortcuts()
            this.updateViewportDiagnostics()
            if (this.usesBrowserRenderer() && ! this.snapshotLoading && ! this.snapshotUrl) {
                this.requestSnapshot()
            }
        },
        releaseSnapshot() {
            if (this.snapshotUrl) URL.revokeObjectURL(this.snapshotUrl)
            this.snapshotUrl = ''
        },
        changeViewport() {
            this.cancelPanGesture()
            this.zoom = this.minZoom
            this.panX = 0
            this.panY = 0
            this.panMode = false
            this.spacePan = false
            this.$nextTick(() => {
                this.scheduleFit()
                if (this.usesBrowserRenderer()) this.requestSnapshot()
                else this.updateViewportDiagnostics()
            })
        },
        rotateViewport() {
            this.snapshotScrollY = 0
            this.snapshotScrollMax = 0
            this.rotated = ! this.rotated
            this.changeViewport()
        },
        viewportSize() {
            const profile = profiles[this.selectedProfile] ?? profiles['desktop-laptop']
            return this.rotated
                ? [profile.height, profile.width]
                : [profile.width, profile.height]
        },
        zoomPercent() {
            return this.zoom <= this.minZoom ? 'Fit' : `${Math.round(this.zoom * 100)}%`
        },
        fitPreview() {
            const viewport = this.$refs.viewport
            const surface = this.$refs.surface
            const page = this.$refs.page
            const frame = this.$refs.frame
            if (! viewport || ! surface || ! page || ! frame) return
            if (frame.tagName !== 'IFRAME' && this.frameShortcuts.length) this.unbindFrameShortcuts()
            // A real CSS-pixel layout viewport; only the outer visual is scaled.
            const [targetWidth, targetHeight] = this.viewportSize()
            const style = getComputedStyle(viewport)
            const width = viewport.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
            const height = viewport.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)
            if (width <= 0 || height <= 0) return
            // No artificial browser chrome, device border or integer rounding.
            // Never upscale a smaller document: 1:1 is the sharp native
            // rendering reference, and Fit can only reduce its pixels.
            const scale = Math.min(1, width / targetWidth, height / targetHeight)
            if (! Number.isFinite(scale) || scale <= 0) return
            this.fitScale = scale
            this.maxZoom = Math.max(3, Math.ceil(1 / scale))
            surface.style.width = `${targetWidth * scale}px`
            surface.style.height = `${targetHeight * scale}px`
            page.style.width = `${targetWidth * scale}px`
            page.style.height = `${targetHeight * scale}px`
            frame.style.width = `${targetWidth}px`
            frame.style.height = `${targetHeight}px`
            const snapshot = this.$refs.snapshotFrame
            if (snapshot) {
                snapshot.style.width = `${targetWidth}px`
                snapshot.style.height = `${targetHeight}px`
            }
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
            const snapshot = this.$refs.snapshotFrame
            if (snapshot) {
                snapshot.style.transformOrigin = 'top left'
                snapshot.style.left = `${this.panX}px`
                snapshot.style.top = `${this.panY}px`
                snapshot.style.transform = frame.style.transform
            }
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
        updateViewportDiagnostics() {
            const frame = this.$refs.frame
            if (! frame || frame.tagName !== 'IFRAME') return
            try {
                const doc = frame.contentDocument
                if (! doc) return
                const touchPreview = Boolean(profiles[this.selectedProfile]?.touch)
                doc.documentElement.classList.toggle('public-preview--touch', touchPreview)
                const root = doc.documentElement
                this.horizontalOverflow = Math.max(root.scrollWidth, doc.body?.scrollWidth ?? 0)
                    > root.clientWidth + 1
            } catch (_) {
                this.horizontalOverflow = false
            }
        },
        scrollSnapshot(amount) {
            if (! this.isShowingSnapshot() || this.snapshotLoading) return
            const next = Math.max(0, Math.min(this.snapshotScrollMax,
                Math.round(this.snapshotScrollY + amount)))
            if (next === this.snapshotScrollY) return
            this.snapshotScrollY = next
            if (this.snapshotWheelDelay !== null) clearTimeout(this.snapshotWheelDelay)
            this.snapshotWheelDelay = setTimeout(() => {
                this.snapshotWheelDelay = null
                this.requestSnapshot()
            }, 160)
        },
        handleSnapshotWheel(event) {
            if (! this.isShowingSnapshot()) return
            event.preventDefault()
            this.scrollSnapshot(event.deltaY)
        },
        handleSnapshotKeydown(event) {
            if (! this.isShowingSnapshot()) return
            const height = this.viewportSize()[1]
            const amount = {
                ArrowDown: 80,
                ArrowUp: -80,
                PageDown: height * .8,
                PageUp: -height * .8,
                Home: -this.snapshotScrollMax,
                End: this.snapshotScrollMax,
            }[event.key]
            if (amount === undefined) return
            event.preventDefault()
            this.scrollSnapshot(amount)
        },
        async requestSnapshot() {
            if (! this.usesBrowserRenderer()) return
            const selectedEngine = this.activeEngine()
            this.snapshotAbort?.abort()
            const controller = new AbortController()
            this.snapshotAbort = controller
            const revision = ++this.snapshotRequest
            this.snapshotLoading = true
            this.horizontalOverflow = false
            this.releaseSnapshot()
            try {
                const target = new URL(this.currentUrl, window.location.origin)
                if (target.origin !== window.location.origin
                    || ! /^\/preview(?:\/|$)/.test(target.pathname)) {
                    throw new Error('Only an authenticated public preview can be compared.')
                }
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                const response = await fetch(element.dataset.previewRenderUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        path: target.pathname,
                        profile: this.selectedProfile,
                        orientation: this.rotated ? 'rotated' : 'default',
                        engine: selectedEngine,
                        scroll_y: this.snapshotScrollY,
                    }),
                    signal: controller.signal,
                })
                if (! response.ok) {
                    const payload = await response.json().catch(() => ({}))
                    throw new Error(payload.message || 'Browser rendering is unavailable.')
                }
                const overflow = response.headers.get('X-Preview-Overflow-X') === '1'
                const maxY = Number(response.headers.get('X-Preview-Scroll-Max') ?? 0)
                const scrollY = Number(response.headers.get('X-Preview-Scroll-Y') ?? 0)
                const blob = await response.blob()
                // Reveal only a decoded screenshot; even an invalid renderer
                // response must leave the live iframe completely usable.
                const imageUrl = URL.createObjectURL(blob)
                try {
                    const picture = new Image()
                    picture.src = imageUrl
                    await picture.decode()
                } catch (error) {
                    URL.revokeObjectURL(imageUrl)
                    throw new Error('The browser comparison could not be displayed.')
                }
                if (controller.signal.aborted || revision !== this.snapshotRequest) {
                    URL.revokeObjectURL(imageUrl)
                    return
                }
                this.snapshotUrl = imageUrl
                this.comparisonUnavailable = false
                this.horizontalOverflow = overflow
                this.snapshotScrollMax = Number.isFinite(maxY) ? Math.max(0, maxY) : 0
                this.snapshotScrollY = Number.isFinite(scrollY) ? Math.max(0, scrollY) : 0
            } catch (error) {
                if (controller.signal.aborted || revision !== this.snapshotRequest) return
                // Keep the interactive page visible. An unavailable renderer
                // must never turn the preview into an empty white screenshot.
                this.comparisonUnavailable = true
                this.releaseSnapshot()
                this.updateViewportDiagnostics()
            } finally {
                if (revision === this.snapshotRequest) {
                    this.snapshotLoading = false
                    this.snapshotAbort = null
                }
            }
        },
        refreshPreview(detail = {}) {
            const frame = this.$refs.frame
            if (! frame || frame.tagName !== 'IFRAME') {
                this.scheduleFit()
                return
            }

            // Gallery selections can change the URL without remounting the
            // preview's device/zoom state; Appearance edits reload the current.
            const source = typeof detail?.url === 'string' && detail.url !== ''
                ? detail.url : frame.src
            if (typeof detail?.title === 'string' && detail.title !== '') frame.title = detail.title
            const url = new URL(source, window.location.origin)
            url.searchParams.set('_appearance', Date.now().toString())
            this.currentUrl = url.toString()
            this.snapshotAbort?.abort()
            this.snapshotLoading = false
            this.releaseSnapshot()
            this.snapshotScrollY = 0
            this.snapshotScrollMax = 0
            frame.src = this.currentUrl
            // The new iframe load is the one refresh boundary for both
            // interactive and browser-engine views; don't double-request.

        },
    };
}
