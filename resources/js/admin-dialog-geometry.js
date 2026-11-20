const openModalSelector = '.fi-modal.fi-modal-open:not(.fi-modal-click-through)';
const desktopDrag = window.matchMedia('(min-width: 46.01rem) and (pointer: fine)');
const movedDialogs = new Map();
let activeDrag = null;
let syncScheduled = false;

function numericPx(value) {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function modalForEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;

    return document.getElementById(String(id));
}

function activeMain(modal) {
    return modal?.closest('.fi-main')
        ?? document.querySelector('.fi-main:has(.admin-workspace)')
        ?? document.querySelector('.fi-main');
}

function modalWindow(modal) {
    return modal?.querySelector(':scope > .fi-modal-window-ctn > .fi-modal-window') ?? null;
}

function dragBounds(modal) {
    const main = activeMain(modal);
    if (!main) return null;

    const rect = main.getBoundingClientRect();
    const style = window.getComputedStyle(main);
    const padding = Math.max(16, numericPx(style.paddingLeft), numericPx(style.paddingRight));
    const topbar = document.querySelector('.fi-topbar');
    const topbarBottom = topbar
        ? Math.max(0, topbar.getBoundingClientRect().bottom)
        : numericPx(window.getComputedStyle(document.documentElement).getPropertyValue('--admin-topbar-height'));
    const edge = 16;
    const left = Math.max(edge, rect.left + padding);
    const right = Math.min(window.innerWidth - edge, rect.right - padding);
    const bottom = window.innerHeight - edge;

    return {
        left,
        right: Math.max(left + 1, right),
        top: Math.min(topbarBottom + edge, bottom - 1),
        bottom,
    };
}

function applyMovedDialog(modal, offset) {
    if (!modal || !offset) return;

    // This is the only supplementary positioning rule. The native centered
    // Filament window remains entirely untouched until it is actually moved.
    modal.dataset.adminDialogMoved = 'true';
    modal.style.setProperty('--admin-dialog-drag-x', `${offset.x}px`);
    modal.style.setProperty('--admin-dialog-drag-y', `${offset.y}px`);
}

function clearMovedDialog(modal) {
    if (!modal) return;
    delete modal.dataset.adminDialogMoved;
    modal.style.removeProperty('--admin-dialog-drag-x');
    modal.style.removeProperty('--admin-dialog-drag-y');
}

function clampMovedDialog(modal) {
    const offset = movedDialogs.get(modal?.id);
    if (!offset) return;

    applyMovedDialog(modal, offset);
    const win = modalWindow(modal);
    const bounds = dragBounds(modal);
    if (!win || !bounds) return;

    const rect = win.getBoundingClientRect();
    // Hidden/transitional windows may report zero-size coordinates. Never
    // derive a speculative displacement from an unpainted opening frame.
    if (rect.width <= 0 || rect.height <= 0) return;

    const minX = bounds.left - rect.left;
    const maxX = bounds.right - rect.right;
    const minY = bounds.top - rect.top;
    const maxY = bounds.bottom - rect.bottom;

    offset.x += minX <= maxX ? Math.max(minX, Math.min(0, maxX)) : minX;
    offset.y += minY <= maxY ? Math.max(minY, Math.min(0, maxY)) : minY;
    applyMovedDialog(modal, offset);
}

function resetModalScroll(modal) {
    const content = modal?.querySelector('.fi-modal-content');
    if (! content) return;

    content.scrollTop = 0;
    content.scrollLeft = 0;
}

function syncModalGeometry(modal, { resetScroll = false } = {}) {
    if (! modal || modal.classList.contains('fi-modal-click-through')) return;

    const main = activeMain(modal);
    if (! main) return;

    const mainRect = main.getBoundingClientRect();
    const mainStyle = window.getComputedStyle(main);
    const topbar = document.querySelector('.fi-topbar');
    const topbarBottom = topbar
        ? Math.max(0, topbar.getBoundingClientRect().bottom)
        : numericPx(window.getComputedStyle(document.documentElement).getPropertyValue('--admin-topbar-height'));

    const inlineStart = Math.max(0, mainRect.left);
    const inlineEnd = Math.max(0, window.innerWidth - mainRect.right);
    const inlinePadding = Math.max(
        16,
        numericPx(mainStyle.paddingLeft),
        numericPx(mainStyle.paddingRight),
    );

    // Livewire may replace a mounted Action modal after a field change.
    // Document-owned frame variables survive that replacement.
    const frame = document.documentElement.style;
    frame.setProperty('--admin-dialog-layer-inline-start', `${inlineStart}px`);
    frame.setProperty('--admin-dialog-layer-inline-end', `${inlineEnd}px`);
    frame.setProperty('--admin-dialog-layer-block-start', `${Math.min(window.innerHeight, topbarBottom)}px`);
    frame.setProperty('--admin-dialog-layer-padding-inline', `${inlinePadding}px`);

    const header = modalWindow(modal)?.querySelector('.fi-modal-header');
    if (header && desktopDrag.matches && !header.hasAttribute('tabindex')) {
        header.tabIndex = 0;
        header.title = 'Drag to move dialog; Alt + arrow keys to move by keyboard';
    }

    // Never clamp an untouched dialog at open. Filament may not have painted
    // it yet, and the original grid/CSS already centers it correctly.
    clampMovedDialog(modal);

    if (resetScroll) {
        resetModalScroll(modal);
    }
}

function syncOpenModalGeometry() {
    for (const modal of document.querySelectorAll(openModalSelector)) {
        syncModalGeometry(modal);
    }
}

const openModalIds = new Set();

