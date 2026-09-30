import '../css/admin/selects.css';
import './admin-selects.js';
import { initializeAdminModalScrollBehavior } from './admin-modal-scroll.js';

let storageRuntimePromise = null;
let refreshFrame = null;
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleRefresh, { once: true });
} else {
    scheduleRefresh();
}

registerLivewireHook();
document.addEventListener('livewire:init', registerLivewireHook, { once: true });
document.addEventListener('livewire:navigated', scheduleRefresh);
document.addEventListener('alpine:navigate', prewarmStorageRuntime);

new MutationObserver(scheduleStorageRefresh).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
