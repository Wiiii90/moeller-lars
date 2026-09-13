let storageRuntimePromise = null;
let refreshFrame = null;
let livewireHookRegistered = false;

function hasStorageVisualization() {
    return document.querySelector('[data-admin-viz="storage-capacity"]') !== null;
}

async function refreshVisualizations() {
    refreshFrame = null;

    if (! hasStorageVisualization() && storageRuntimePromise === null) return;

    storageRuntimePromise ??= import('./admin-storage-viz.js');
    const runtime = await storageRuntimePromise;
    runtime.refreshStorageVisualizations();
}

function scheduleRefresh() {
    if (refreshFrame !== null) return;
    refreshFrame = window.requestAnimationFrame(refreshVisualizations);
}

function registerLivewireHook() {
    if (livewireHookRegistered || ! window.Livewire?.hook) return;

    livewireHookRegistered = true;
    window.Livewire.hook('morph.updated', scheduleRefresh);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleRefresh, { once: true });
} else {
    scheduleRefresh();
}

registerLivewireHook();
document.addEventListener('livewire:init', registerLivewireHook, { once: true });
document.addEventListener('livewire:navigated', scheduleRefresh);

new MutationObserver(() => {
    if (hasStorageVisualization()) scheduleRefresh();
}).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
