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
    if (!image) return () => {};

    const lifetime = new AbortController();
    const page = image.closest('#content');
    const detail = image.closest('.artwork-detail');
    const sourceWidth = Number(image.getAttribute('width'));
    const sourceHeight = Number(image.getAttribute('height'));
    const stylesheet = [...document.querySelectorAll('style[data-public-layout-style]')].at(-1)?.sheet;
    const rootRule = [...(stylesheet?.cssRules ?? [])].find((rule) => rule.selectorText === ':root');
    const fitStyle = rootRule?.style;
    let frameRequest = 0;

    const clearFit = () => {
        fitStyle?.removeProperty('--public-fitted-image-width');
        fitStyle?.removeProperty('--public-fitted-paper-width');
    };

    const outerHeight = (element) => {
        if (!element) return 0;
        const style = getComputedStyle(element);
        return element.getBoundingClientRect().height
            + (Number.parseFloat(style.marginTop) || 0)
            + (Number.parseFloat(style.marginBottom) || 0);
    };

    const fit = () => {
        if (lifetime.signal.aborted || !fitStyle || !page || !detail
            || sourceWidth <= 0 || sourceHeight <= 0) return;

        // The small-screen presentation remains the existing native Artwork
        // layout, even after repeated narrow/wide viewport transitions.
        if (!window.matchMedia('(min-width: 64.001rem)').matches) {
            clearFit();
            return;
        }

        // Only viewport and unrelated fixed-height siblings are measured.
        // Never read the image stage, image, paper width or Menu surface:
        // doing so fed our own previous writes back into the next resize.
        const viewportWidth = document.documentElement.clientWidth;
        const rem = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        const gutter = Math.min(1.5 * rem, Math.max(.65 * rem, .02 * viewportWidth));
        const inset = gutter;
        const availableWidth = Math.max(1, viewportWidth - 2 * gutter - 2 * inset);
        const pageStyle = getComputedStyle(page);
        const reserve = outerHeight(document.querySelector('.site-header'))
            + outerHeight(detail.querySelector('.artwork-folio'))
            + outerHeight(detail.querySelector('.artwork-detail__footer'))
            + outerHeight(detail.querySelector('.artwork-detail__narrative'))
            + outerHeight(detail.querySelector('.artwork-detail__credits'))
            + (Number.parseFloat(pageStyle.paddingTop) || 0)
            + (Number.parseFloat(pageStyle.paddingBottom) || 0)
            + 1.5 * rem;
        const heightForImage = Math.max(0, window.innerHeight - reserve);
        const configuredWidth = Number.parseFloat(getComputedStyle(document.documentElement)
            .getPropertyValue('--public-art-max')) || 0;
        const fittedWidth = Math.min(availableWidth, Math.max(
            heightForImage * sourceWidth / sourceHeight,
            Math.min(availableWidth, configuredWidth),
        ));

        const generalShellWidth = Number.parseFloat(getComputedStyle(document.documentElement)
            .getPropertyValue('--public-shell-max')) || 0;
        const paperWidth = Math.max(
            fittedWidth + 2 * inset,
            Math.min(viewportWidth - 2 * gutter, generalShellWidth),
        );
        fitStyle.setProperty('--public-fitted-image-width', Math.floor(fittedWidth) + 'px');
        fitStyle.setProperty('--public-fitted-paper-width', Math.ceil(paperWidth) + 'px');
    };

    const scheduleFit = () => {
        if (frameRequest) cancelAnimationFrame(frameRequest);
        frameRequest = requestAnimationFrame(() => {
            frameRequest = 0;
            fit();
        });
    };

    if (page?.classList.contains('site-content--fitted-artwork') && fitStyle) {
        fit();
        window.addEventListener('resize', scheduleFit, { passive: true, signal: lifetime.signal });
        // The navigation controller may settle its height after page setup.
        scheduleFit();
        document.fonts?.ready?.then(() => {
            if (!lifetime.signal.aborted) scheduleFit();
        });
    }

    const ready = () => revealDecodedImage(image, 'artworkImageState', lifetime.signal);
    const failed = () => {
        image.dataset.artworkImageState = 'error';
    };

    image.addEventListener('load', ready, { signal: lifetime.signal });
    image.addEventListener('error', failed, { signal: lifetime.signal });
    if (image.complete) ready();

    return () => {
        lifetime.abort();
        if (frameRequest) cancelAnimationFrame(frameRequest);
        clearFit();
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
