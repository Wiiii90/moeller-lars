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

// The same Artwork image lifecycle also resolves the short-lived native ALT
// placeholder on detail routes. The server owns the pending state so the
// placeholder is hidden before JavaScript starts, including during SPA swaps.
function revealDecodedImage(image, key, signal) {
    const source = image.currentSrc || image.src;
    const done = () => {
        if (signal.aborted || (image.currentSrc || image.src) !== source) return;
        image.dataset[key] = image.naturalWidth > 0 ? 'ready' : 'error';
    };
    if (image.naturalWidth <= 0 || typeof image.decode !== 'function') {
        done();
        return;
    }
    try {
        image.decode().then(done, done);
    } catch {
        done();
    }
}

function initializeArtworkDetailImage(root) {
    const image = root.querySelector?.('.artwork-detail__image[data-artwork-image-state="pending"]');
    if (! image) return () => {};

    const lifetime = new AbortController();
    const media = image.closest('.artwork-detail__media');
    const stage = media?.querySelector('.artwork-detail__image-stage');
    const page = stage?.closest('#content');
    const frame = page?.closest('.public-paper-frame');
    const width = Number(image.getAttribute('width'));
    const height = Number(image.getAttribute('height'));
    let resizeObserver = null;
    if (stage && width > 0 && height > 0) {
        const fit = () => {
            if (lifetime.signal.aborted) return;
            const { width: availableWidth, height: availableHeight } = stage.getBoundingClientRect();
            if (availableWidth <= 0 || availableHeight <= 0) return;
            const standardWidth = Number.parseFloat(getComputedStyle(document.documentElement)
                .getPropertyValue('--public-art-max')) || 0;
            const fittedWidth = Math.min(availableWidth, Math.max(
                availableHeight * width / height,
                Math.min(availableWidth, standardWidth),
            ));
            const value = Math.max(1, Math.floor(fittedWidth * 100) / 100) + 'px';
            if (media.style.getPropertyValue('--public-fitted-image-width') !== value) {
                media.style.setProperty('--public-fitted-image-width', value);
            }
            if (page && frame) {
                const style = getComputedStyle(page);
                const horizontalPadding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
                const paperWidth = Math.ceil(fittedWidth + horizontalPadding) + 'px';
                if (frame.style.getPropertyValue('--public-fitted-paper-width') !== paperWidth) {
                    frame.style.setProperty('--public-fitted-paper-width', paperWidth);
                }
            }
        };
        fit();
        if (typeof ResizeObserver === 'function') {
            resizeObserver = new ResizeObserver(fit);
            resizeObserver.observe(stage);
        } else {
            window.addEventListener('resize', fit, { passive: true, signal: lifetime.signal });
        }
    }
    const ready = () => revealDecodedImage(image, 'artworkImageState', lifetime.signal);
    const failed = () => {
        // Keep broken-image ALT visible: a real media failure must not silently
        // become a permanently blank artwork.
        image.dataset.artworkImageState = 'error';
    };

    image.addEventListener('load', ready, { signal: lifetime.signal });
    image.addEventListener('error', failed, { signal: lifetime.signal });
    // The image may have completed before the SPA page initializer runs.
    if (image.complete) ready();

    return () => {
        lifetime.abort();
        resizeObserver?.disconnect();
    };
}

export function initializeGalleryImageLoading(root = document) {
    const disposeDetail = initializeArtworkDetailImage(root);
    const images = Array.from(root.querySelectorAll('.artwork-card__image'));
    if (images.length === 0) return disposeDetail;

    const lifetime = new AbortController();
    const allImages = new Set(images);
    let releaseBuffered = () => {};
    const settled = (event) => {
        if (lifetime.signal.aborted || !allImages.has(event.target)) return;
        const image = event.target;
        if (event.type === 'error') image.dataset.galleryImageState = 'error';
        else revealDecodedImage(image, 'galleryImageState', lifetime.signal);
        releaseBuffered(image);
    };
    root.addEventListener('load', settled, { capture: true, signal: lifetime.signal });
    root.addEventListener('error', settled, { capture: true, signal: lifetime.signal });

    for (const image of images) {
        if (image.src && !image.dataset.gallerySrc && image.complete) {
            revealDecodedImage(image, 'galleryImageState', lifetime.signal);
        }
    }

    const cleanup = () => {
        lifetime.abort();
        disposeDetail();
    };
    const deferred = images.filter(image => image.dataset.gallerySrc);
    if (deferred.length === 0) return cleanup;

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
            if (image.complete) revealDecodedImage(image, 'galleryImageState', lifetime.signal);
        }
        return cleanup;
    }

    const owned = new Set(deferred);
    const nearby = new Set();
    const speculative = new Set();
    let disposed = false;

    const finish = (image) => {
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

        if (image.complete) {
            revealDecodedImage(image, 'galleryImageState', lifetime.signal);
            finish(image);
        }
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

    releaseBuffered = (image) => {
        if (disposed || !owned.has(image)) return;
        finish(image);
        pump();
    };

    for (const image of deferred) {
        bufferObserver.observe(image);
        visibleObserver.observe(image);
    }

    return () => {
        disposed = true;
        cleanup();
        bufferObserver.disconnect();
        visibleObserver.disconnect();
        nearby.clear();
        speculative.clear();
    };
}
