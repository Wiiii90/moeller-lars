// Only the first Gallery images expose src in initial HTML. Later images
// keep their canonical URLs in data-gallery-src until close to the viewport.
// Native loading=lazy remains the fallback without IntersectionObserver.
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
    if (images.length === 0) return () => {};

    const deferred = images.filter(image => image.dataset.gallerySrc);
    if (deferred.length === 0) return () => {};

    // HTML keeps gated images hidden for the no-JS <noscript> fallback.
    // Reveal before observing: intrinsic width/height reserve layout space.
    for (const image of deferred) {
        image.hidden = false;
        image.dataset.galleryImageState = 'deferred';
    }

    if (typeof globalThis.IntersectionObserver !== 'function') {
        // Degrade to native lazy loading, rather than leaving blank images.
        for (const image of deferred) {
            image.src = image.dataset.gallerySrc;
            delete image.dataset.gallerySrc;
            image.dataset.galleryImageState = 'pending';
        }
        return () => {};
    }

    const owned = new Set(deferred);
    const nearby = new Set();
    const speculative = new Set();
    const lifetime = new AbortController();
    let disposed = false;

    const finish = (image, state) => {
        image.dataset.galleryImageState = state;
        nearby.delete(image);
        speculative.delete(image);
        bufferObserver.unobserve(image);
        visibleObserver.unobserve(image);
    };

    const request = (image, visible = false) => {
        if (!owned.has(image)) return;
        if (!image.dataset.gallerySrc) {
            if (visible && image.dataset.galleryImageState === 'pending') {
                image.fetchPriority = 'high';
                speculative.delete(image);
            }
            return;
        }

        nearby.delete(image);
        if (visible) {
            speculative.delete(image);
            image.fetchPriority = 'high';
        } else {
            speculative.add(image);
            image.fetchPriority = 'low';
        }

        // Assign the canonical src only here. loading=eager guarantees that
        // the near-viewport buffer is not delayed by native lazy heuristics.
        image.loading = 'eager';
        image.dataset.galleryImageState = 'pending';
        image.src = image.dataset.gallerySrc;
        delete image.dataset.gallerySrc;

        if (image.complete) finish(image, image.naturalWidth > 0 ? 'ready' : 'error');
    };

    const pump = () => {
        while (!disposed && speculative.size < GALLERY_PREFETCH_LIMIT && nearby.size > 0) {
            const viewportHeight = globalThis.window?.innerHeight || 800;
            const image = [...nearby].sort((a, b) =>
                galleryImageDistance(a, viewportHeight) - galleryImageDistance(b, viewportHeight))[0];
            request(image);
        }
    };

    const bufferObserver = new IntersectionObserver((entries) => {
        if (disposed) return;
        for (const entry of entries) {
            if (!owned.has(entry.target) || !entry.target.dataset.gallerySrc) continue;
            if (entry.isIntersecting) nearby.add(entry.target);
            else nearby.delete(entry.target);
        }
        pump();
    }, { rootMargin: GALLERY_PREFETCH_MARGIN });

    const visibleObserver = new IntersectionObserver((entries) => {
        if (disposed) return;
        for (const entry of entries) {
            if (entry.isIntersecting) request(entry.target, true);
        }
        // Scrolled-to images bypass the speculative budget; freed slots
        // remain available to buffer the next unseen artwork.
        pump();
    }, { rootMargin: '0px' });

    const settled = (event) => {
        if (disposed || !owned.has(event.target)) return;
        finish(event.target, event.type === 'load' ? 'ready' : 'error');
        pump();
    };

    root.addEventListener('load', settled, { capture: true, signal: lifetime.signal });
    root.addEventListener('error', settled, { capture: true, signal: lifetime.signal });

    for (const image of deferred) {
        bufferObserver.observe(image);
        visibleObserver.observe(image);
    }

    return () => {
        disposed = true;
        lifetime.abort();
        bufferObserver.disconnect();
        visibleObserver.disconnect();
        nearby.clear();
        speculative.clear();
    };
}
