export const MIN_SCALE = 1;
export const MAX_SCALE = 8;

export function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

export function calculatePanBounds(imageWidth, imageHeight, stageWidth, stageHeight, scale) {
    return {
        maxX: Math.max(0, (imageWidth * scale - stageWidth) / 2),
        maxY: Math.max(0, (imageHeight * scale - stageHeight) / 2),
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

export function adjacentIndex(index, direction, length) {
    if (direction !== -1 && direction !== 1) {
        throw new RangeError('direction must be -1 or 1');
    }

    const destination = index + direction;
    return destination < 0 || destination >= length ? null : destination;
}

function normalizeItem(element) {
    const key = element.dataset.viewerKey;
    if (!key) {
        return null;
    }

    return {
        key,
        src: element.dataset.viewerSrc || '',
        alt: element.dataset.viewerAlt || '',
        title: element.dataset.viewerTitle || '',
        page: element.dataset.viewerPage || '',
    };
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
    let dragStart = null;
    let pinchStart = null;
    let resizeFrame = null;

    const updateTransform = () => {
        if (!image || image.hidden || !stage) return;
        if (state.scale === 1) state = { ...state, x: 0, y: 0 };
        const bounds = calculatePanBounds(image.clientWidth, image.clientHeight, stage.clientWidth, stage.clientHeight, state.scale);
        const pan = clampPan(state.x, state.y, bounds);
        state = { ...state, ...pan };
        image.style.transform = `translate3d(${state.x}px, ${state.y}px, 0) scale(${state.scale})`;
        zoomOut.disabled = state.scale <= MIN_SCALE;
        zoomIn.disabled = state.scale >= MAX_SCALE;
        reset.disabled = state.scale === MIN_SCALE;
        reset.textContent = state.scale === 1 ? '100%' : `${Math.round(state.scale * 100)}%`;
    };

    const resetState = () => {
        state = { scale: 1, x: 0, y: 0 };
        if (image) image.style.transform = 'translate3d(0px, 0px, 0) scale(1)';
        updateTransform();
    };

    const showItem = (nextIndex) => {
        const item = items[nextIndex];
        if (!item) return;
        index = nextIndex;
        state = { scale: 1, x: 0, y: 0 };
        pointers.clear();
        dragStart = null;
        pinchStart = null;
        title.textContent = item.title;
        previous.disabled = adjacentIndex(index, -1, items.length) === null;
        next.disabled = adjacentIndex(index, 1, items.length) === null;
        pageLink.hidden = !item.page;
        if (item.page) pageLink.href = item.page;
        expectedSrc = item.src;
        image.removeAttribute('src');
        image.hidden = true;
        missing.hidden = Boolean(item.src);
        loading.hidden = !item.src;
        zoomOut.disabled = true;
        zoomIn.disabled = true;
        reset.disabled = true;
        reset.textContent = '100%';
        if (!item.src) return;
        image.alt = item.alt;
        image.src = item.src;
    };

    const open = (source, sequence) => {
        const normalized = Array.from(sequence.querySelectorAll('[data-artwork-viewer-item]'))
            .map(normalizeItem).filter(Boolean);
        const start = normalized.findIndex((item) => item.key === source.dataset.viewerKey);
        if (start < 0) return false;
        trigger = source;
        items = normalized;
        showItem(start);
        dialog.showModal();
        close.focus();
        return true;
    };

    const changeZoom = (nextScale, pointX = 0, pointY = 0) => {
        if (image.hidden || !expectedSrc) return;
        state = zoomAroundPoint(state, nextScale, pointX, pointY);
        updateTransform();
    };

    root.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== undefined && event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const source = event.target.closest?.('[data-artwork-viewer-trigger]');
        if (!source) return;
        const sequence = source.closest('[data-artwork-viewer-sequence]');
        if (sequence && open(source, sequence)) event.preventDefault();
    });

    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => showItem(adjacentIndex(index, -1, items.length)));
    next.addEventListener('click', () => showItem(adjacentIndex(index, 1, items.length)));
    zoomOut.addEventListener('click', () => changeZoom(state.scale / 1.25));
    zoomIn.addEventListener('click', () => changeZoom(state.scale * 1.25));
    reset.addEventListener('click', resetState);

    image.addEventListener('load', () => {
        if (image.src !== expectedSrc) return;
        loading.hidden = true;
        missing.hidden = true;
        image.hidden = false;
        resetState();
    });
    image.addEventListener('error', () => {
        if (image.src !== expectedSrc) return;
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
        const rect = stage.getBoundingClientRect();
        const pointX = event.clientX - (rect.left + rect.width / 2);
        const pointY = event.clientY - (rect.top + rect.height / 2);
        changeZoom(state.scale * (event.deltaY < 0 ? 1.12 : 1 / 1.12), pointX, pointY);
    }, { passive: false });

    stage.addEventListener('pointerdown', (event) => {
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.setPointerCapture?.(event.pointerId);
        if (pointers.size === 1) dragStart = { x: event.clientX, y: event.clientY, panX: state.x, panY: state.y };
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinchStart = { distance: Math.hypot(b.x - a.x, b.y - a.y), midpoint: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, ...state };
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
            state = zoomAroundPoint(pinchStart, pinchStart.scale * distance / Math.max(1, pinchStart.distance), pointX, pointY);
            state.x += pointX - (pinchStart.midpoint.x - (rect.left + rect.width / 2));
            state.y += pointY - (pinchStart.midpoint.y - (rect.top + rect.height / 2));
            updateTransform();
        } else if (values.length === 1 && dragStart && state.scale > 1) {
            state.x = dragStart.panX + event.clientX - dragStart.x;
            state.y = dragStart.panY + event.clientY - dragStart.y;
            updateTransform();
        }
    });
    const releasePointer = (event) => {
        pointers.delete(event.pointerId);
        stage.releasePointerCapture?.(event.pointerId);
        dragStart = pointers.size === 1 ? { ...dragStart, ...[...pointers.values()][0] } : null;
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

    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        expectedSrc = '';
        pointers.clear();
        state = { scale: 1, x: 0, y: 0 };
        loading.hidden = true;
        image.hidden = true;
        missing.hidden = true;
        if (trigger?.isConnected && typeof trigger.focus === 'function') trigger.focus();
        trigger = null;
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
