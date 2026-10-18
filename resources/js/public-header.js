/* Public artist-name placement uses the actual rendered title and the
 * configured menu shell. It does not infer breakpoints from viewport presets. */
export function headerNeedsStack(mode, menuWidth, titleWidth, outsideSpace, rem = 16) {
    if (mode === 'outside') {
        return outsideSpace < titleWidth + 1.25 * rem;
    }
    if (mode === 'inside') {
        // The existing menu needs enough space for whole labels and its
        // 44px overflow controls, even when the title becomes very large.
        return menuWidth - titleWidth - 1.7 * rem < 240;
    }
    return false;
}

export function initializePublicHeader() {
    const header = document.querySelector('.site-header');
    const menu = header?.querySelector('.site-navigation');
    const title = header?.querySelector('.site-title');
    if (!(header instanceof HTMLElement)
        || !(menu instanceof HTMLElement)
        || !(title instanceof HTMLElement)) return () => {};

    const mode = header.classList.contains('site-header--name-outside')
        ? 'outside'
        : (header.classList.contains('site-header--name-inside') ? 'inside' : 'below');
    if (mode === 'below') return () => {};

    let disposed = false;
    const update = () => {
        if (disposed) return;
        const menuRect = menu.getBoundingClientRect();
        const headerRect = header.getBoundingClientRect();
        const rem = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        const titleWidth = title.scrollWidth;
        const compact = headerNeedsStack(
            mode,
            menuRect.width,
            titleWidth,
            menuRect.left - headerRect.left,
            rem,
        );
        header.classList.toggle('is-title-stacked', compact);
    };

    // Synchronous first fit prevents a fixed-breakpoint intermediate state.
    update();
    const observer = new ResizeObserver(update);
    observer.observe(header);
    observer.observe(menu);
    observer.observe(title);
    document.fonts?.addEventListener?.('loadingdone', update);

    return () => {
        disposed = true;
        observer.disconnect();
        document.fonts?.removeEventListener?.('loadingdone', update);
    };
}
