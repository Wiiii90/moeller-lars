// Gallery cards are paged on the existing public route. The server remains
// the sole source of Artwork order, media eligibility and rendered card markup.
// This transport only appends canonical fragments and informs page-scoped owners.
export function initializeGalleryBatches(root = document) {
    const list = root.querySelector('[data-gallery-infinite]');
    const more = root.querySelector('[data-gallery-load-more]');
    if (!list || !more) return () => {};
    const status = more.querySelector('[data-gallery-load-more-label]');
    const dots = more.querySelector('[data-gallery-load-more-dots]');
    if (!status || !dots) return () => {};

    const lifetime = new AbortController();
    const attachedStyles = [];
    let busy = false;
    let failed = false;
    let observer = null;

    const showState = (state) => {
        more.dataset.galleryLoadState = state;
        status.textContent = state === 'loading' ? 'Loading artworks…'
            : state === 'error' ? 'Retry loading artworks' : 'Load more artworks';
        dots.hidden = state === 'error';
        if (state === 'loading') more.setAttribute('aria-busy', 'true');
        else more.removeAttribute('aria-busy');
    };
    // The native text link survives when scripts are disabled; when enhanced,
    // the same link becomes a restrained three-dot progress indicator.
    showState('idle');

    const next = async () => {
        if (busy || lifetime.signal.aborted || !more.isConnected) return;
        busy = true;
        failed = false;
        showState('loading');
        observer?.unobserve(more);

        try {
            const response = await fetch(more.href, {
                headers: { 'Accept': 'application/json', 'X-Gallery-Chunk': '1' },
                credentials: 'same-origin',
                signal: lifetime.signal,
            });
            if (!response.ok) throw new Error('Gallery request failed');
            const batch = await response.json();
            if (typeof batch.html !== 'string'
                || (batch.nextPage !== null && (!Number.isInteger(batch.nextPage) || batch.nextPage < 2))) {
                throw new Error('Invalid Gallery batch');
            }
            if (lifetime.signal.aborted) return;

            // Reuse the exact component-generated CSS at the current page's
            // nonce owner; a second HTTP response has a different CSP nonce.
            const styles = document.createElement('template');
            styles.innerHTML = batch.styles || '';
            const nonce = document.querySelector('style[data-public-layout-style]')?.nonce;
            if (styles.content.querySelector('style') && !nonce) {
                throw new Error('Public Gallery style nonce is unavailable');
            }
            for (const style of styles.content.querySelectorAll('style')) {
                style.nonce = nonce;
                document.head.append(style);
                attachedStyles.push(style);
            }

            const fragment = document.createElement('template');
            fragment.innerHTML = batch.html;
            const added = [...fragment.content.children];
            list.append(fragment.content);
            root.dispatchEvent(new CustomEvent('gallery:items-appended', { detail: { elements: added } }));

            if (batch.nextPage === null) {
                more.remove();
            } else {
                const destination = new URL(more.href);
                destination.searchParams.set('gallery_page', String(batch.nextPage));
                more.href = destination.href;
                showState('idle');
                observer?.observe(more);
            }
        } catch (error) {
            if (lifetime.signal.aborted) return;
            failed = true;
            showState('error');
            // An automatic retry loop would contend with visible images.
            // Keep the link usable for an explicit click instead.
        } finally {
            busy = false;
        }
    };

    const onClick = (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey
            || event.metaKey || event.altKey || event.shiftKey) return;
        event.preventDefault();
        void next();
    };
    more.addEventListener('click', onClick);

    if (typeof IntersectionObserver === 'function') {
        const lookahead = Math.max(960, Math.ceil(window.innerHeight * 1.2));
        observer = new IntersectionObserver((entries) => {
            if (!failed && entries.some((entry) => entry.isIntersecting)) void next();
        }, { rootMargin: `${lookahead}px 0px` });
        observer.observe(more);
    }

    return () => {
        lifetime.abort();
        observer?.disconnect();
        more.removeEventListener('click', onClick);
        attachedStyles.forEach((style) => style.remove());
    };
}
