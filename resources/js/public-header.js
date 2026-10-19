/* Artist-title stacking is decided from stable browser/configuration
 * geometry, never from a rail whose size changes with is-title-stacked.
 * This is a one-way calculation on initial render, browser resize, and
 * font completion. No element observer, timer or layout feedback. */
const INSIDE_MIN_TITLE_RAIL_REM = 15;
const INSIDE_TITLE_PADDING_REM = 1.7;
const INSIDE_MIN_NAV_RAIL_REM = 15;
const INSIDE_TITLE_SHARE = 0.32;
const OUTSIDE_MAX_TITLE_SHELL_SHARE = 0.5;

export function headerShellGeometry(viewportWidth, shellMax, rem = 16) {
    // Mirrors the public shell's single CSS gutter clamp. Unlike a
    // getBoundingClientRect() read on the active navigation, this geometry
    // is unaffected by the stacked-title class or page scrollbars.
    const gutter = Math.min(1.5 * rem, Math.max(.65 * rem, viewportWidth * .02));
    const menuWidth = Math.max(0, Math.min(shellMax, viewportWidth - 2 * gutter));
    const outsideSpace = Math.max(0, (viewportWidth - menuWidth) / 2);
    return { gutter, menuWidth, outsideSpace };
}

export function headerNeedsStack(mode, menuWidth, titleWidth, outsideSpace, rem = 16) {
    if (mode === 'outside') {
        // Beside's content-aligned title slot uses at most half of the
        // configured shell width. Reserve both gutters at its sides.
        return outsideSpace < titleWidth + 3 * rem;
    }
    if (mode === 'inside') {
        // The real title rail is minmax(15rem, 32%) in the unstacked menu.
        // The name must fit INSIDE it, and navigation retains its own rail.
        const nameRail = Math.max(INSIDE_MIN_TITLE_RAIL_REM * rem, menuWidth * INSIDE_TITLE_SHARE);
        return titleWidth + INSIDE_TITLE_PADDING_REM * rem > nameRail
            || menuWidth - nameRail < INSIDE_MIN_NAV_RAIL_REM * rem;
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
        const { menuWidth, outsideSpace } = headerShellGeometry(window.innerWidth, configuredShellMax, rem);
        const titleWidth = intrinsicTitleWidth(title, measurementContext);
        const besideRail = Math.min(outsideSpace, configuredShellMax * OUTSIDE_MAX_TITLE_SHELL_SHARE);
        const stacked = headerNeedsStack(mode, menuWidth, titleWidth, besideRail, rem);
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
