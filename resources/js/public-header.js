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

/* Text remains measurable after it wraps in the compact menu header.
 * Measuring the rendered link's box here would make the breakpoint flip
 * between stacked and unstacked states on every ResizeObserver cycle. */
function intrinsicTitleWidth(title) {
    const style = getComputedStyle(title);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    if (!context) return title.scrollWidth;
    context.font = [style.fontStyle, style.fontVariant, style.fontWeight, style.fontSize, style.fontFamily].join(' ');
    const text = title.textContent.replace(/\s+/g, ' ').trim();
    const tracking = Number.parseFloat(style.letterSpacing);
    return Math.ceil(context.measureText(text).width
        + (Number.isFinite(tracking) ? Math.max(0, text.length - 1) * tracking : 0)
        + 4);
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
        const titleWidth = intrinsicTitleWidth(title);
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
