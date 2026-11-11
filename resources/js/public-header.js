/* The Inside Menu title and navigation share the Page reading width.
 * The visible Menu owns that padding in every layout state, so measuring
 * its content width cannot feed back through is-title-stacked. */
const MIN_TITLE_RAIL_REM = 15;
const MIN_NAV_RAIL_REM = 15;
const TITLE_RAIL_SHARE = 0.32;

export function headerNeedsStack(menuWidth, titleWidth, narrowViewport = false, rem = 16) {
    if (narrowViewport) return true;

    const titleRailWidth = Math.max(MIN_TITLE_RAIL_REM * rem, menuWidth * TITLE_RAIL_SHARE);
    return titleWidth > titleRailWidth
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
        const menuStyle = getComputedStyle(menu);
        const readingWidth = menu.clientWidth
            - (Number.parseFloat(menuStyle.paddingLeft) || 0)
            - (Number.parseFloat(menuStyle.paddingRight) || 0);
        const stacked = headerNeedsStack(
            Math.max(0, readingWidth),
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
