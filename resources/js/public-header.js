/* Artist-title stacking uses the rendered Menu and paper geometry.
 * Their width is CSS-owned and unchanged by is-title-stacked; only title
 * positioning and navigation rows change. No layout feedback or timers. */
const INSIDE_MIN_TITLE_RAIL_REM = 15;
const INSIDE_TITLE_PADDING_REM = 1.7;
const INSIDE_MIN_NAV_RAIL_REM = 15;
const INSIDE_TITLE_SHARE = 0.32;

export function headerGutter(viewportWidth, rem = 16) {
    // Mirrors the root --public-gutter clamp at the document viewport.
    return Math.min(1.5 * rem, Math.max(.65 * rem, viewportWidth * .02));
}

/* Beside starts at the viewport gutter and stops at the *visible* Menu
 * paper, not necessarily the navigation width on Artwork pages. These
 * source rectangles are fixed by CSS, not by title stacking. */
export function headerBesideGeometry(menuLeft, paperLeft, gutter) {
    return {
        offset: Math.max(0, menuLeft - gutter),
        railWidth: Math.max(0, paperLeft - gutter),
    };
}

/* Beside receives the available exterior rail width, Inside receives the
 * available Menu width. Both are measured from the same stable shell geometry. */
export function headerNeedsStack(mode, availableWidth, titleWidth, rem = 16) {
    if (mode === 'outside') {
        return titleWidth + INSIDE_TITLE_PADDING_REM * rem > availableWidth;
    }
    if (mode === 'inside') {
        // The real title rail is minmax(15rem, 32%) in the unstacked menu.
        // The name must fit INSIDE it, and navigation retains its own rail.
        const nameRail = Math.max(INSIDE_MIN_TITLE_RAIL_REM * rem, availableWidth * INSIDE_TITLE_SHARE);
        return titleWidth + INSIDE_TITLE_PADDING_REM * rem > nameRail
            || availableWidth - nameRail < INSIDE_MIN_NAV_RAIL_REM * rem;
    }
    return false;
}

/* A wrapped link has a different rendered width from its real text width.
 * Canvas reads font glyph metrics without depending on the layout state. */
function intrinsicTitleWidth(title, context) {
    const style = getComputedStyle(title);
    if (!context) return title.scrollWidth;
    context.font = [style.fontStyle, style.fontVariant, style.fontWeight, style.fontSize, style.fontFamily].join(' ');
    const value = title.textContent.replace(/\s+/g, ' ').trim();
    const tracking = Number.parseFloat(style.letterSpacing);
    return Math.ceil(context.measureText(value).width
        + (Number.isFinite(tracking) ? Math.max(0, value.length - 1) * tracking : 0)
        + 4);
}

export function initializePublicHeader() {
    const header = document.querySelector('.site-header');
    const title = header?.querySelector('.site-title');
    if (!(header instanceof HTMLElement) || !(title instanceof HTMLElement)) return () => {};

    const mode = header.classList.contains('site-header--name-outside')
        ? 'outside'
        : (header.classList.contains('site-header--name-inside') ? 'inside' : 'other');
    const menu = header.querySelector('.site-navigation');
    if (mode === 'other' || !(menu instanceof HTMLElement)) return () => {};

    let disposed = false;
    // Reuse one offscreen canvas context throughout this route's lifetime.
    const measurementContext = document.createElement('canvas').getContext('2d');
    const update = () => {
        if (disposed) return;
        const rem = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        const gutter = headerGutter(document.documentElement.clientWidth, rem);
        // This actual Menu element has a CSS-defined shell width and retains
        // that width whether its title is inline or stacked. Reading the
        // outer wrapper for the paper also handles fitted Artwork pages.
        const menuRect = menu.getBoundingClientRect();
        const menuWidth = menuRect.width;
        let outsideRailWidth = 0;
        if (mode === 'outside') {
            const surface = header.querySelector('.site-navigation-surface');
            const paperLeft = surface instanceof HTMLElement && getComputedStyle(surface).display !== 'contents'
                ? surface.getBoundingClientRect().left
                : menuRect.left;
            const beside = headerBesideGeometry(menuRect.left, paperLeft, gutter);
            outsideRailWidth = beside.railWidth;
            menu.style.setProperty('--public-title-outside-offset', `${beside.offset}px`);
            menu.style.setProperty('--public-title-outside-width', `${beside.railWidth}px`);
        }
        const titleWidth = intrinsicTitleWidth(title, measurementContext);
        const stacked = headerNeedsStack(
            mode,
            mode === 'outside' ? outsideRailWidth : menuWidth,
            titleWidth,
            rem,
        );
        if (header.classList.contains('is-title-stacked') !== stacked) {
            header.classList.toggle('is-title-stacked', stacked);
        }
    };

    update();
    window.addEventListener('resize', update, { passive: true });
    document.fonts?.addEventListener?.('loadingdone', update);

    return () => {
        disposed = true;
        window.removeEventListener('resize', update);
        document.fonts?.removeEventListener?.('loadingdone', update);
    };
}
