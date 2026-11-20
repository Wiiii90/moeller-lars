const openModalSelector = '.fi-modal.fi-modal-open:not(.fi-modal-click-through)';
const desktopPointer = window.matchMedia('(min-width: 46.01rem) and (pointer: fine)');
const modalSessions = new Map();
let activeGesture = null;
let frameRefreshPending = false;

function numericPx(value) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function modalForEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;
    return document.getElementById(String(id));
}

function modalWindow(modal) {
    return modal?.querySelector(':scope > .fi-modal-window-ctn > .fi-modal-window') ?? null;
}

function activeMain(modal) {
    return modal?.closest('.fi-main')
        ?? document.querySelector('.fi-main:has(.admin-workspace)')
        ?? document.querySelector('.fi-main');
}

function modalFrame(modal) {
    const main = activeMain(modal);
    if (!main) return null;

    const mainRect = main.getBoundingClientRect();
    const mainStyle = window.getComputedStyle(main);
    const topbar = document.querySelector('.fi-topbar');
    const topbarBottom = topbar
        ? Math.max(0, topbar.getBoundingClientRect().bottom)
        : numericPx(window.getComputedStyle(document.documentElement).getPropertyValue('--admin-topbar-height'));

    const padding = Math.max(16, numericPx(mainStyle.paddingLeft), numericPx(mainStyle.paddingRight));
    const inlineStart = Math.max(0, mainRect.left);
    const inlineEnd = Math.max(0, window.innerWidth - mainRect.right);
    const compact = window.matchMedia('(max-width: 46rem)').matches;
    const edge = compact ? 8 : 16;
    // A short viewport must never place the entire header below the screen.
    const blockStart = Math.min(topbarBottom, Math.max(0, window.innerHeight - edge - 120));

    // These are the same persistent root-owned variables used by the fixed
    // Filament window layer. Do not transform the Main or modal ancestors.
    const root = document.documentElement.style;
    root.setProperty('--admin-dialog-layer-inline-start', `${inlineStart}px`);
    root.setProperty('--admin-dialog-layer-inline-end', `${inlineEnd}px`);
    root.setProperty('--admin-dialog-layer-block-start', `${blockStart}px`);
    root.setProperty('--admin-dialog-layer-padding-inline', `${padding}px`);

    const left = Math.max(edge, inlineStart + padding);
    const right = Math.min(window.innerWidth - edge, window.innerWidth - inlineEnd - padding);
    const top = blockStart + edge;
    const bottom = window.innerHeight - edge;

    return {
        left,
        right: Math.max(left + 1, right),
        top,
        bottom: Math.max(top + 1, bottom),
    };
}

function ensureWindowControls(modal) {
    const win = modalWindow(modal);
    if (!win || !desktopPointer.matches) return;

    const header = win.querySelector('.fi-modal-header');
    if (header && !header.hasAttribute('tabindex')) {
        header.tabIndex = 0;
        header.title = 'Move dialog: Alt + arrow keys, or drag this header';
    }

    if (!win.querySelector('[data-admin-dialog-resize]')) {
        const grip = document.createElement('button');
        grip.type = 'button';
        grip.className = 'admin-dialog-resize-grip';
        grip.dataset.adminDialogResize = '';
        grip.setAttribute('aria-label', 'Resize dialog with arrow keys');
        grip.title = 'Resize dialog: drag this corner or use arrow keys';
        win.append(grip);
    }
}

function resetModalScroll(modal) {
    const content = modal?.querySelector('.fi-modal-content');
    if (!content) return;
    content.scrollTop = 0;
    content.scrollLeft = 0;
}

function sessionFor(modal) {
    if (!modal?.id) return null;
    let session = modalSessions.get(modal.id);
    if (!session) {
        session = { x: 0, y: 0, width: null, height: null };
        modalSessions.set(modal.id, session);
    }
    return session;
}

function applySession(modal, session) {
    if (!modal || !session) return;
    modal.classList.toggle('admin-dialog--manually-sized', session.width !== null && session.height !== null);
    const style = modal.style;
    style.setProperty('--admin-dialog-drag-x', `${session.x}px`);
    style.setProperty('--admin-dialog-drag-y', `${session.y}px`);
    if (session.width === null) style.removeProperty('--admin-dialog-manual-width');
    else style.setProperty('--admin-dialog-manual-width', `${session.width}px`);
    if (session.height === null) style.removeProperty('--admin-dialog-manual-height');
    else style.setProperty('--admin-dialog-manual-height', `${session.height}px`);
}

