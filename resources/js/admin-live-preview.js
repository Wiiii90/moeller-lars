// Shared Gallery/General Preview: actual responsive layout viewports, with
// browser-local device preference and a single interactive Public frame.
export function adminLivePreview(element) {
    const profiles = JSON.parse(element.dataset.previewProfiles || '{}')
    const preferenceKey = 'moeller-lars:admin-live-preview-device'
    return {
        resizeObserver: null,
        fitFrame: null,
        frameShortcuts: [],
        diagnosticsFrame: null,
        pointerInside: false,
        previewDevice: 'desktop',
        selectedProfile: 'current-window',
        rotated: false,
        currentUrl: element.dataset.previewUrl || '',
        horizontalOverflow: false,
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
            // A device preference belongs to the artist's browser tab, not to
            // a Gallery or a published site configuration.
            try {
                const saved = JSON.parse(sessionStorage.getItem(preferenceKey) || 'null')
                if (saved && Object.hasOwn(profiles, saved.profile)) {
                    this.selectedProfile = saved.profile
                    this.previewDevice = profiles[saved.profile].category
                    this.rotated = saved.profile !== 'current-window' && saved.rotated === true
                }
            } catch (_) {
                // Private browsing may deny storage; the live preview still works.
            }
            this.$nextTick(() => {
                this.resizeObserver = new ResizeObserver(() => this.scheduleFit())
                if (this.$refs.viewport) this.resizeObserver.observe(this.$refs.viewport)
                this.scheduleFit()
            })
        },
        saveDevicePreference() {
            try {
                sessionStorage.setItem(preferenceKey, JSON.stringify({
                    profile: this.selectedProfile,
                    rotated: this.rotated,
                }))
            } catch (_) {
                // Storage is optional, never a prerequisite for preview.
            }
        },
        destroy() {
            this.resizeObserver?.disconnect()
            if (this.fitFrame !== null) cancelAnimationFrame(this.fitFrame)
            if (this.diagnosticsFrame !== null) cancelAnimationFrame(this.diagnosticsFrame)
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
        scheduleDiagnostics() {
            if (this.diagnosticsFrame !== null) return
            this.diagnosticsFrame = requestAnimationFrame(() => {
                this.diagnosticsFrame = null
                this.updateViewportDiagnostics()
            })
        },
        selectProfile(value) {
            if (! Object.hasOwn(profiles, value)) return
            this.selectedProfile = value
            this.previewDevice = profiles[value].category
            this.rotated = false
            this.saveDevicePreference()
            this.changeViewport()
        },
        onFrameLoad() {
            this.scheduleFit()
            this.bindFrameShortcuts()
            this.scheduleDiagnostics()
        },
        changeViewport() {
            this.cancelPanGesture()
            this.zoom = this.minZoom
            this.panX = 0
            this.panY = 0
            this.panMode = false
            this.spacePan = false
            this.$nextTick(() => this.scheduleFit())
        },
        rotateViewport() {
            if (this.selectedProfile === 'current-window') return
            this.rotated = ! this.rotated
            this.saveDevicePreference()
            this.changeViewport()
        },
        selectedProfileLabel() {
            return profiles[this.selectedProfile]?.label ?? 'Current window'
        },
        viewportSize() {
            const profile = profiles[this.selectedProfile] ?? profiles['current-window']
            if (profile.width === null || profile.height === null) {
                // Match the Admin browser viewport, not the tiny Stage area.
                return [Math.max(1, window.innerWidth || 1280), Math.max(1, window.innerHeight || 800)]
            }
            return this.rotated
                ? [profile.height, profile.width]
                : [profile.width, profile.height]
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
            this.clampPan()
            this.applyPreviewTransform()
            this.scheduleDiagnostics()
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
            const changed = () => this.scheduleDiagnostics()
            documentRef.addEventListener('keydown', down)
            documentRef.addEventListener('keyup', up)
            // Recheck after actual iframe reflow, media completion and fonts.
            documentRef.addEventListener('load', changed, true)
            windowRef.addEventListener('resize', changed)
            windowRef.addEventListener('blur', blur)
            const observer = typeof windowRef.ResizeObserver === 'function'
                ? new windowRef.ResizeObserver(changed) : null
            observer?.observe(documentRef.documentElement)
            if (documentRef.body) observer?.observe(documentRef.body)
            documentRef.fonts?.ready.then(() => {
                if (this.$refs.frame?.contentDocument === documentRef) this.scheduleDiagnostics()
            }).catch(() => {})
            this.frameShortcuts.push(
                () => documentRef.removeEventListener('keydown', down),
                () => documentRef.removeEventListener('keyup', up),
                () => documentRef.removeEventListener('load', changed, true),
                () => windowRef.removeEventListener('resize', changed),
                () => windowRef.removeEventListener('blur', blur),
                () => observer?.disconnect(),
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
            if (! frame || frame.tagName !== 'IFRAME') {
                this.horizontalOverflow = false
                return
            }
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
            this.unbindFrameShortcuts()
            this.horizontalOverflow = false
            frame.src = this.currentUrl
        },
    };
}
