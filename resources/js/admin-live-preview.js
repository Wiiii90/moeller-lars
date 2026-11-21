// Shared General/Gallery/Journal Preview: actual responsive layout viewports, with
// browser-local device preference and a single interactive Public frame.
export function adminLivePreview(element) {
    const profiles = JSON.parse(element.dataset.previewProfiles || '{}')
    const preferenceKey = 'moeller-lars:admin-live-preview-device'
    return {
        resizeObserver: null,
        fitFrame: null,
        frameShortcuts: [],
        diagnosticsFrame: null,
        previewDevice: 'desktop',
        selectedProfile: 'current-window',
        rotated: false,
        currentUrl: element.dataset.previewUrl || '',
        horizontalOverflow: false,
        fitScale: 1,
        zoom: 1,
        minZoom: 1,
        maxZoom: 3,
        dragging: false,
        panCandidate: null,
        panSuppressClick: false,
        panCaptureTarget: null,
        pointerId: null,
        panGestureCleanup: null,
        dragLastX: 0,
        dragLastY: 0,
        dragScrollRoot: null,
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
        // The iframe element's src does not track navigation inside Public.
        // Read only same-origin private-preview destinations as current targets.
        viewedPreviewUrl() {
            try {
                const value = new URL(this.$refs.frame?.contentWindow?.location.href)
                if (value.origin === window.location.origin
                    && (value.pathname === '/preview' || value.pathname.startsWith('/preview/'))) {
                    return value.toString()
                }
            } catch (_) {
                // An unloaded or cross-origin frame has no readable local URL.
            }
            return null
        },
        onFrameLoad() {
            this.currentUrl = this.viewedPreviewUrl() || this.currentUrl
            this.scheduleFit()
            this.bindFrameShortcuts()
            this.scheduleDiagnostics()
        },
        changeViewport() {
            this.cancelPanGesture()
            this.zoom = this.minZoom
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
        fitPreview(anchor = null) {
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

            // Apply a requested pointer anchor in this same layout pass.
            // Re-centering first and correcting a second time can cause the
            // scaled iframe to visibly jump away from the cursor.
            viewport.scrollLeft = anchor
                ? anchor.x * renderedWidth - anchor.screenX
                : (renderedWidth > width ? centerX * renderedWidth - width / 2 : 0)
            viewport.scrollTop = anchor
                ? anchor.y * renderedHeight - anchor.screenY
                : (renderedHeight > height ? centerY * renderedHeight - height / 2 : 0)
            this.scheduleDiagnostics()
        },
        resetZoom() {
            this.cancelPanGesture()
            this.touchPanStart = null
            this.zoom = this.minZoom
            this.fitPreview()
        },
        changeZoom(step) {
            this.setZoom(Math.round((this.zoom + step) * 100) / 100)
        },
        setZoom(requestedZoom, point = null) {
            const nextZoom = Math.max(this.minZoom, Math.min(this.maxZoom, requestedZoom))
            if (! Number.isFinite(nextZoom) || nextZoom === this.zoom) return
            if (nextZoom <= this.minZoom) {
                this.resetZoom()
                return
            }

            const viewport = this.$refs.viewport
            const surface = this.$refs.surface
            let anchor = null
            if (point && viewport && surface) {
                const oldSurface = surface.getBoundingClientRect()
                const oldViewport = viewport.getBoundingClientRect()
                if (oldSurface.width > 0 && oldSurface.height > 0
                    && oldViewport.width > 0 && oldViewport.height > 0) {
                    // getBoundingClientRect() uses actual screen pixels,
                    // including transforms on the enclosing Admin Stage.
                    // Scroll positions use layout CSS pixels; convert between
                    // the coordinate systems before selecting the zoom target.
                    const horizontalScale = viewport.clientWidth / oldViewport.width
                    const verticalScale = viewport.clientHeight / oldViewport.height
                    anchor = {
                        x: Math.max(0, Math.min(1, (point.x - oldSurface.left) / oldSurface.width)),
                        y: Math.max(0, Math.min(1, (point.y - oldSurface.top) / oldSurface.height)),
                        screenX: (point.x - oldViewport.left) * horizontalScale - viewport.clientLeft,
                        screenY: (point.y - oldViewport.top) * verticalScale - viewport.clientTop,
                    }
                }
            }

            this.zoom = nextZoom
            this.fitPreview(anchor)
        },
        wheelPixels(event, delta) {
            const unit = event.deltaMode === 1 ? 16
                : (event.deltaMode === 2 ? (this.$refs.viewport?.clientHeight || 800) : 1)
            return delta * unit
        },
        zoomWithWheel(event, inFrame = false) {
            // Native wheel scrolling must reach the Public page even at 100%.
            // Shift + wheel is reserved only inside this Preview for zoom.
            // Ctrl/Command/Alt keep their browser and operating-system roles.
            if (! event.shiftKey || event.ctrlKey || event.metaKey || event.altKey || ! event.cancelable) return
            const rawDelta = event.deltaY || event.deltaX
            if (! rawDelta) return
            event.preventDefault()

            // Browsers may remap Shift + wheel from deltaY to deltaX.
            // A notch is distinct, while fine trackpad deltas are smoother.
            const delta = Math.max(-160, Math.min(160, this.wheelPixels(event, rawDelta)))
            this.setZoom(this.zoom * Math.exp(-delta * 0.002), this.previewPoint(event, inFrame))
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

            // The Public body carries the configurable fixed background.
            // When its iframe is scaled, some browsers expose the white
            // root canvas below the initially painted viewport on scroll.
            // Scope the matching root backing to this embedded Preview only.
            if (documentRef.body?.classList.contains('public-site')) {
                documentRef.documentElement.classList.add('public-preview--embedded')
                this.frameShortcuts.push(() => {
                    documentRef.documentElement.classList.remove('public-preview--embedded')
                })
            }

            const blur = () => this.cancelPanGesture()
            const changed = () => this.scheduleDiagnostics()
            const frameWheel = (event) => this.zoomWithWheel(event, true)
            const frameDown = (event) => this.startPan(event, true)
            const frameClick = (event) => this.suppressPanClick(event)
            const frameTouchStart = (event) => this.startTouchPan(event, true)
            const frameTouchMove = (event) => this.moveTouchPan(event, true)
            const frameTouchEnd = (event) => this.endTouchPan(event)
            const preventMiddleAutoscroll = (event) => {
                if (event.button === 1 && (this.zoom > this.minZoom || this.previewScrollRoot())) {
                    event.preventDefault()
                }
            }
            documentRef.addEventListener('wheel', frameWheel, { capture: true, passive: false })
            documentRef.addEventListener('pointerdown', frameDown, true)
            documentRef.addEventListener('click', frameClick, true)
            documentRef.addEventListener('mousedown', preventMiddleAutoscroll, true)
            documentRef.addEventListener('auxclick', preventMiddleAutoscroll, true)
            documentRef.addEventListener('touchstart', frameTouchStart, { passive: true })
            documentRef.addEventListener('touchmove', frameTouchMove, { passive: false })
            documentRef.addEventListener('touchend', frameTouchEnd)
            documentRef.addEventListener('touchcancel', frameTouchEnd)
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
                () => documentRef.removeEventListener('wheel', frameWheel, true),
                () => documentRef.removeEventListener('pointerdown', frameDown, true),
                () => documentRef.removeEventListener('click', frameClick, true),
                () => documentRef.removeEventListener('mousedown', preventMiddleAutoscroll, true),
                () => documentRef.removeEventListener('auxclick', preventMiddleAutoscroll, true),
                () => documentRef.removeEventListener('touchstart', frameTouchStart),
                () => documentRef.removeEventListener('touchmove', frameTouchMove),
                () => documentRef.removeEventListener('touchend', frameTouchEnd),
                () => documentRef.removeEventListener('touchcancel', frameTouchEnd),
                () => documentRef.removeEventListener('load', changed, true),
                () => windowRef.removeEventListener('resize', changed),
                () => windowRef.removeEventListener('blur', blur),
                () => observer?.disconnect(),
            )
        },
        previewPoint(event, inFrame) {
            if (! inFrame) return { x: event.clientX, y: event.clientY }
            const frame = this.$refs.frame
            const rect = frame?.getBoundingClientRect()
            if (! rect || ! frame.clientWidth || ! frame.clientHeight) {
                return { x: event.clientX, y: event.clientY }
            }
            // Pointer coordinates inside the iframe are unscaled CSS pixels.
            // Its rendered rect also includes ancestor transforms, which the
            // previous fitScale*zoom approximation did not account for.
            return {
                x: rect.left + event.clientX * (rect.width / frame.clientWidth),
                y: rect.top + event.clientY * (rect.height / frame.clientHeight),
            }
        },
        previewScrollRoot() {
            const frame = this.$refs.frame
            if (! frame || frame.tagName !== 'IFRAME') return null
            try {
                const documentRef = frame.contentDocument
                const root = documentRef?.scrollingElement
                if (! root) return null
                const scrollHeight = Math.max(root.scrollHeight, documentRef.body?.scrollHeight || 0)
                const scrollWidth = Math.max(root.scrollWidth, documentRef.body?.scrollWidth || 0)
                return scrollHeight > root.clientHeight + 1 || scrollWidth > root.clientWidth + 1
                    ? root : null
            } catch (_) {
                return null
            }
        },
        scrollPreviewBy(deltaX, deltaY, scrollRoot = this.previewScrollRoot()) {
            const viewport = this.$refs.viewport
            if (! viewport) return

            // Consume gesture movement in the zoomed outer canvas first.
            // The remaining distance scrolls the actual Public document,
            // making content below the device's first viewport reachable.
            const oldX = viewport.scrollLeft
            const oldY = viewport.scrollTop
            viewport.scrollLeft += deltaX
            viewport.scrollTop += deltaY
            if (! scrollRoot) return

            const remainderX = deltaX - (viewport.scrollLeft - oldX)
            const remainderY = deltaY - (viewport.scrollTop - oldY)
            const scale = this.fitScale * this.zoom
            if (! Number.isFinite(scale) || scale <= 0) return
            scrollRoot.scrollLeft += remainderX / scale
            scrollRoot.scrollTop += remainderY / scale
        },
        startPan(event, inFrame = false) {
            // The primary-button alternative makes Mac trackpads usable
            // without a middle button. Option/Alt + a *drag* pans; a plain
            // Option-click retains its native browser behavior (including
            // Safari link downloads). Never override Command/Control clicks.
            const middle = event.button === 1
            const optionDrag = event.button === 0 && event.altKey
                && ! event.ctrlKey && ! event.metaKey && ! event.shiftKey
                && event.pointerType !== 'touch'
            if (this.dragging || this.panCandidate) return
            this.panSuppressClick = false
            if (! middle && ! optionDrag) return

            const viewport = this.$refs.viewport
            const scrollRoot = this.previewScrollRoot()
            if (! viewport || (this.zoom <= this.minZoom && ! scrollRoot)) return

            const point = this.previewPoint(event, inFrame)
            this.pointerId = event.pointerId
            this.dragLastX = point.x
            this.dragLastY = point.y
            this.dragScrollRoot = scrollRoot

            // Option + primary-button drag is a pending gesture until a
            // minimum movement is measured. Pointer capture and click
            // suppression must not apply to a stationary Option-click.
            if (optionDrag) {
                this.panCandidate = { x: point.x, y: point.y }
            } else {
                event.preventDefault()
                this.dragging = true
            }

            // The iframe is a separate document; listen on both sides so
            // a gesture can cross its border without losing the pointer.
            const frameDocument = this.$refs.frame?.tagName === 'IFRAME'
                ? this.$refs.frame.contentDocument : null
            const moveParent = (movement) => this.movePan(movement)
            const moveFrame = (movement) => this.movePan(movement, true)
            const finish = (release) => this.endPan(release)
            const stopNativeDrag = (dragEvent) => {
                if (this.panCandidate || this.dragging) dragEvent.preventDefault()
            }
            window.addEventListener('pointermove', moveParent)
            window.addEventListener('pointerup', finish)
            window.addEventListener('pointercancel', finish)
            window.addEventListener('blur', finish)
            frameDocument?.addEventListener('pointermove', moveFrame)
            frameDocument?.addEventListener('pointerup', finish)
            frameDocument?.addEventListener('pointercancel', finish)
            frameDocument?.addEventListener('dragstart', stopNativeDrag, true)

            const capture = inFrame ? frameDocument?.documentElement : viewport
            this.panCaptureTarget = capture
            if (middle) {
                try { capture?.setPointerCapture?.(event.pointerId) } catch (_) {}
            }
            this.panGestureCleanup = () => {
                window.removeEventListener('pointermove', moveParent)
                window.removeEventListener('pointerup', finish)
                window.removeEventListener('pointercancel', finish)
                window.removeEventListener('blur', finish)
                frameDocument?.removeEventListener('pointermove', moveFrame)
                frameDocument?.removeEventListener('pointerup', finish)
                frameDocument?.removeEventListener('pointercancel', finish)
                frameDocument?.removeEventListener('dragstart', stopNativeDrag, true)
                try {
                    if (capture?.hasPointerCapture?.(event.pointerId)) capture.releasePointerCapture(event.pointerId)
                } catch (_) {}
            }
        },
        movePan(event, inFrame = false) {
            if (event.pointerId !== this.pointerId || (! this.dragging && ! this.panCandidate)) return
            const viewport = this.$refs.viewport
            if (! viewport) return

            const point = this.previewPoint(event, inFrame)
            if (this.panCandidate) {
                if (Math.hypot(point.x - this.panCandidate.x, point.y - this.panCandidate.y) < 5) return
                this.panCandidate = null
                this.dragging = true
                this.panSuppressClick = true
                try { this.panCaptureTarget?.setPointerCapture?.(event.pointerId) } catch (_) {}
            }

            event.preventDefault()
            const rect = viewport.getBoundingClientRect()
            if (! rect.width || ! rect.height) return
            const deltaX = (this.dragLastX - point.x) * viewport.clientWidth / rect.width
            const deltaY = (this.dragLastY - point.y) * viewport.clientHeight / rect.height
            this.dragLastX = point.x
            this.dragLastY = point.y
            this.scrollPreviewBy(deltaX, deltaY, this.dragScrollRoot)
        },
        endPan(event) {
            if ((! this.dragging && ! this.panCandidate)
                || (event?.pointerId !== undefined && event.pointerId !== this.pointerId)) return
            this.dragging = false
            this.panCandidate = null
            this.panCaptureTarget = null
            this.pointerId = null
            this.dragScrollRoot = null
            this.panGestureCleanup?.()
            this.panGestureCleanup = null
        },
        cancelPanGesture() {
            this.endPan(null)
        },
        suppressPanClick(event) {
            // A native mouse click after a completed pan must not activate
            // a Public link. Keyboard-generated clicks (detail === 0)
            // remain accessible even if the pointer emitted no click.
            if (! this.panSuppressClick || event.detail === 0) return
            this.panSuppressClick = false
            event.preventDefault()
            event.stopImmediatePropagation()
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
            this.touchPanStart = { x: point.x, y: point.y }
        },
        moveTouchPan(event, inFrame = false) {
            if (this.zoom <= this.minZoom || event.touches.length !== 2) return
            if (! this.touchPanStart) this.startTouchPan(event, inFrame)
            if (! this.touchPanStart) return
            event.preventDefault()
            const point = this.touchPoint(event, inFrame)
            const viewport = this.$refs.viewport
            if (! point || ! viewport) return
            const rect = viewport.getBoundingClientRect()
            if (! rect.width || ! rect.height) return
            const deltaX = (this.touchPanStart.x - point.x) * viewport.clientWidth / rect.width
            const deltaY = (this.touchPanStart.y - point.y) * viewport.clientHeight / rect.height
            this.touchPanStart = { x: point.x, y: point.y }
            this.scrollPreviewBy(deltaX, deltaY)
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

            // A Gallery/Journal selection names its target explicitly. With
            // Auto-focus off, refresh the actual navigated Public preview,
            // rather than the iframe element's outdated initial src.
            const source = typeof detail?.url === 'string' && detail.url !== ''
                ? detail.url : (this.viewedPreviewUrl() || frame.src)
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
