import '../css/admin/selects.css';
import './admin-selects.js';
import { initializeAdminModalScrollBehavior } from './admin-modal-scroll.js';

let storageRuntimePromise = null;
let livewireHookRegistered = false;

initializeAdminModalScrollBehavior();

function hasStorageVisualization() {
    return document.querySelector('[data-admin-viz="storage-capacity"]') !== null;
}

function ensureStorageRuntime() {
    storageRuntimePromise ??= import('./admin-storage-viz.js');

    return storageRuntimePromise;
}

async function refreshVisualizations() {
    if (! hasStorageVisualization() && storageRuntimePromise === null) return;

    const runtime = await ensureStorageRuntime();
    runtime.refreshStorageVisualizations();
}

function refreshStorageIfPresent() {
    if (storageRuntimePromise === null && ! hasStorageVisualization()) return;
    void refreshVisualizations();
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
    window.Livewire.hook('morph.updated', refreshStorageIfPresent);
}

if (hasStorageVisualization()) {
    ensureStorageRuntime();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshVisualizations, { once: true });
} else {
    refreshVisualizations();
}

registerLivewireHook();
document.addEventListener('livewire:init', registerLivewireHook, { once: true });
document.addEventListener('livewire:navigated', refreshVisualizations);
document.addEventListener('alpine:navigate', prewarmStorageRuntime);

new MutationObserver(refreshStorageIfPresent).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
