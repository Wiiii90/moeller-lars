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
        spacePan: false,
        dragging: false,
        pointerId: null,
        panGestureCleanup: null,
        dragStartX: 0,
        dragStartY: 0,
        dragStartScrollX: 0,
        dragStartScrollY: 0,
        touchPanStart: null,
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
            this.touchPanStart = null
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
            this.spacePan = false
            this.touchPanStart = null
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

            const [targetWidth, targetHeight] = this.viewportSize()
            const style = getComputedStyle(viewport)
            const width = viewport.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
            const height = viewport.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)
            if (width <= 0 || height <= 0) return

            // Preserve the same logical point while zooming or resizing.
            // Native overflow scrolling owns the bounds: a centered, oversized
            // transform inside a clipped Stage left unreachable negative space.
            const oldWidth = surface.offsetWidth
            const oldHeight = surface.offsetHeight
            const centerX = oldWidth > width ? (viewport.scrollLeft + width / 2) / oldWidth : 0.5
            const centerY = oldHeight > height ? (viewport.scrollTop + height / 2) / oldHeight : 0.5

            const scale = Math.min(1, width / targetWidth, height / targetHeight)
            if (! Number.isFinite(scale) || scale <= 0) return
            this.fitScale = scale
            this.maxZoom = Math.max(3, Math.ceil(1 / scale))
            const renderedWidth = targetWidth * scale * this.zoom
            const renderedHeight = targetHeight * scale * this.zoom
            surface.style.width = `${renderedWidth}px`
            surface.style.height = `${renderedHeight}px`
            page.style.width = `${renderedWidth}px`
            page.style.height = `${renderedHeight}px`
            frame.style.width = `${targetWidth}px`
            frame.style.height = `${targetHeight}px`
            frame.style.left = '0'
            frame.style.top = '0'
            frame.style.transformOrigin = 'top left'
            frame.style.transform = `scale(${scale * this.zoom})`

            viewport.scrollLeft = renderedWidth > width ? centerX * renderedWidth - width / 2 : 0
            viewport.scrollTop = renderedHeight > height ? centerY * renderedHeight - height / 2 : 0
            this.scheduleDiagnostics()
        },
        resetZoom() {
            this.cancelPanGesture()
            this.touchPanStart = null
            this.zoom = this.minZoom
            this.spacePan = false
            this.fitPreview()
        },
        changeZoom(step) {
            const nextZoom = Math.max(this.minZoom, Math.min(this.maxZoom, Math.round((this.zoom + step) * 100) / 100))
            if (nextZoom === this.zoom) return
            if (nextZoom === this.minZoom) {
                this.resetZoom()
                return
            }
            this.zoom = nextZoom
            this.fitPreview()
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
            this.cancelPanGesture()
        },
        cancelSpacePan() {
            if (! this.spacePan) return

            this.spacePan = false
            this.cancelPanGesture()
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
            const frameDown = (event) => this.startPan(event, true)
            const frameTouchStart = (event) => this.startTouchPan(event, true)
            const frameTouchMove = (event) => this.moveTouchPan(event, true)
            const frameTouchEnd = (event) => this.endTouchPan(event)
            const preventMiddleAutoscroll = (event) => {
                if (event.button === 1 && this.zoom > this.minZoom) event.preventDefault()
            }
            documentRef.addEventListener('pointerdown', frameDown, true)
            documentRef.addEventListener('mousedown', preventMiddleAutoscroll, true)
            documentRef.addEventListener('auxclick', preventMiddleAutoscroll, true)
            documentRef.addEventListener('touchstart', frameTouchStart, { passive: true })
            documentRef.addEventListener('touchmove', frameTouchMove, { passive: false })
            documentRef.addEventListener('touchend', frameTouchEnd)
            documentRef.addEventListener('touchcancel', frameTouchEnd)
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
                () => documentRef.removeEventListener('pointerdown', frameDown, true),
                () => documentRef.removeEventListener('mousedown', preventMiddleAutoscroll, true),
                () => documentRef.removeEventListener('auxclick', preventMiddleAutoscroll, true),
                () => documentRef.removeEventListener('touchstart', frameTouchStart),
                () => documentRef.removeEventListener('touchmove', frameTouchMove),
                () => documentRef.removeEventListener('touchend', frameTouchEnd),
                () => documentRef.removeEventListener('touchcancel', frameTouchEnd),
                () => documentRef.removeEventListener('keydown', down),
                () => documentRef.removeEventListener('keyup', up),
                () => documentRef.removeEventListener('load', changed, true),
                () => windowRef.removeEventListener('resize', changed),
                () => windowRef.removeEventListener('blur', blur),
                () => observer?.disconnect(),
            )
        },
        previewPoint(event, inFrame) {
            if (! inFrame) return { x: event.clientX, y: event.clientY }
            const rect = this.$refs.frame?.getBoundingClientRect()
            const scale = this.fitScale * this.zoom
            return {
                x: (rect?.left ?? 0) + event.clientX * scale,
                y: (rect?.top ?? 0) + event.clientY * scale,
            }
        },
        startPan(event, inFrame = false) {
            if (this.zoom <= this.minZoom || this.dragging
                || (event.button !== 1 && (! this.spacePan || event.button !== 0))) return

            const viewport = this.$refs.viewport
            if (! viewport) return
            event.preventDefault()
            const point = this.previewPoint(event, inFrame)
            this.dragging = true
            this.pointerId = event.pointerId
            this.dragStartX = point.x
            this.dragStartY = point.y
            this.dragStartScrollX = viewport.scrollLeft
            this.dragStartScrollY = viewport.scrollTop

            // An embedded iframe owns another document. Capture both event
            // paths only during the gesture; ordinary clicks stay untouched.
            const frameDocument = this.$refs.frame?.tagName === 'IFRAME'
                ? this.$refs.frame.contentDocument : null
            const moveParent = (movement) => this.movePan(movement)
            const moveFrame = (movement) => this.movePan(movement, true)
            const finish = (release) => this.endPan(release)
            window.addEventListener('pointermove', moveParent)
            window.addEventListener('pointerup', finish)
            window.addEventListener('pointercancel', finish)
            window.addEventListener('blur', finish)
            frameDocument?.addEventListener('pointermove', moveFrame)
            frameDocument?.addEventListener('pointerup', finish)
            frameDocument?.addEventListener('pointercancel', finish)
            const capture = inFrame ? frameDocument?.documentElement : viewport
            try { capture?.setPointerCapture?.(event.pointerId) } catch (_) {}
            this.panGestureCleanup = () => {
                window.removeEventListener('pointermove', moveParent)
                window.removeEventListener('pointerup', finish)
                window.removeEventListener('pointercancel', finish)
                window.removeEventListener('blur', finish)
                frameDocument?.removeEventListener('pointermove', moveFrame)
                frameDocument?.removeEventListener('pointerup', finish)
                frameDocument?.removeEventListener('pointercancel', finish)
                try {
                    if (capture?.hasPointerCapture?.(event.pointerId)) capture.releasePointerCapture(event.pointerId)
                } catch (_) {}
            }
        },
        movePan(event, inFrame = false) {
            if (! this.dragging || event.pointerId !== this.pointerId) return
            const viewport = this.$refs.viewport
            if (! viewport) return
            event.preventDefault()
            const point = this.previewPoint(event, inFrame)
            viewport.scrollLeft = this.dragStartScrollX - (point.x - this.dragStartX)
            viewport.scrollTop = this.dragStartScrollY - (point.y - this.dragStartY)
        },
        endPan(event) {
            if (! this.dragging || (event?.pointerId !== undefined && event.pointerId !== this.pointerId)) return
            this.dragging = false
            this.pointerId = null
            this.panGestureCleanup?.()
            this.panGestureCleanup = null
        },
        cancelPanGesture() {
            this.endPan(null)
        },
        touchPoint(event, inFrame) {
            const first = event.touches[0]
            const second = event.touches[1]
            if (! first || ! second) return null
            return this.previewPoint({
                clientX: (first.clientX + second.clientX) / 2,
                clientY: (first.clientY + second.clientY) / 2,
            }, inFrame)
        },
        startTouchPan(event, inFrame = false) {
            if (this.zoom <= this.minZoom || event.touches.length !== 2) return
            const point = this.touchPoint(event, inFrame)
            const viewport = this.$refs.viewport
            if (! point || ! viewport) return
            this.touchPanStart = {
                x: point.x,
                y: point.y,
                scrollX: viewport.scrollLeft,
                scrollY: viewport.scrollTop,
            }
        },
        moveTouchPan(event, inFrame = false) {
            if (this.zoom <= this.minZoom || event.touches.length !== 2) return
            if (! this.touchPanStart) this.startTouchPan(event, inFrame)
            if (! this.touchPanStart) return
            event.preventDefault()
            const point = this.touchPoint(event, inFrame)
            const viewport = this.$refs.viewport
            if (! point || ! viewport) return
            viewport.scrollLeft = this.touchPanStart.scrollX - (point.x - this.touchPanStart.x)
            viewport.scrollTop = this.touchPanStart.scrollY - (point.y - this.touchPanStart.y)
        },
        endTouchPan(event) {
            if (event.touches.length !== 2) this.touchPanStart = null
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
            this.cancelPanGesture()
            this.touchPanStart = null
            this.unbindFrameShortcuts()
            this.horizontalOverflow = false
            frame.src = this.currentUrl
        },
    };
}
