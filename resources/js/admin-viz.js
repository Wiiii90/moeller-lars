import './admin-notifications.js';
import { adminMediaUploader, adminMediaUploadField } from './admin-media-upload.js';
window.adminMediaUploader = adminMediaUploader;
window.adminMediaUploadField = adminMediaUploadField;
import './admin-action-titles.js';
import './admin-dialog-geometry.js';
import './admin-partial-date.js';
import { adminLivePreview } from './admin-live-preview.js';
window.adminLivePreview = adminLivePreview;

// An Admin action activated with a mouse can retain native button focus.
// Pressing Shift later may switch the browser's :focus-visible heuristic,
// making the previously clicked action appear keyboard-focused.
// Keep the pointer origin until a real keyboard action occurs; Tab/Shift+Tab
// clears it before focus navigation, preserving accessible focus indicators.
// This belongs to the shared action grammar, not individual tool buttons.
const pointerFocusAttribute = 'data-admin-pointer-focus';
const clearPointerFocus = () => {
    document.querySelectorAll('[data-admin-pointer-focus]')
        .forEach((element) => element.removeAttribute(pointerFocusAttribute));
};

document.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'mouse' || !(event.target instanceof Element)) return;
    const action = event.target.closest('button.admin-action, a.admin-action, [role="button"].admin-action');
    if (! action) return;
    clearPointerFocus();
    action.setAttribute(pointerFocusAttribute, '');
}, true);

document.addEventListener('keydown', (event) => {
    if (['Shift', 'Control', 'Alt', 'Meta'].includes(event.key)) return;
    clearPointerFocus();
}, true);
import { adminGalleryHeroStage } from './gallery-hero-stage.js';
window.adminGalleryHeroStage = adminGalleryHeroStage;

let storageRuntimePromise = null;
let refreshFrame = null;
let livewireHookRegistered = false;


function hasStorageVisualization() {
    return document.querySelector('[data-admin-viz="storage-capacity"]') !== null;
}

function ensureStorageRuntime() {
    storageRuntimePromise ??= import('./admin-storage-viz.js');

    return storageRuntimePromise;
}

async function refreshVisualizations() {
    refreshFrame = null;

    if (! hasStorageVisualization() && storageRuntimePromise === null) return;

    const runtime = await ensureStorageRuntime();
    runtime.refreshStorageVisualizations();
}

function scheduleRefresh() {
    if (refreshFrame !== null) return;
    refreshFrame = window.requestAnimationFrame(refreshVisualizations);
}

function scheduleStorageRefresh() {
    if (storageRuntimePromise === null && ! hasStorageVisualization()) return;
    scheduleRefresh();
}

function destinationNeedsStorageRuntime(value) {
    if (! value) return false;

    try {
        const url = value instanceof URL ? value : new URL(String(value), window.location.href);
        const path = url.pathname.replace(/\/+$/, '') || '/';

        return path === '/admin' || path === '/admin/storage' || path.startsWith('/admin/storage/');
    } catch {
        return false;
    }
}

function prewarmStorageRuntime(event) {
    if (destinationNeedsStorageRuntime(event.detail?.url)) {
        ensureStorageRuntime();
    }
}

function registerLivewireHook() {
    if (livewireHookRegistered || ! window.Livewire?.hook) return;

    livewireHookRegistered = true;
    window.Livewire.hook('morph.updated', scheduleStorageRefresh);
}

if (hasStorageVisualization()) {
    ensureStorageRuntime();
}

scheduleRefresh();
registerLivewireHook();
document.addEventListener('livewire:init', registerLivewireHook, { once: true });
document.addEventListener('livewire:navigate', prewarmStorageRuntime);
document.addEventListener('livewire:navigated', scheduleRefresh);

new MutationObserver(scheduleStorageRefresh).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
