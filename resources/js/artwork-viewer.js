import { trackMatomoEvent } from './matomo.js';

export const MIN_SCALE = 1;
export const MAX_SCALE = 8;
export const PAN_OVERSCROLL = 0;
export const MIN_ATTENTION_MS = 3000;

export function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

export function meaningfulAttentionSeconds(milliseconds, minimum = MIN_ATTENTION_MS) {
    const duration = Number(milliseconds);
    if (!Number.isFinite(duration) || duration < minimum) return null;

    return Math.max(1, Math.round(duration / 1000));
}

export function calculatePanBounds(imageWidth, imageHeight, stageWidth, stageHeight, scale, overscroll = 0) {
    const allowance = scale > MIN_SCALE ? Math.max(0, overscroll) : 0;

    return {
        maxX: Math.max(0, (imageWidth * scale - stageWidth) / 2 + allowance),
        maxY: Math.max(0, (imageHeight * scale - stageHeight) / 2 + allowance),
    };
}

export function clampPan(x, y, bounds) {
    return {
        x: clamp(x, -bounds.maxX, bounds.maxX),
        y: clamp(y, -bounds.maxY, bounds.maxY),
    };
}

export function zoomAroundPoint(state, nextScale, pointX, pointY) {
    const scale = clamp(nextScale, MIN_SCALE, MAX_SCALE);
    const localX = (pointX - state.x) / state.scale;
    const localY = (pointY - state.y) / state.scale;

    return {
        scale,
        x: pointX - localX * scale,
        y: pointY - localY * scale,
    };
}

export function pinchFromGestureStart(state, nextScale, startPointX, startPointY, currentPointX, currentPointY) {
    const scale = clamp(nextScale, MIN_SCALE, MAX_SCALE);
    const localX = (startPointX - state.x) / state.scale;
    const localY = (startPointY - state.y) / state.scale;

    return {
        scale,
        x: currentPointX - localX * scale,
        y: currentPointY - localY * scale,
    };
}

export function adjacentIndex(index, direction, length) {
    if (direction !== -1 && direction !== 1) {
        throw new RangeError('direction must be -1 or 1');
    }

    const destination = index + direction;
    return destination < 0 || destination >= length ? null : destination;
}

function normalizeItem(element) {
    const key = element.dataset.viewerKey;
    const analyticsKey = element.dataset.viewerAnalyticsKey;
    const src = element.dataset.viewerSrc;
    const alt = element.dataset.viewerAlt;
    const title = element.dataset.viewerTitle;
    const page = element.dataset.viewerPage;

    if (!key || !analyticsKey || !src || alt === undefined || !title || !page) {
        throw new Error('Artwork viewer item is missing required canonical data.');
    }

    return { key, analyticsKey, src, alt, title, page };
}

