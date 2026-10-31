const openModalSelector = '.fi-modal.fi-modal-open';

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
// Filament may emit open-modal again when the mounted Action rerenders.
// Only x-modal-opened represents an actual closed-to-open transition; position
// once at that boundary, never in response to an unchanged mounted Action.
document.addEventListener('x-modal-opened', (event) => {
    const modal = modalForEvent(event);
    if (! modal) return;

    const id = String(event.detail.id);
    const isNewSession = ! openModalIds.has(id);
    openModalIds.add(id);
    syncModalGeometry(modal, { resetScroll: isNewSession });
});

document.addEventListener('modal-closed', (event) => {
    if (event.detail?.id != null) openModalIds.delete(String(event.detail.id));
});

window.addEventListener('resize', syncOpenModalGeometry, { passive: true });
document.addEventListener('livewire:navigated', () => {
    openModalIds.clear();
    syncOpenModalGeometry();
});