function clampSession(modal, session, frame) {
    const win = modalWindow(modal);
    if (!win || !session || !frame) return;

    const availableWidth = frame.right - frame.left;
    const availableHeight = frame.bottom - frame.top;
    if (session.width !== null) session.width = Math.min(session.width, availableWidth);
    if (session.height !== null) session.height = Math.min(session.height, availableHeight);
    applySession(modal, session);

    const rect = win.getBoundingClientRect();
    // Move only the window, never its fixed backdrop or its scroll-lock owner.
    const minX = frame.left - rect.left;
    const maxX = frame.right - rect.right;
    const minY = frame.top - rect.top;
    const maxY = frame.bottom - rect.bottom;

    if (minX <= maxX) session.x += Math.max(minX, Math.min(0, maxX));
    if (minY <= maxY) session.y += Math.max(minY, Math.min(0, maxY));
    applySession(modal, session);
}

function syncModalGeometry(modal, { resetScroll = false } = {}) {
    if (!modal || modal.classList.contains('fi-modal-click-through')) return;
    const frame = modalFrame(modal);
    if (!frame) return;
    ensureWindowControls(modal);
    const session = sessionFor(modal);
    // Compact/touch screens retain Filament's responsive fixed presentation.
    if (!desktopPointer.matches && session) {
        session.x = 0;
        session.y = 0;
        session.width = null;
        session.height = null;
    }
    clampSession(modal, session, frame);
    if (resetScroll) resetModalScroll(modal);
}

function syncOpenModalGeometry() {
    for (const modal of document.querySelectorAll(openModalSelector)) {
        syncModalGeometry(modal);
    }
}

function scheduleFrameRefresh() {
    if (frameRefreshPending) return;
    frameRefreshPending = true;
    window.requestAnimationFrame(() => {
        frameRefreshPending = false;
        syncOpenModalGeometry();
    });
}

// Filament retains modal opening, focus management, Escape, close/destroy,
// background scroll lock and the existing fixed full-viewport backdrop.
document.addEventListener('x-modal-opened', (event) => {
    const modal = modalForEvent(event);
    if (!modal) return;

    const fresh = !modalSessions.has(modal.id);
    syncModalGeometry(modal, { resetScroll: fresh });
    scheduleFrameRefresh();
});

document.addEventListener('modal-closed', (event) => {
    if (event.detail?.id == null) return;
    const id = String(event.detail.id);
    if (activeGesture?.modalId === id) finishGesture();
    modalSessions.delete(id);
});

function finishGesture() {
    if (!activeGesture) return;
    activeGesture = null;
    document.documentElement.classList.remove('admin-dialog--manipulating');
}

document.addEventListener('pointerdown', (event) => {
    if (!desktopPointer.matches || event.button !== 0 || activeGesture) return;
    if (!(event.target instanceof Element)) return;

    const modal = event.target.closest(openModalSelector);
    const win = modalWindow(modal);
    if (!win || !win.contains(event.target)) return;

    const rect = win.getBoundingClientRect();
    const grip = event.target.closest('[data-admin-dialog-resize]');
    const isResizeCorner = grip && win.contains(grip);
    const header = event.target.closest('.fi-modal-header');
    const isDragHeader = header && win.contains(header);
    if (!isResizeCorner && !isDragHeader) return;

    // Never take a form, close, menu, link or header-action click as a drag.
    if (!isResizeCorner && event.target.closest('button, a, input, select, textarea, label, [contenteditable], [role="button"], .fi-modal-footer, .fi-modal-close-btn')) return;

    const frame = modalFrame(modal);
    const session = sessionFor(modal);
    if (!frame || !session) return;
    activeGesture = {
        modalId: modal.id,
        pointerId: event.pointerId,
        kind: isResizeCorner ? 'resize' : 'move',
        startX: event.clientX,
        startY: event.clientY,
        initialRect: rect,
        initialX: session.x,
        initialY: session.y,
        frame,
    };

    event.preventDefault();
    document.documentElement.classList.add('admin-dialog--manipulating');
});