export function initializeArtworkViewer(root = document) {
    const dialog = root.querySelector?.('[data-artwork-viewer]');
    if (!dialog || typeof dialog.showModal !== 'function' || dialog.dataset.viewerInitialized) {
        return;
    }
    dialog.dataset.viewerInitialized = 'true';

    const stage = dialog.querySelector('[data-viewer-stage]');
    const image = dialog.querySelector('[data-viewer-image]');
    const loading = dialog.querySelector('[data-viewer-loading]');
    const missing = dialog.querySelector('[data-viewer-missing]');
    const close = dialog.querySelector('[data-viewer-close]');
    const previous = dialog.querySelector('[data-viewer-previous]');
    const next = dialog.querySelector('[data-viewer-next]');
    const zoomOut = dialog.querySelector('[data-viewer-zoom-out]');
    const reset = dialog.querySelector('[data-viewer-reset]');
    const zoomIn = dialog.querySelector('[data-viewer-zoom-in]');
    const pageLink = dialog.querySelector('[data-viewer-page-link]');
    const title = dialog.querySelector('[data-viewer-title]');
    let items = [];
    let index = 0;
    let state = { scale: 1, x: 0, y: 0 };
    let trigger = null;
    let expectedSrc = '';
    const pointers = new Map();
    const zoomTracked = new Set();
    let dragStart = null;
    let pinchStart = null;
    let resizeFrame = null;
    let attentionKey = null;
    let attentionStartedAt = null;
    let attentionAccumulatedMs = 0;

    const currentItem = () => items[index] ?? null;
    const now = () => window.performance?.now?.() ?? Date.now();

    const resetAttention = () => {
        attentionKey = null;
        attentionStartedAt = null;
        attentionAccumulatedMs = 0;
    };

    const pauseAttention = () => {
        if (attentionStartedAt === null) return;
        attentionAccumulatedMs += Math.max(0, now() - attentionStartedAt);
        attentionStartedAt = null;
    };

    const resumeAttention = () => {
        const item = currentItem();
        if (!item || image.hidden || !dialog.open || document.visibilityState === 'hidden') return;
        if (attentionKey !== item.analyticsKey) {
            resetAttention();
            attentionKey = item.analyticsKey;
        }
        if (attentionStartedAt === null) attentionStartedAt = now();
    };

    const flushAttention = () => {
        pauseAttention();
        const seconds = meaningfulAttentionSeconds(attentionAccumulatedMs);
        if (attentionKey && seconds !== null) {
            trackMatomoEvent('Artwork', 'artwork_attention', attentionKey, seconds, root);
        }
        resetAttention();
    };

    const updateTransform = () => {
        if (!image || image.hidden || !stage) return;
        if (state.scale === 1) state = { ...state, x: 0, y: 0 };
        const bounds = calculatePanBounds(
            image.clientWidth,
            image.clientHeight,
            stage.clientWidth,
            stage.clientHeight,
            state.scale,
            PAN_OVERSCROLL,
        );
        const pan = clampPan(state.x, state.y, bounds);
        state = { ...state, ...pan };
        image.style.transform = `translate3d(${state.x}px, ${state.y}px, 0) scale(${state.scale})`;
        stage.dataset.viewerZoomed = state.scale > MIN_SCALE ? 'true' : 'false';
        zoomOut.disabled = state.scale <= MIN_SCALE;
        zoomIn.disabled = state.scale >= MAX_SCALE;
        reset.disabled = state.scale === MIN_SCALE;
        reset.textContent = state.scale === 1 ? '100%' : `${Math.round(state.scale * 100)}%`;
    };

    const resetState = () => {
        state = { scale: 1, x: 0, y: 0 };
        if (image) image.style.transform = 'translate3d(0px, 0px, 0) scale(1)';
        if (stage) stage.dataset.viewerZoomed = 'false';
        updateTransform();
    };

    const showItem = (nextIndex) => {
        const item = items[nextIndex];
        if (!item) {
            throw new RangeError('Artwork viewer index is outside the canonical sequence.');
        }
        index = nextIndex;
        state = { scale: 1, x: 0, y: 0 };
        pointers.clear();
        dragStart = null;
        pinchStart = null;
        title.textContent = item.title;
        previous.disabled = adjacentIndex(index, -1, items.length) === null;
        next.disabled = adjacentIndex(index, 1, items.length) === null;
        pageLink.hidden = false;
        pageLink.href = item.page;
        expectedSrc = item.src;
        image.removeAttribute('src');
        image.hidden = true;
        missing.hidden = true;
        loading.hidden = false;
        zoomOut.disabled = true;
        zoomIn.disabled = true;
        reset.disabled = true;
        reset.textContent = '100%';
        stage.dataset.viewerZoomed = 'false';
        image.alt = item.alt;
        image.src = item.src;
    };

    const open = (source, sequence) => {
        const normalized = Array.from(sequence.querySelectorAll('[data-artwork-viewer-item]')).map(normalizeItem);
        const start = normalized.findIndex((item) => item.key === source.dataset.viewerKey);
        if (start < 0) {
            throw new Error('Artwork viewer trigger is not present in its canonical sequence.');
        }
        trigger = source;
        items = normalized;
        zoomTracked.clear();
        resetAttention();
        showItem(start);
        trackMatomoEvent('Artwork', 'artwork_open', normalized[start].analyticsKey, null, root);
        dialog.showModal();
        close.focus();
        return true;
    };

    const trackZoomIfNeeded = () => {
        const item = currentItem();
        if (!item || state.scale <= MIN_SCALE || zoomTracked.has(item.analyticsKey)) return;
        zoomTracked.add(item.analyticsKey);
        trackMatomoEvent('Artwork', 'artwork_zoom_used', item.analyticsKey, null, root);
    };

    const changeZoom = (nextScale, pointX = 0, pointY = 0) => {
        if (image.hidden || !expectedSrc) return;
        state = zoomAroundPoint(state, nextScale, pointX, pointY);
        updateTransform();
        trackZoomIfNeeded();
    };

    const navigate = (direction, action) => {
        const destination = adjacentIndex(index, direction, items.length);
        if (destination === null) return;
        flushAttention();
        showItem(destination);
        const item = currentItem();
        trackMatomoEvent('Artwork', action, item?.analyticsKey ?? null, null, root);
    };

    const stagePoint = (event) => {
        const rect = stage.getBoundingClientRect();
        return {
            x: event.clientX - (rect.left + rect.width / 2),
            y: event.clientY - (rect.top + rect.height / 2),
        };
    };

    root.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== undefined && event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const source = event.target.closest?.('[data-artwork-viewer-trigger]');
        if (!source) return;
        const sequence = source.closest('[data-artwork-viewer-sequence]');
        if (sequence && open(source, sequence)) event.preventDefault();
    });

    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => navigate(-1, 'artwork_previous'));
    next.addEventListener('click', () => navigate(1, 'artwork_next'));
    zoomOut.addEventListener('click', () => changeZoom(state.scale / 1.25));
    zoomIn.addEventListener('click', () => changeZoom(state.scale * 1.25));
    reset.addEventListener('click', resetState);

    image.addEventListener('load', () => {
        if (image.src !== expectedSrc) return;
        loading.hidden = true;
        missing.hidden = true;
        image.hidden = false;
        resetState();
        resumeAttention();
    });
    image.addEventListener('error', () => {
        if (image.src !== expectedSrc) return;
        resetAttention();
        loading.hidden = true;
        image.hidden = true;
        missing.hidden = false;
        zoomOut.disabled = true;
        zoomIn.disabled = true;
        reset.disabled = true;
    });

    stage.addEventListener('wheel', (event) => {
        if (image.hidden || !expectedSrc) return;
        event.preventDefault();
        const point = stagePoint(event);
        changeZoom(state.scale * (event.deltaY < 0 ? 1.12 : 1 / 1.12), point.x, point.y);
    }, { passive: false });

    stage.addEventListener('dblclick', (event) => {
        if (image.hidden || !expectedSrc) return;
        event.preventDefault();
        if (state.scale > MIN_SCALE) {
            resetState();
            return;
        }
        const point = stagePoint(event);
        changeZoom(2, point.x, point.y);
    });

    stage.addEventListener('pointerdown', (event) => {
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.setPointerCapture?.(event.pointerId);
        if (pointers.size === 1) dragStart = { x: event.clientX, y: event.clientY, panX: state.x, panY: state.y };
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            const rect = stage.getBoundingClientRect();
            const midpointX = (a.x + b.x) / 2;
            const midpointY = (a.y + b.y) / 2;
            pinchStart = {
                distance: Math.hypot(b.x - a.x, b.y - a.y),
                pointX: midpointX - (rect.left + rect.width / 2),
                pointY: midpointY - (rect.top + rect.height / 2),
                state: { scale: state.scale, x: state.x, y: state.y },
            };
        }
    });
    stage.addEventListener('pointermove', (event) => {
        if (!pointers.has(event.pointerId)) return;
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        const values = [...pointers.values()];
        if (values.length >= 2 && pinchStart) {
            const [a, b] = values;
            const distance = Math.hypot(b.x - a.x, b.y - a.y);
            const midpoint = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
            const rect = stage.getBoundingClientRect();
            const pointX = midpoint.x - (rect.left + rect.width / 2);
            const pointY = midpoint.y - (rect.top + rect.height / 2);
            const nextScale = pinchStart.state.scale * distance / Math.max(1, pinchStart.distance);
            state = pinchFromGestureStart(pinchStart.state, nextScale, pinchStart.pointX, pinchStart.pointY, pointX, pointY);
            updateTransform();
            trackZoomIfNeeded();
        } else if (values.length === 1 && dragStart && state.scale > 1) {
            state.x = dragStart.panX + event.clientX - dragStart.x;
            state.y = dragStart.panY + event.clientY - dragStart.y;
            updateTransform();
        }
    });
    const releasePointer = (event) => {
        pointers.delete(event.pointerId);
        stage.releasePointerCapture?.(event.pointerId);
        if (pointers.size === 1) {
            const remaining = [...pointers.values()][0];
            dragStart = { x: remaining.x, y: remaining.y, panX: state.x, panY: state.y };
        } else {
            dragStart = null;
        }
        pinchStart = null;
    };
    stage.addEventListener('pointerup', releasePointer);
    stage.addEventListener('pointercancel', releasePointer);

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') return;
        if (event.key === 'ArrowLeft' && !previous.disabled) { event.preventDefault(); previous.click(); }
        if (event.key === 'ArrowRight' && !next.disabled) { event.preventDefault(); next.click(); }
        if (event.key === '+' || event.key === '=') { event.preventDefault(); changeZoom(state.scale * 1.25); }
        if (event.key === '-' || event.key === '_') { event.preventDefault(); changeZoom(state.scale / 1.25); }
        if (event.key === '0') { event.preventDefault(); resetState(); }
    });

    document.addEventListener('visibilitychange', () => {
        if (!dialog.open) return;
        if (document.visibilityState === 'hidden') pauseAttention();
        else resumeAttention();
    });

    dialog.addEventListener('close', () => {
        flushAttention();
        image.removeAttribute('src');
        expectedSrc = '';
        pointers.clear();
        state = { scale: 1, x: 0, y: 0 };
        loading.hidden = true;
        image.hidden = true;
        missing.hidden = true;
        stage.dataset.viewerZoomed = 'false';
        if (trigger?.isConnected && typeof trigger.focus === 'function') trigger.focus();
        trigger = null;
    });

    window.addEventListener('pagehide', () => {
        if (dialog.open) flushAttention();
    });

    const recalculate = () => {
        resizeFrame = null;
        if (dialog.open) updateTransform();
    };
    const scheduleResize = () => {
        if (resizeFrame === null) resizeFrame = requestAnimationFrame(recalculate);
    };
    window.addEventListener('resize', scheduleResize);
    window.addEventListener('orientationchange', scheduleResize);
}
