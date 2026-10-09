const openModalSelector = '.fi-modal.fi-modal-open';

function modalFromOpenEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;

    const modal = document.getElementById(String(id));
    if (! modal?.classList.contains('fi-modal')) return null;
    if (modal.classList.contains('fi-modal-click-through')) return null;

    return modal;
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
    if (! modal) return;

    for (const element of modal.querySelectorAll('.fi-modal-window-ctn, .fi-modal-window, .fi-modal-content')) {
        element.scrollTop = 0;
        element.scrollLeft = 0;
    }
}

function syncModalGeometry(modal, { resetScroll = false } = {}) {
    if (! modal || modal.classList.contains('fi-modal-click-through')) return;

    const main = activeMain(modal);
    if (! main) return;

    modal.classList.add('admin-dialog-frame');

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

// The application owns only placement geometry. Every blocking Filament modal
// opened over an admin Main frame receives the same measured content bounds.
// Filament owns modal opening, document scroll lock, focus trapping, Escape,
// close/destroy and lock cleanup.
window.addEventListener('open-modal', (event) => {
    syncModalGeometry(modalFromOpenEvent(event));
}, true);

document.addEventListener('x-modal-opened', (event) => {
    syncModalGeometry(modalForEvent(event), { resetScroll: true });
});

window.addEventListener('resize', syncOpenModalGeometry, { passive: true });
document.addEventListener('livewire:navigated', syncOpenModalGeometry);