window.addEventListener('pointermove', (event) => {
    const gesture = activeGesture;
    if (!gesture || event.pointerId !== gesture.pointerId) return;

    const modal = document.getElementById(gesture.modalId);
    const win = modalWindow(modal);
    const session = modalSessions.get(gesture.modalId);
    const frame = gesture.frame;
    if (!win || !session || !frame) return finishGesture();

    const deltaX = event.clientX - gesture.startX;
    const deltaY = event.clientY - gesture.startY;

    if (gesture.kind === 'move') {
        const rect = win.getBoundingClientRect();
        session.x += Math.max(frame.left - rect.left, Math.min(deltaX - (session.x - gesture.initialX), frame.right - rect.right));
        session.y += Math.max(frame.top - rect.top, Math.min(deltaY - (session.y - gesture.initialY), frame.bottom - rect.bottom));
        applySession(modal, session);
        return;
    }

    resizeDialog(modal, session, frame, gesture.initialRect,
        gesture.initialRect.width + deltaX, gesture.initialRect.height + deltaY);
});

function resizeDialog(modal, session, frame, initialRect, width, height) {
    const win = modalWindow(modal);
    if (!win) return;

    const minWidth = Math.min(280, frame.right - frame.left);
    const minHeight = Math.min(160, frame.bottom - frame.top);
    session.width = Math.max(minWidth, Math.min(width, frame.right - initialRect.left));
    session.height = Math.max(minHeight, Math.min(height, frame.bottom - initialRect.top));
    applySession(modal, session);

    // Filament normally centers the grid item; retain the user's top-left
    // anchor while the bottom/right size changes.
    const resized = win.getBoundingClientRect();
    session.x += initialRect.left - resized.left;
    session.y += initialRect.top - resized.top;
    applySession(modal, session);
    clampSession(modal, session, frame);
}

document.addEventListener('keydown', (event) => {
    if (!desktopPointer.matches || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)
        || !(event.target instanceof Element)) return;

    const modal = event.target.closest(openModalSelector);
    const win = modalWindow(modal);
    const session = modalSessions.get(modal?.id);
    if (!win || !session) return;

    const isResize = event.target.matches('[data-admin-dialog-resize]');
    const isMove = event.altKey && event.target.classList.contains('fi-modal-header');
    if (!isResize && !isMove) return;

    const frame = modalFrame(modal);
    if (!frame) return;
    const delta = event.shiftKey ? 32 : 12;
    const dx = event.key === 'ArrowRight' ? delta : event.key === 'ArrowLeft' ? -delta : 0;
    const dy = event.key === 'ArrowDown' ? delta : event.key === 'ArrowUp' ? -delta : 0;
    event.preventDefault();
    event.stopPropagation();

    if (isResize) {
        const rect = win.getBoundingClientRect();
        resizeDialog(modal, session, frame, rect, rect.width + dx, rect.height + dy);
    } else {
        const rect = win.getBoundingClientRect();
        session.x += Math.max(frame.left - rect.left, Math.min(dx, frame.right - rect.right));
        session.y += Math.max(frame.top - rect.top, Math.min(dy, frame.bottom - rect.bottom));
        applySession(modal, session);
    }
});

window.addEventListener('pointerup', (event) => {
    if (activeGesture?.pointerId === event.pointerId) finishGesture();
});
window.addEventListener('pointercancel', (event) => {
    if (activeGesture?.pointerId === event.pointerId) finishGesture();
});
window.addEventListener('blur', finishGesture);

// An Action's form update may morph its window while it is open. Reapply the
// single per-modal session rather than attaching handlers to replaced nodes.
function registerMorphSync() {
    if (!window.Livewire?.hook || registerMorphSync.registered) return;
    registerMorphSync.registered = true;
    window.Livewire.hook('morph.updated', ({ el }) => {
        if (!(el instanceof Element)) return;
        if (el.closest('.fi-modal') || el.querySelector('.fi-modal.fi-modal-open')) {
            scheduleFrameRefresh();
        }
    });
    window.Livewire.hook('morph.added', ({ el }) => {
        if (el instanceof Element && (el.matches('.fi-modal') || el.querySelector('.fi-modal.fi-modal-open'))) {
            scheduleFrameRefresh();
        }
    });
}

registerMorphSync();
document.addEventListener('livewire:init', registerMorphSync, { once: true });
window.addEventListener('resize', () => {
    // The drag bounds were captured at pointerdown; discard them when the
    // viewport changes and clamp the window against the new visible frame.
    finishGesture();
    scheduleFrameRefresh();
}, { passive: true });
document.addEventListener('livewire:navigated', () => {
    finishGesture();
    modalSessions.clear();
    scheduleFrameRefresh();
});
