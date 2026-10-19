/* Connected/continuous public paper owns exactly one pair of static outer
 * contours. Their return follows the last visible rendered content item,
 * not the trailing content padding or the viewport height.
 *
 * DOM-derived geometry is needed because Journal disclosure, custom
 * components, home slides and lazy Gallery media are not fixed-height.
 * The observed elements are content owners; the CSS value only affects
 * absolutely positioned decoration, so observing cannot feed back into
 * the measured layout. */
const CONTENT_ITEMS = [
    '.artwork-list > .artwork-card',
    '.blog-page > .blog-entry',
    '.exhibitions-page > .exhibition-entry',
    '.artwork-detail',
    '.blog-post',
    '.custom-page:not(.custom-page--embedded) > .custom-page__component',
    '.custom-page:not(.custom-page--embedded) > .custom-page__image-group',
    '.custom-page:not(.custom-page--embedded) > .custom-page__divider',
    '[data-home-hero-slide]:not([hidden]) > .artwork-card',
].join(', ');

function visible(element) {
    return element instanceof HTMLElement
        && element.getClientRects().length > 0
        && element.getBoundingClientRect().height > 0
        && !element.closest('[hidden], [inert]');
}

export function findPublicPaperEnd(content) {
    const flow = content?.querySelector(':scope > .public-content-reveal');
    if (!(flow instanceof HTMLElement)) return null;

    const entries = [...flow.querySelectorAll(CONTENT_ITEMS)].filter(visible);
    if (entries.length) {
        // A Gallery can contain separators, an Exhibition may be expanded,
        // and Home may retain hidden slides. Use the actual last visible
        // rendered content edge, not DOM order or the last image asset.
        return entries.reduce((last, current) =>
            current.getBoundingClientRect().bottom > last.getBoundingClientRect().bottom
                ? current : last);
    }
    return [...flow.children].reverse().find(visible) ?? flow;
}

export function initializePublicPaperFrame() {
    const frame = document.querySelector('.public-paper-frame');
    const decoration = frame?.querySelector(':scope > .public-paper-decoration');
    const content = frame?.querySelector(':scope > #content');
    if (!(frame instanceof HTMLElement)
        || !(decoration instanceof HTMLElement)
        || !(content instanceof HTMLElement)) return () => {};

    const hasSharedContours = frame.classList.contains('public-paper-frame--connected')
        || (frame.classList.contains('public-paper-frame--compatible')
            && document.body.classList.contains('public-site--frame-continuous'));
    const drawsContours = ['layered', 'continuous', 'contours']
        .some(style => document.body.classList.contains('public-site--frame-' + style));
    if (!hasSharedContours || !drawsContours) return () => {};

    let disposed = false;
    let previous = null;
    let observedTarget = null;
    const observer = new ResizeObserver(update);
    const update = () => {
        if (disposed || !frame.isConnected) return;
        const target = findPublicPaperEnd(content);
        if (!target) return;
        if (target !== observedTarget) {
            if (observedTarget) observer.unobserve(observedTarget);
            observer.observe(target);
            observedTarget = target;
        }
        const frameRect = frame.getBoundingClientRect();
        const endRect = target.getBoundingClientRect();
        // The separate style rule owns the small final return inset.
        const end = Math.max(0, Math.min(frameRect.height, endRect.bottom - frameRect.top));
        const value = Math.round(end * 100) / 100 + 'px';
        if (value !== previous) {
            frame.style.setProperty('--public-paper-content-end', value);
            previous = value;
        }
    };

    // Content, not the decoration, is observed. Opening a journal entry,
    // revealing another Home artwork or loading a Gallery image updates
    // its natural height without intervals or animations.
    observer.observe(frame);
    observer.observe(content);
    const flow = content.querySelector(':scope > .public-content-reveal');
    if (flow instanceof HTMLElement) observer.observe(flow);
    const mutationObserver = new MutationObserver(update);
    mutationObserver.observe(content, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['hidden', 'open'],
    });
    document.fonts?.addEventListener?.('loadingdone', update);
    update();

    return () => {
        disposed = true;
        observer.disconnect();
        mutationObserver.disconnect();
        document.fonts?.removeEventListener?.('loadingdone', update);
        frame.style.removeProperty('--public-paper-content-end');
    };
}
