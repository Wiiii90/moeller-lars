// Browser-native lazy loading remains the no-JavaScript fallback. The Gallery
// only upgrades images near the viewport; it never clones media URLs, creates
// detached images or requests the full catalogue at high priority.
export const GALLERY_PREFETCH_LIMIT = 2;
export const GALLERY_PREFETCH_MARGIN = '960px 0px';

export function galleryImageDistance(image, viewportHeight) {
    const bounds = image.getBoundingClientRect();
    if (bounds.top >= viewportHeight) return bounds.top - viewportHeight;
    if (bounds.bottom <= 0) return -bounds.bottom;
    return 0;
}

export function initializeGalleryImageLoading(root = document) {
    const images = Array.from(root.querySelectorAll('[data-gallery-image-buffer] .artwork-card__image'));
    if (images.length === 0 || typeof globalThis.IntersectionObserver !== 'function') return () => {};

    const owned = new Set(images);
    const waiting = new Set();
    const proactive = new Set();
    const lifetime = new AbortController();
    let disposed = false;

    const observer = new IntersectionObserver((entries) => {
        if (disposed) return;
        for (const entry of entries) {
            const image = entry.target;
            if (!owned.has(image) || image.loading !== 'lazy') continue;
            if (entry.isIntersecting && !image.complete) waiting.add(image);
            else waiting.delete(image);
        }
        pump();
    }, { rootMargin: GALLERY_PREFETCH_MARGIN });

    const finish = (image, state) => {
        image.dataset.galleryImageState = state;
        waiting.delete(image);
        proactive.delete(image);
        observer.unobserve(image);
    };

    const pump = () => {
        while (!disposed && proactive.size < GALLERY_PREFETCH_LIMIT && waiting.size > 0) {
            const viewportHeight = globalThis.window?.innerHeight || 800;
            const image = [...waiting].sort((a, b) =>
                galleryImageDistance(a, viewportHeight) - galleryImageDistance(b, viewportHeight))[0];
            waiting.delete(image);
            if (image.complete) {
                finish(image, image.naturalWidth > 0 ? 'ready' : 'error');
                continue;
            }
            proactive.add(image);
            // Attribute promotion reuses the existing canonical src and the
            // browser cache. Extra buffered images are NOT high priority.
            image.loading = 'eager';
            if (image.complete) finish(image, image.naturalWidth > 0 ? 'ready' : 'error');
        }
    };

    const onSettled = (event) => {
        if (disposed || !owned.has(event.target)) return;
        finish(event.target, event.type === 'load' ? 'ready' : 'error');
        pump();
    };
    root.addEventListener('load', onSettled, { capture: true, signal: lifetime.signal });
    root.addEventListener('error', onSettled, { capture: true, signal: lifetime.signal });

    for (const image of images) {
        if (image.complete) finish(image, image.naturalWidth > 0 ? 'ready' : 'error');
        else {
            image.dataset.galleryImageState = 'pending';
            if (image.loading === 'lazy') observer.observe(image);
        }
    }

    return () => {
        disposed = true;
        lifetime.abort();
        observer.disconnect();
        waiting.clear();
        proactive.clear();
    };
}
