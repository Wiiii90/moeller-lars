const MODAL_SCROLLBAR_GUTTER_CLASS = 'admin-modal-scrollbar-gutter';

let initialized = false;

export function needsStableScrollbarGutter(innerWidth, clientWidth) {
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
        MODAL_SCROLLBAR_GUTTER_CLASS,
        needsStableScrollbarGutter(window.innerWidth, root.clientWidth),
    );
}

function releaseModalScrollbarGeometry() {
    queueMicrotask(() => {
        if (document.querySelector('.fi-modal.fi-modal-open:not(.fi-modal-click-through)')) return;

        document.documentElement.classList.remove(MODAL_SCROLLBAR_GUTTER_CLASS);
    });
}

function resetModalScrollbarGeometry() {
    document.documentElement.classList.remove(MODAL_SCROLLBAR_GUTTER_CLASS);
}

export function initializeAdminModalScrollbarGeometry() {
    if (initialized) return;
    initialized = true;

    // Capture runs before Filament's window-level open-modal listener. This is
    // intentional: acquireScrollLock() reads computed scrollbar-gutter before
    // it decides whether to inject padding-right compensation.
    window.addEventListener('open-modal', prepareModalScrollbarGeometry, true);
    window.addEventListener('modal-closed', releaseModalScrollbarGeometry);
    document.addEventListener('livewire:navigated', resetModalScrollbarGeometry);
}
