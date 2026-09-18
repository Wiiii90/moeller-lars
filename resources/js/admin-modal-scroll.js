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
let softScrollLockCount = 0;
let lockedScrollX = 0;
let lockedScrollY = 0;
const softLockedModals = new WeakSet();

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

function acquireSoftScrollLock() {
    if (softScrollLockCount === 0) {
        lockedScrollX = window.scrollX;
        lockedScrollY = window.scrollY;
        attachSoftScrollLockListeners();
    }

    softScrollLockCount++;
}

function releaseSoftScrollLock() {
    if (softScrollLockCount === 0) return;

    softScrollLockCount--;

    if (softScrollLockCount > 0) return;

    detachSoftScrollLockListeners();
    restoreLockedWindowScroll();
}

function modalFromEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;

    const modal = document.getElementById(String(id));
    if (! modal?.classList.contains('fi-modal')) return null;
    if (! adminTaskDialogWindow(modal)) return null;

    return modal;
}

function acquireAdminTaskDialogSoftLock(event) {
    const modal = modalFromEvent(event);
    if (! modal || softLockedModals.has(modal)) return;

    softLockedModals.add(modal);
    acquireSoftScrollLock();
}

function releaseAdminTaskDialogSoftLock(event) {
    const modal = modalFromEvent(event);
    if (! modal || ! softLockedModals.has(modal)) return;

    softLockedModals.delete(modal);
    releaseSoftScrollLock();
}

function resetSoftScrollLock() {
    softScrollLockCount = 0;
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
    document.addEventListener('livewire:navigated', resetSoftScrollLock);
}
