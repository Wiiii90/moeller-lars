const SCROLL_LOCK_KEYS = new Set([
    'ArrowDown',
    'ArrowUp',
    'End',
    'Home',
    'PageDown',
    'PageUp',
    ' ',
]);

let initialized = false;
const softLockedModalIds = new Set();
let lockedScrollX = 0;
let lockedScrollY = 0;

export function isDocumentScrollKey(key) {
    return SCROLL_LOCK_KEYS.has(key);
}

function adminTaskDialogWindow(modal) {
    return modal.querySelector(':scope > .fi-modal-window-ctn > .fi-modal-window.admin-task-dialog');
}

function scrollableDialogContent(target) {
    if (! (target instanceof Element)) return null;

    const content = target.closest('.admin-task-dialog .fi-modal-content');
    if (! content) return null;
    if (content.scrollHeight <= content.clientHeight) return null;

    return content;
}

function isInteractiveKeyTarget(target) {
    if (! (target instanceof Element)) return false;

    return target.closest(
        'input, textarea, select, button, a, [role="button"], [contenteditable="true"]',
    ) !== null;
}

function preventBackgroundWheel(event) {
    if (scrollableDialogContent(event.target)) return;

    event.preventDefault();
}

function preventBackgroundTouchMove(event) {
    if (scrollableDialogContent(event.target)) return;

    event.preventDefault();
}

function preventBackgroundKeyScroll(event) {
    if (! isDocumentScrollKey(event.key)) return;
    if (event.altKey || event.ctrlKey || event.metaKey) return;
    if (isInteractiveKeyTarget(event.target)) return;
    if (scrollableDialogContent(event.target)) return;

    event.preventDefault();
}

function restoreLockedWindowScroll() {
    if (window.scrollX === lockedScrollX && window.scrollY === lockedScrollY) return;

    window.scrollTo(lockedScrollX, lockedScrollY);
}

function attachSoftScrollLockListeners() {
    document.addEventListener('wheel', preventBackgroundWheel, { capture: true, passive: false });
    document.addEventListener('touchmove', preventBackgroundTouchMove, { capture: true, passive: false });
    document.addEventListener('keydown', preventBackgroundKeyScroll, true);
    window.addEventListener('scroll', restoreLockedWindowScroll);
}

function detachSoftScrollLockListeners() {
    document.removeEventListener('wheel', preventBackgroundWheel, true);
    document.removeEventListener('touchmove', preventBackgroundTouchMove, true);
    document.removeEventListener('keydown', preventBackgroundKeyScroll, true);
    window.removeEventListener('scroll', restoreLockedWindowScroll);
}

function modalIdFromEvent(event) {
    const id = event.detail?.id;

    return id === undefined || id === null || id === '' ? null : String(id);
}

function acquireAdminTaskDialogSoftLock(event) {
    const id = modalIdFromEvent(event);
    if (id === null || softLockedModalIds.has(id)) return;

    const modal = document.getElementById(id);
    if (! modal?.classList.contains('fi-modal')) return;
    if (! adminTaskDialogWindow(modal)) return;

    if (softLockedModalIds.size === 0) {
        lockedScrollX = window.scrollX;
        lockedScrollY = window.scrollY;
        attachSoftScrollLockListeners();
    }

    softLockedModalIds.add(id);
}

function releaseAdminTaskDialogSoftLock(event) {
    const id = modalIdFromEvent(event);
    if (id === null || ! softLockedModalIds.delete(id)) return;
    if (softLockedModalIds.size > 0) return;

    detachSoftScrollLockListeners();
    restoreLockedWindowScroll();
}

function resetSoftScrollLock() {
    softLockedModalIds.clear();
    detachSoftScrollLockListeners();
}

export function initializeAdminModalScrollBehavior() {
    if (initialized) return;
    initialized = true;

    // Filament's modal provider is preconfigured in admin-modal-bootstrap.blade.php
    // so AdminDialog instances never acquire Filament's root-mutating scroll lock.
    // These listeners provide the blocking behavior without touching <html>.
    window.addEventListener('open-modal', acquireAdminTaskDialogSoftLock, true);
    window.addEventListener('modal-closed', releaseAdminTaskDialogSoftLock);
    document.addEventListener('livewire:navigating', resetSoftScrollLock);
    document.addEventListener('livewire:navigated', resetSoftScrollLock);
}
