const openModalSelector = '.fi-modal.fi-modal-open';
const visibleScrollbarLockClass = 'admin-modal-visible-scrollbar-lock';
const scrollKeys = new Set([
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'End',
    'Home',
    'PageDown',
    'PageUp',
    ' ',
]);

let lockedScrollPosition = null;

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

function hasOpenBlockingAdminDialog() {
    return Array.from(document.querySelectorAll(openModalSelector)).some((modal) => (
        ! modal.classList.contains('fi-modal-click-through')
        && modal.querySelector(':scope > .fi-modal-window-ctn > .fi-modal-window.admin-task-dialog')
    ));
}

function freezeDocumentScroll(modal) {
    if (! hasClassicDocumentScrollbar()) return;

    if (lockedScrollPosition === null) {
        lockedScrollPosition = {
            left: window.scrollX,
            top: window.scrollY,
        };
    }

    document.documentElement.classList.add(visibleScrollbarLockClass);

    // Measure before Filament mutates <html> for its native lock. This keeps
    // the dialog layer on the exact same Main frame the user was looking at.
    syncModalGeometry(modal);
}

function restoreLockedDocumentScroll() {
    if (lockedScrollPosition === null) return;

    const { left, top } = lockedScrollPosition;

    if ((window.scrollX === left) && (window.scrollY === top)) return;

    window.scrollTo({
        left,
        top,
        behavior: 'instant',
    });
}

function releaseDocumentScroll() {
    queueMicrotask(() => {
        if (hasOpenBlockingAdminDialog()) return;

        restoreLockedDocumentScroll();
        lockedScrollPosition = null;
        document.documentElement.classList.remove(visibleScrollbarLockClass);
    });
}

function resetDocumentScrollLock() {
    lockedScrollPosition = null;
    document.documentElement.classList.remove(visibleScrollbarLockClass);
}

function eventIsInsideOpenDialog(event) {
    const target = event.target;

    return target instanceof Element
        && target.closest('.fi-modal.fi-modal-open > .fi-modal-window-ctn') !== null;
}

function preventBackgroundPointerScroll(event) {
    if (lockedScrollPosition === null) return;
    if (eventIsInsideOpenDialog(event)) return;

    event.preventDefault();
}

function preventBackgroundKeyboardScroll(event) {
    if (lockedScrollPosition === null) return;
    if (! scrollKeys.has(event.key)) return;
    if (eventIsInsideOpenDialog(event)) return;

    const target = event.target;

    if (
        target instanceof HTMLElement
        && (
            target.isContentEditable
            || ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)
        )
    ) {
        return;
    }

    event.preventDefault();
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

// Capture runs before Filament's window-level open listener. For long pages we
// keep the already-visible classic scrollbar, freeze its scroll position and
// neutralize only Filament's visual overflow/padding mutation via CSS. Filament
// still owns open/close/focus/Escape/destroy and its native lock counter.
window.addEventListener('open-modal', (event) => {
    const modal = modalFromOpenEvent(event);
    if (! modal) return;

    freezeDocumentScroll(modal);
}, true);

window.addEventListener('modal-closed', releaseDocumentScroll);
window.addEventListener('scroll', restoreLockedDocumentScroll, { passive: true });
window.addEventListener('wheel', preventBackgroundPointerScroll, { capture: true, passive: false });
window.addEventListener('touchmove', preventBackgroundPointerScroll, { capture: true, passive: false });
document.addEventListener('keydown', preventBackgroundKeyboardScroll, true);

document.addEventListener('x-modal-opened', (event) => {
    syncModalGeometry(modalForEvent(event), { resetScroll: true });
});

window.addEventListener('resize', syncOpenModalGeometry, { passive: true });
document.addEventListener('livewire:navigated', () => {
    resetDocumentScrollLock();
    syncOpenModalGeometry();
});
