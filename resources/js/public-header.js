/* The canonical Inside Menu title uses two physical regions at desktop
 * widths and becomes the first row above navigation on narrow screens.
 * Its menu width is CSS-owned, unchanged by title stacking or Left/Right. */
const MIN_TITLE_RAIL_REM = 15;
const TITLE_PADDING_REM = 1.7;
const MIN_NAV_RAIL_REM = 15;
const TITLE_RAIL_SHARE = 0.32;

export function headerNeedsStack(menuWidth, titleWidth, narrowViewport = false, rem = 16) {
    if (narrowViewport) return true;

    const titleRailWidth = Math.max(MIN_TITLE_RAIL_REM * rem, menuWidth * TITLE_RAIL_SHARE);
    return titleWidth + TITLE_PADDING_REM * rem > titleRailWidth
        || menuWidth - titleRailWidth < MIN_NAV_RAIL_REM * rem;
}

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
    const menu = header?.querySelector('.site-navigation');
    if (!(header instanceof HTMLElement)
        || !header.classList.contains('site-header--name-inside')
        || !(title instanceof HTMLElement)
        || !(menu instanceof HTMLElement)) return () => {};

    let disposed = false;
    const measurementContext = document.createElement('canvas').getContext('2d');
    const update = () => {
        if (disposed) return;
        const rem = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        const stacked = headerNeedsStack(
            menu.getBoundingClientRect().width,
            intrinsicTitleWidth(title, measurementContext),
            window.matchMedia('(max-width: 40rem)').matches,
            rem,
        );
        header.classList.toggle('is-title-stacked', stacked);
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