// The application owns only placement geometry. Every blocking Filament modal
// opened over an admin Main frame receives the same measured content bounds.
// Filament owns modal opening, document scroll lock, focus trapping, Escape,
// close/destroy and lock cleanup.
document.addEventListener('x-modal-opened', (event) => {
    const modal = modalForEvent(event);
    if (! modal) return;

    const id = String(event.detail.id);
    const isNewSession = ! openModalIds.has(id);
    openModalIds.add(id);
    syncModalGeometry(modal, { resetScroll: isNewSession });
});

document.addEventListener('modal-closed', (event) => {
    if (event.detail?.id == null) return;
    const id = String(event.detail.id);

    if (activeDrag?.id === id) finishDrag();
    openModalIds.delete(id);
    movedDialogs.delete(id);
    clearMovedDialog(document.getElementById(id));
});

function finishDrag() {
    activeDrag = null;
    document.documentElement.classList.remove('admin-dialog--dragging');
}

function clamp(value, min, max) {
    return min <= max ? Math.max(min, Math.min(value, max)) : min;
}

document.addEventListener('pointerdown', (event) => {
    if (!desktopDrag.matches || event.button !== 0 || activeDrag
        || !(event.target instanceof Element)) return;

    const header = event.target.closest('.fi-modal-header');
    const modal = event.target.closest(openModalSelector);
    const win = modalWindow(modal);
    if (!header || !win || !win.contains(header)) return;

    // Header action buttons, close controls, links and inputs must retain
    // their native click, keyboard and focus behavior.
    if (event.target.closest('button, a, input, select, textarea, label, [contenteditable], [role="button"]')) return;

    syncModalGeometry(modal);
    const rect = win.getBoundingClientRect();
    const bounds = dragBounds(modal);
    if (!bounds || rect.width <= 0 || rect.height <= 0) return;

    activeDrag = {
        id: modal.id,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        rect,
        bounds,
        initial: { ...(movedDialogs.get(modal.id) ?? { x: 0, y: 0 }) },
        started: false,
    };
    event.preventDefault();
});

window.addEventListener('pointermove', (event) => {
    const drag = activeDrag;
    if (!drag || event.pointerId !== drag.pointerId) return;

    const dx = event.clientX - drag.startX;
    const dy = event.clientY - drag.startY;
    if (!drag.started && Math.hypot(dx, dy) < 3) return;

    drag.started = true;
    const modal = document.getElementById(drag.id);
    if (!modal) return finishDrag();

    const offset = {
        x: drag.initial.x + clamp(dx, drag.bounds.left - drag.rect.left, drag.bounds.right - drag.rect.right),
        y: drag.initial.y + clamp(dy, drag.bounds.top - drag.rect.top, drag.bounds.bottom - drag.rect.bottom),
    };
    movedDialogs.set(drag.id, offset);
    applyMovedDialog(modal, offset);
    document.documentElement.classList.add('admin-dialog--dragging');
});

window.addEventListener('pointerup', (event) => {
    if (activeDrag?.pointerId === event.pointerId) finishDrag();
});
window.addEventListener('pointercancel', (event) => {
    if (activeDrag?.pointerId === event.pointerId) finishDrag();
});
window.addEventListener('blur', finishDrag);

document.addEventListener('keydown', (event) => {
    if (!desktopDrag.matches || !event.altKey
        || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)
        || !(event.target instanceof Element)
        || !event.target.classList.contains('fi-modal-header')) return;

    const modal = event.target.closest(openModalSelector);
    const win = modalWindow(modal);
    const bounds = dragBounds(modal);
    if (!modal || !win || !bounds) return;

    const rect = win.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return;

    const step = event.shiftKey ? 32 : 12;
    const dx = event.key === 'ArrowRight' ? step : event.key === 'ArrowLeft' ? -step : 0;
    const dy = event.key === 'ArrowDown' ? step : event.key === 'ArrowUp' ? -step : 0;

    const offset = { ...(movedDialogs.get(modal.id) ?? { x: 0, y: 0 }) };
    offset.x += clamp(dx, bounds.left - rect.left, bounds.right - rect.right);
    offset.y += clamp(dy, bounds.top - rect.top, bounds.bottom - rect.bottom);
    movedDialogs.set(modal.id, offset);
    applyMovedDialog(modal, offset);
    event.preventDefault();
    event.stopPropagation();
});

function scheduleGeometrySync() {
    if (syncScheduled) return;
    syncScheduled = true;
    window.requestAnimationFrame(() => {
        syncScheduled = false;
        syncOpenModalGeometry();
    });
}

function registerLivewireMorphSync() {
    if (!window.Livewire?.hook || registerLivewireMorphSync.registered) return;
    registerLivewireMorphSync.registered = true;

    // Restore only already-moved dialog windows after a Filament form morph.
    const onMorph = ({ el }) => {
        if (movedDialogs.size === 0 || !(el instanceof Element)) return;
        if (el.closest('.fi-modal') || el.querySelector('.fi-modal.fi-modal-open')) {
            scheduleGeometrySync();
        }
    };
    window.Livewire.hook('morph.updated', onMorph);
    window.Livewire.hook('morph.added', onMorph);
}

registerLivewireMorphSync();
document.addEventListener('livewire:init', registerLivewireMorphSync, { once: true });
window.addEventListener('resize', () => {
    finishDrag();
    syncOpenModalGeometry();
}, { passive: true });
document.addEventListener('livewire:navigated', () => {
    finishDrag();
    openModalIds.clear();
    movedDialogs.clear();
    syncOpenModalGeometry();
});
