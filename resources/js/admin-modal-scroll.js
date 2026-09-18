const MODAL_EXISTING_SCROLLBAR_CLASS = 'admin-modal-existing-scrollbar';

let initialized = false;

export function hasClassicDocumentScrollbar(innerWidth, clientWidth) {
    return Number.isFinite(innerWidth)
        && Number.isFinite(clientWidth)
        && innerWidth > clientWidth;
}

function modalFromOpenEvent(event) {
    const id = event.detail?.id;
    if (id === undefined || id === null || id === '') return null;

    const modal = document.getElementById(String(id));
    if (! modal?.classList.contains('fi-modal')) return null;
    if (modal.classList.contains('fi-modal-click-through')) return null;

    return modal;
}

function prepareModalScrollbarGeometry(event) {
    const modal = modalFromOpenEvent(event);
    if (! modal) return;

    const root = document.documentElement;
    root.classList.toggle(
        MODAL_EXISTING_SCROLLBAR_CLASS,
        hasClassicDocumentScrollbar(window.innerWidth, root.clientWidth),
    );
}

function releaseModalScrollbarGeometry() {
    queueMicrotask(() => {
        if (document.querySelector('.fi-modal.fi-modal-open:not(.fi-modal-click-through)')) return;

        document.documentElement.classList.remove(MODAL_EXISTING_SCROLLBAR_CLASS);
    });
}

function resetModalScrollbarGeometry() {
    document.documentElement.classList.remove(MODAL_EXISTING_SCROLLBAR_CLASS);
}

export function initializeAdminModalScrollbarGeometry() {
    if (initialized) return;
    initialized = true;

    // Capture runs before Filament's window-level open-modal listener. On a
    // page that already scrolls, the class keeps the real vertical scrollbar
    // visible and overrides Filament's inline padding compensation. A short
    // page is left entirely to Filament's native lock path.
    window.addEventListener('open-modal', prepareModalScrollbarGeometry, true);
    window.addEventListener('modal-closed', releaseModalScrollbarGeometry);
    document.addEventListener('livewire:navigated', resetModalScrollbarGeometry);
}
