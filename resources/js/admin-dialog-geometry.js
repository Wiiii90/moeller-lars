const openModalSelector = '.fi-modal.fi-modal-open';
const modalScrollbarGutterClass = 'admin-modal-scrollbar-gutter';

function hasClassicDocumentScrollbar() {
    const root = document.documentElement;

    return window.innerWidth > root.clientWidth;
}

function modalFromOpenEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;

    const modal = document.getElementById(String(id));
    if (! modal?.classList.contains('fi-modal')) return null;
    if (modal.classList.contains('fi-modal-click-through')) return null;
    if (! modal.querySelector(':scope > .fi-modal-window-ctn > .fi-modal-window.admin-task-dialog')) return null;

    return modal;
}

function prepareModalScrollbarGeometry(event) {
    if (! modalFromOpenEvent(event)) return;

    document.documentElement.classList.toggle(
        modalScrollbarGutterClass,
        hasClassicDocumentScrollbar(),
    );
}

function releaseModalScrollbarGeometry() {
    queueMicrotask(() => {
        if (document.querySelector('.fi-modal.fi-modal-open:not(.fi-modal-click-through)')) return;

        document.documentElement.classList.remove(modalScrollbarGutterClass);
    });
}

function resetModalScrollbarGeometry() {
    document.documentElement.classList.remove(modalScrollbarGutterClass);
}

function numericPx(value) {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function modalForEvent(event) {
    const id = event.detail?.id;

    if (id) {
        return document.getElementById(id);
    }

    const openModals = document.querySelectorAll(openModalSelector);

    return openModals[openModals.length - 1] ?? null;
}

function activeMain(modal) {
    return modal?.closest('.fi-main')
        ?? document.querySelector('.fi-main:has(.admin-workspace)')
        ?? document.querySelector('.fi-main');
}

function resetModalScroll(modal) {
    for (const element of modal.querySelectorAll('.fi-modal-window-ctn, .fi-modal-window, .fi-modal-content')) {
        element.scrollTop = 0;
        element.scrollLeft = 0;
    }
}

function syncModalGeometry(modal, { resetScroll = false } = {}) {
    if (! modal) return;

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

    modal.style.setProperty('--admin-dialog-layer-inline-start', `${inlineStart}px`);
    modal.style.setProperty('--admin-dialog-layer-inline-end', `${inlineEnd}px`);
    modal.style.setProperty('--admin-dialog-layer-block-start', `${Math.min(window.innerHeight, topbarBottom)}px`);
    modal.style.setProperty('--admin-dialog-layer-padding-inline', `${inlinePadding}px`);

    if (resetScroll) {
        resetModalScroll(modal);
    }
}

function syncOpenModalGeometry() {
    for (const modal of document.querySelectorAll(openModalSelector)) {
        syncModalGeometry(modal);
    }
}

// Capture runs before Filament's window-level open listener. On a page that
// already has a classic scrollbar, the temporary stable gutter is visible to
// Filament before acquireScrollLock() decides whether padding compensation is
// needed. Filament still owns the actual acquire/release lifecycle.
window.addEventListener('open-modal', prepareModalScrollbarGeometry, true);
window.addEventListener('modal-closed', releaseModalScrollbarGeometry);

document.addEventListener('x-modal-opened', (event) => {
    syncModalGeometry(modalForEvent(event), { resetScroll: true });
});

window.addEventListener('resize', syncOpenModalGeometry, { passive: true });
document.addEventListener('livewire:navigated', () => {
    resetModalScrollbarGeometry();
    syncOpenModalGeometry();
});
