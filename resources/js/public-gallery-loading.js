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

/* The shared fitted-media owner. Both a Gallery's individual Artwork and
   Home's currently visible Hero slide use the same measured width/height
   budget, so captions and bottom padding cannot diverge between templates. */
function fittedMediaWidth(page, owner, availableWidth, sourceWidth, sourceHeight, footer, controls = null) {
    const viewport = window.visualViewport;
    const viewportHeight = viewport?.height ?? window.innerHeight;
    const pageStyle = getComputedStyle(page);
    const outerHeight = (element) => {
        if (!element) return 0;
        const style = getComputedStyle(element);
        if (style.display === 'none') return 0;
        return element.getBoundingClientRect().height
            + (Number.parseFloat(style.marginTop) || 0)
            + (Number.parseFloat(style.marginBottom) || 0);
    };
    // The media group may overflow during its initial fit and move its own
    // rectangle. The Page top plus visible heading/folio heights is stable.
    const heading = page.querySelector(':scope > .public-content-reveal > .public-page-heading');
    const folio = owner.querySelector('.artwork-folio');
    const stageStart = page.getBoundingClientRect().top
        + (Number.parseFloat(pageStyle.paddingTop) || 0)
        + outerHeight(heading) + outerHeight(folio);
    const imageTop = Math.max(0, stageStart - (viewport?.offsetTop ?? 0));
    const reserveBelow = outerHeight(footer) + outerHeight(controls)
        + (Number.parseFloat(pageStyle.paddingBottom) || 0)
        + (Number.parseFloat(pageStyle.marginBottom) || 0);
    const remaining = viewportHeight - imageTop - reserveBelow;
    // Exceptionally long Artwork information may scroll below the Page.
    // Never shrink the image toward zero through a feedback loop where its
    // narrower caption track creates still more wrapped metadata lines.
    const minimumMediaHeight = Math.min(240, viewportHeight * .3);
    const heightForImage = Math.max(
        minimumMediaHeight, remaining > 0 ? remaining : viewportHeight - reserveBelow,
    );
    return Math.max(1, Math.floor(Math.min(availableWidth, heightForImage * sourceWidth / sourceHeight)));
}

function fittedStyleRule() {
    const stylesheet = [...document.querySelectorAll('style[data-public-layout-style]')].at(-1)?.sheet;
    return [...(stylesheet?.cssRules ?? [])].find((rule) => rule.selectorText === ':root')?.style ?? null;
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
    const stage = detail?.querySelector('.artwork-detail__image-stage');
    const sourceWidth = Number(image.getAttribute('width'));
    const sourceHeight = Number(image.getAttribute('height'));
    const fitStyle = fittedStyleRule();
    let frameRequest = 0;

    const clearFit = () => {
        fitStyle?.removeProperty('--public-fitted-image-width');
        // Retire the previous fitted-paper override on SPA transitions.
        fitStyle?.removeProperty('--public-fitted-paper-width');
    };

    const fit = () => {
        if (lifetime.signal.aborted || !fitStyle || !page || !detail || !stage
            || sourceWidth <= 0 || sourceHeight <= 0) return;

        const fittedWidth = fittedMediaWidth(
            page, detail, Math.max(1, stage.getBoundingClientRect().width),
            sourceWidth, sourceHeight, detail.querySelector('.artwork-detail__footer'),
        );
        fitStyle.setProperty('--public-fitted-image-width', Math.max(1, Math.floor(fittedWidth)) + 'px');
        fitStyle.removeProperty('--public-fitted-paper-width');
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

/* Home switches among already-present slides. Fit only the active member
   using exactly the same General content/viewport/metadata calculation as
   an individual Artwork. No inline style attributes or extra style owners. */
function initializeHomeHeroFitting(root) {
    const page = root.querySelector?.('#content.site-content--home-hero-fitted');
    const group = page?.querySelector('.home-hero-presentation');
    const fitStyle = fittedStyleRule();
    if (!page || !group || !fitStyle) return () => {};

    const lifetime = new AbortController();
    let frameRequest = 0;
    const fit = () => {
        if (lifetime.signal.aborted) return;
        const active = [...group.querySelectorAll(':scope > [data-home-hero-slide]')]
            .find((slide) => !slide.hidden);
        const owner = active?.querySelector('.artwork-card');
        const media = owner?.querySelector('.artwork-card__composition--home-hero :is(.artwork-card__image, .artwork-card__video)');
        if (!owner || !media) {
            fitStyle.removeProperty('--public-fitted-image-width');
            return;
        }

        const width = Number(media.getAttribute('width')) || Number(media.naturalWidth)
            || Number(media.videoWidth) || 0;
        const height = Number(media.getAttribute('height')) || Number(media.naturalHeight)
            || Number(media.videoHeight) || 0;
        if (width <= 0 || height <= 0) {
            fitStyle.removeProperty('--public-fitted-image-width');
            return;
        }
        const fittedWidth = fittedMediaWidth(
            page, owner, Math.max(1, owner.getBoundingClientRect().width),
            width, height, owner.querySelector('.artwork-card__footer'),
            owner.querySelector('.public-video__controls'),
        );
        fitStyle.setProperty('--public-fitted-image-width', fittedWidth + 'px');
    };
    const schedule = () => {
        if (frameRequest) cancelAnimationFrame(frameRequest);
        frameRequest = requestAnimationFrame(() => {
            frameRequest = 0;
            fit();
        });
    };

    fit();
    window.addEventListener('resize', schedule, { passive: true, signal: lifetime.signal });
    group.addEventListener('home-hero-change', schedule, { signal: lifetime.signal });
    group.addEventListener('load', schedule, { capture: true, signal: lifetime.signal });
    group.addEventListener('loadedmetadata', schedule, { capture: true, signal: lifetime.signal });
    // A media-width change can wrap long captions into additional lines.
    // Re-measure their real height without adding a separate fitting owner.
    const footerObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(schedule) : null;
    group.querySelectorAll('.artwork-card__footer, .public-video__controls').forEach((element) => {
        footerObserver?.observe(element);
    });
    document.fonts?.ready?.then(() => {
        if (!lifetime.signal.aborted) schedule();
    });
    return () => {
        lifetime.abort();
        footerObserver?.disconnect();
        if (frameRequest) cancelAnimationFrame(frameRequest);
        fitStyle.removeProperty('--public-fitted-image-width');
    };
}

export function initializeGalleryImageLoading(root = document) {
    const disposeDetail = initializeArtworkDetailImage(root);
    const disposeHome = initializeHomeHeroFitting(root);
    const disposeFitted = () => {
        disposeDetail();
        disposeHome();
    };
    const images = Array.from(root.querySelectorAll('.artwork-card__image'));
    if (images.length === 0) return disposeFitted;

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
        disposeFitted();
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
