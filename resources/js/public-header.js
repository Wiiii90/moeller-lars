/* Artist-title stacking is decided from stable browser/configuration
 * geometry, never from a rail whose size changes with is-title-stacked.
 * This is a one-way calculation on initial render, browser resize, and
 * font completion. No element observer, timer or layout feedback. */
const INSIDE_MIN_TITLE_RAIL_REM = 15;
const INSIDE_TITLE_PADDING_REM = 1.7;
const INSIDE_MIN_NAV_RAIL_REM = 15;
const INSIDE_TITLE_SHARE = 0.32;

export function headerShellGeometry(viewportWidth, shellMax, rem = 16) {
    // Mirrors the public shell's single CSS gutter clamp. Unlike a
    // getBoundingClientRect() read on the active navigation, this geometry
    // is unaffected by the stacked-title class or page scrollbars.
    const gutter = Math.min(1.5 * rem, Math.max(.65 * rem, viewportWidth * .02));
    const menuWidth = Math.max(0, Math.min(shellMax, viewportWidth - 2 * gutter));
    return { gutter, menuWidth };
}

/* Beside's offset starts at the left viewport gutter; its right edge
 * stops at the VISIBLE Menu paper, which may be wider than navigation on
 * fitted Artwork pages. The shell's center axis never changes. */
export function headerBesideGeometry(viewportWidth, menuWidth, paperWidth, gutter) {
    return {
        offset: Math.max(0, (viewportWidth - menuWidth) / 2 - gutter),
        railWidth: Math.max(0, (viewportWidth - Math.max(menuWidth, paperWidth)) / 2 - gutter),
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
    if (mode === 'other') return () => {};

    let disposed = false;
    // Reuse one offscreen canvas context throughout this route's lifetime.
    const measurementContext = document.createElement('canvas').getContext('2d');
    const update = () => {
        if (disposed) return;
        const root = getComputedStyle(document.documentElement);
        const rem = Number.parseFloat(root.fontSize) || 16;
        const configuredShellMax = Number.parseFloat(root.getPropertyValue('--public-shell-max')) || 800;
        const viewportWidth = document.documentElement.clientWidth;
        const { gutter, menuWidth } = headerShellGeometry(viewportWidth, configuredShellMax, rem);
        // The Artwork menu can widen its paper beyond its 800px-sized
        // navigation. Read only the stable wrapper width, never the title
        // or a column changed by is-title-stacked.
        let outsideRailWidth = 0;
        if (mode === 'outside') {
            const menu = header.querySelector('.site-navigation');
            const surface = header.querySelector('.site-navigation-surface');
            const paperWidth = surface instanceof HTMLElement && getComputedStyle(surface).display !== 'contents'
                ? Math.max(menuWidth, surface.getBoundingClientRect().width)
                : menuWidth;
            const beside = headerBesideGeometry(viewportWidth, menuWidth, paperWidth, gutter);
            outsideRailWidth = beside.railWidth;
            menu?.style.setProperty('--public-title-outside-offset', `${beside.offset}px`);
            menu?.style.setProperty('--public-title-outside-width', `${beside.railWidth}px`);
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
