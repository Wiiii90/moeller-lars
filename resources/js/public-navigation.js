// Resolve only immediate children: otherwise the main navigation could bind
// its right arrow to a descendant submenu's right arrow.
export function navigationWindowParts(surface) {
    const direct = Array.from(surface.children);
    const content = direct.find((element) => element.matches('[data-navigation-content]'));
    const railChildren = content ? Array.from(content.children) : direct;
    return {
        rail: railChildren.find((element) => element.matches('nav[data-navigation-window-scroll]')),
        previous: direct.find((element) => element.matches('button[data-direction="previous"]')),
        next: direct.find((element) => element.matches('button[data-direction="next"]')),
    };
}

// Arrows are only necessary when the complete set exceeds the rail.
// A single overwide label wraps in place rather than creating useless arrows.
export function needsNavigationOverflow(widths, availableWidth) {
    return widths.length > 1
        && widths.reduce((total, width) => total + width, 0) > availableWidth;
}

// Navigation is server-rendered. A destination-scoped session handoff
// preserves the exact visible window when an internal navigation link reloads
// the page. No URL parameters, duplicate renderer or background polling.
const NAVIGATION_HANDOFF_KEY = 'moeller:public-navigation-handoff';

function navigationDestination(url) {
    return url.pathname + url.search;
}

function readNavigationHandoff() {
    try {
        const handoff = JSON.parse(window.sessionStorage.getItem(NAVIGATION_HANDOFF_KEY) ?? 'null');
        return handoff?.destination === navigationDestination(window.location)
            && handoff.windows && typeof handoff.windows === 'object'
            ? handoff.windows
            : null;
    } catch {
        // Storage can be disabled; direct-entry navigation still works.
        return null;
    }
}

export function restoreNavigationWindow(widths, availableWidth, activeIndex, saved, keys) {
    if (!needsNavigationOverflow(widths, availableWidth)) {
        return widths.map((_, index) => index);
    }

    // Only the Site Node order determines whether a handoff is still valid.
    // A scrollbar appearing or disappearing may change rail width without
    // changing the user's window selection.
    if (saved && Array.isArray(saved.keys) && Array.isArray(saved.visible)
        && saved.keys.length === keys.length
        && keys.every((key, index) => key === saved.keys[index])
        && saved.visible.length > 0
        && new Set(saved.visible).size === saved.visible.length
        && saved.visible.every((index, offset) => Number.isInteger(index)
            && index >= 0 && index < widths.length
            && (offset === 0 || index === (saved.visible[offset - 1] + 1) % widths.length))) {
        const occupied = saved.visible.reduce((sum, index) => sum + widths[index], 0);
        if (saved.visible.length === 1 || occupied <= availableWidth) {
            // Even a hidden selected parent cannot rearrange a fitting stack.
            return [...saved.visible];
        }

        // Only the changed available space may shrink the visible stack.
        // Refit from its original first item, never from the selected parent.
        return fitNavigationWindow(widths, availableWidth, saved.visible[0]);
    }

    // A direct URL has no previous window to preserve. Start with the
    // relevant page visible without simulating arrow clicks.
    const initial = fitNavigationWindow(widths, availableWidth);
    return activeIndex === -1 || initial.includes(activeIndex)
        ? initial
        : fitNavigationWindow(widths, availableWidth, activeIndex);
}

// Both navigation levels use the same whole-item cyclic window and never scroll pixels.
// The surface is a three-column grid: previous control, canonical content width, next control.
function initializeNavigationWindow(surface, restoredWindows) {
    const { rail, previous, next } = navigationWindowParts(surface);
    if (!(rail instanceof HTMLElement)
        || !(previous instanceof HTMLButtonElement)
        || !(next instanceof HTMLButtonElement)) {
        throw new Error('Public navigation window is missing required controls.');
    }

    const items = Array.from(rail.children).filter((item) => item.matches('[data-navigation-item]'));
    const activeIndex = items.findIndex((item) => item.classList.contains('is-active'));
    const keys = items.map((item) => item.dataset.navigationKey);
    const surfaceKey = surface.hasAttribute('data-navigation-main') ? 'main' : surface.id;
    let initialized = false;
    let widths = [];
    let visible = [];
    let availableWidth = 0;
    let cycling = false;
    let observedWidth = -1;

    const render = (indices) => {
        const shown = new Set(indices);
        const focused = document.activeElement;
        const focusedItem = focused instanceof Element
            ? focused.closest('[data-navigation-item]')
            : null;

        items.forEach((item, index) => {
            item.hidden = !shown.has(index);
            item.classList.toggle('is-overwide', shown.has(index) && widths[index] > availableWidth);
        });

        // Reorder existing DOM nodes, not copies, to match the cyclic visual,
        // focus and screen-reader order when the sequence wraps around.
        const ordered = [
            ...indices.map((index) => items[index]),
            ...items.filter((_, index) => !shown.has(index)),
        ];
        if (ordered.some((item, position) => rail.children[position] !== item)) {
            rail.append(...ordered);
        }

        visible = indices;
        if (focusedItem && focused instanceof HTMLElement) {
            if (shown.has(items.indexOf(focusedItem))) {
                focused.focus({ preventScroll: true });
            } else if (cycling) {
                previous.focus({ preventScroll: true });
            }
        }
    };

    const refresh = () => {
        if (surface.hidden) return;

        surface.classList.add('is-measuring');
        items.forEach((item) => {
            item.hidden = false;
            item.classList.remove('is-overwide');
        });
        widths = items.map((item) => item.getBoundingClientRect().width);
        surface.classList.remove('is-measuring');

        // Text-only navigation has no item borders to reserve.
        availableWidth = Math.max(1, rail.clientWidth);
        // ResizeObserver delivers an initial notification even without a size
        // change. Mark the measured width now, or that callback would re-fit
        // and shift an exactly restored cyclic window immediately after load.
        observedWidth = rail.getBoundingClientRect().width;
        const wasCycling = cycling;
        if (surface.classList.contains('is-hover-preview')) {
            cycling = false;
            surface.classList.remove('is-overflowing');
            previous.hidden = true;
            next.hidden = true;
            initialized = true;
            render(items.map((_, index) => index));
            return;
        }
        cycling = needsNavigationOverflow(widths, availableWidth);

        surface.classList.toggle('is-overflowing', cycling);
        previous.hidden = !cycling;
        next.hidden = !cycling;

        if (!cycling) {
            initialized = true;
            render(items.map((_, index) => index));
            return;
        }

        let fitted;
        if (!initialized) {
            fitted = restoreNavigationWindow(
                widths, availableWidth, activeIndex, restoredWindows?.[surfaceKey], keys,
            );
        } else {
            fitted = fitNavigationWindow(widths, availableWidth, visible[0] ?? 0);
        }
        initialized = true;
        render(fitted);
    };

    const shift = (direction) => {
        if (!cycling) return;
        render(advanceNavigationWindow(widths, visible, availableWidth, direction));
    };
    previous.addEventListener('click', () => shift(-1));
    next.addEventListener('click', () => shift(1));

    let resizeObserver = null;
    if ('ResizeObserver' in window) {
        resizeObserver = new ResizeObserver(() => {
            const width = rail.getBoundingClientRect().width;
            if (Math.abs(observedWidth - width) > 0.5) {
                observedWidth = width;
                refresh();
            }
        });
        resizeObserver.observe(rail);
    } else {
        window.addEventListener('resize', refresh, { passive: true });
    }

    return {
        items, refresh, previous, next,
        snapshot: () => {
            if (initialized && visible.length) {
                return { keys, visible: [...visible], width: availableWidth };
            }
            // Preserve the main window while a child level is server-rendered.
            const saved = restoredWindows?.[surfaceKey];
            return saved && Array.isArray(saved.keys) && Array.isArray(saved.visible)
                && saved.keys.length === keys.length
                && keys.every((key, index) => key === saved.keys[index])
                ? saved : null;
        },
        key: surfaceKey,
        destroy: () => {
            resizeObserver?.disconnect();
            if (!resizeObserver) window.removeEventListener('resize', refresh);
        },
    };
}

function submenuControls(item, container) {
    const parentLink = item.querySelector('[data-navigation-parent-link]');
    const submenuId = parentLink?.getAttribute('aria-controls');
    const submenu = submenuId ? document.getElementById(submenuId) : null;
    const back = submenu?.querySelector('[data-navigation-back]');
    if (!(parentLink instanceof HTMLElement)
        || !(submenu instanceof HTMLElement)
        || !(back instanceof HTMLButtonElement)
        || !container.contains(submenu)) return null;
    return { parentLink, submenu, back };
}

function initializeNavigationLevels(container, mainSurface, main, restoredWindows, windows) {
    const entries = main.items
        .map((item) => ({ item, controls: submenuControls(item, container) }))
        .filter((entry) => entry.controls !== null)
        .map((entry) => ({
            ...entry,
            window: initializeNavigationWindow(entry.controls.submenu, restoredWindows),
        }));
    windows.push(...entries.map((entry) => entry.window));

    let activeEntry = entries.find(({ controls }) => !controls.submenu.hidden) ?? null;
    let hoverEntry = null;

    const hideHover = () => {
        if (hoverEntry === null) return;
        const previous = hoverEntry;
        hoverEntry = null;
        previous.controls.submenu.classList.remove('is-hover-preview');
        previous.controls.submenu.hidden = true;
        previous.controls.parentLink.setAttribute('aria-expanded', 'false');
    };

    const showHover = (entry) => {
        if (activeEntry !== null || mainSurface.hidden || container.dataset.hoverPreview !== 'true'
            || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        if (hoverEntry === entry) return;
        hideHover();
        hoverEntry = entry;
        entry.controls.submenu.classList.add('is-hover-preview');
        entry.controls.submenu.hidden = false;
        entry.controls.parentLink.setAttribute('aria-expanded', 'true');
        entry.window.refresh();
    };

    container.addEventListener('pointerleave', hideHover);

    const showMain = (restoreFocus = false) => {
        if (activeEntry === null) return;
        const previous = activeEntry;
        activeEntry = null;
        previous.controls.submenu.hidden = true;
        previous.controls.parentLink.setAttribute('aria-expanded', 'false');
        previous.item.dataset.submenuOpen = 'false';
        mainSurface.hidden = false;
        main.refresh();

        if (restoreFocus) {
            const target = previous.item.hidden
                ? (main.previous.hidden
                    ? main.items.find((item) => !item.hidden)?.querySelector('a, button')
                    : main.previous)
                : previous.controls.parentLink;
            target?.focus({ preventScroll: true });
        }
    };

    const showChildren = (entry, focusBack = false) => {
        hideHover();
        if (activeEntry !== null && activeEntry !== entry) {
            activeEntry.controls.submenu.hidden = true;
            activeEntry.controls.parentLink.setAttribute('aria-expanded', 'false');
            activeEntry.item.dataset.submenuOpen = 'false';
        }
        activeEntry = entry;
        mainSurface.hidden = true;
        entry.controls.submenu.hidden = false;
        entry.controls.parentLink.setAttribute('aria-expanded', 'true');
        entry.item.dataset.submenuOpen = 'true';
        entry.window.refresh();
        if (focusBack) entry.controls.back.focus({ preventScroll: true });
    };

    for (const entry of entries) {
        const { parentLink, submenu, back } = entry.controls;
        back.addEventListener('click', () => showMain(true));
        entry.item.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') showHover(entry);
        });

        parentLink.addEventListener('click', (event) => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey
                || event.shiftKey || event.altKey) return;
            event.preventDefault();
            showChildren(entry, true);
        });
        parentLink.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown') return;
            event.preventDefault();
            showChildren(entry);
            submenu.querySelector('.site-navigation__item:not([hidden]) a')?.focus({ preventScroll: true });
        });

        submenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                showMain(true);
                return;
            }
            if (!['ArrowLeft', 'ArrowRight', 'ArrowDown', 'ArrowUp'].includes(event.key)) return;
            const links = Array.from(submenu.querySelectorAll('.site-navigation__item:not([hidden]) a'));
            const current = links.indexOf(document.activeElement);
            if (current === -1 || links.length === 0) return;
            event.preventDefault();
            const offset = event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1;
            const destination = current + offset;
            if (destination >= 0 && destination < links.length) {
                links[destination].focus();
            } else {
                const control = offset > 0 ? entry.window.next : entry.window.previous;
                if (!control.hidden) control.click();
                const updated = Array.from(submenu.querySelectorAll('.site-navigation__item:not([hidden]) a'));
                (offset > 0 ? updated[updated.length - 1] : updated[0])?.focus();
            }
        });
    }

    // Blade already places an active branch into the same row before JS runs.
    if (activeEntry) showChildren(activeEntry);
}

// The visible menu is a cyclic window of complete Site Node items.
export function fitNavigationWindow(widths, availableWidth, startIndex = 0) {
    if (widths.length === 0) return [];

    const start = ((startIndex % widths.length) + widths.length) % widths.length;
    const visible = [start];
    let occupied = widths[start];

    for (let step = 1; step < widths.length; step += 1) {
        const next = (start + step) % widths.length;
        if (occupied + widths[next] > availableWidth) break;
        visible.push(next);
        occupied += widths[next];
    }

    // Even an overwide label stays whole as one item; CSS wraps its text.
    return visible;
}

export function advanceNavigationWindow(widths, visible, availableWidth, direction) {
    if (widths.length === 0) return [];
    if (direction !== -1 && direction !== 1) {
        throw new RangeError('Navigation direction must be -1 or 1.');
    }
    if (visible.length === 0) return fitNavigationWindow(widths, availableWidth);
    if (visible.length === widths.length) return [...visible];

    const adjacent = direction === 1
        ? (visible[visible.length - 1] + 1) % widths.length
        : (visible[0] - 1 + widths.length) % widths.length;
    const candidate = direction === 1 ? [...visible, adjacent] : [adjacent, ...visible];
    let occupied = candidate.reduce((sum, index) => sum + widths[index], 0);

    while (candidate.length > 1 && occupied > availableWidth) {
        const removed = direction === 1 ? candidate.shift() : candidate.pop();
        occupied -= widths[removed];
    }

    // A former long label may have occupied the whole rail. Refill unused
    // space with following/preceding whole items in the chosen direction.
    while (candidate.length < widths.length) {
        const edge = direction === 1 ? candidate[candidate.length - 1] : candidate[0];
        const adjacentIndex = (edge + direction + widths.length) % widths.length;
        if (occupied + widths[adjacentIndex] > availableWidth) break;
        if (direction === 1) candidate.push(adjacentIndex);
        else candidate.unshift(adjacentIndex);
        occupied += widths[adjacentIndex];
    }

    return candidate;
}

export function initializePublicNavigation() {
    const disposers = [];
    document.querySelectorAll('[data-site-navigation]').forEach((container) => {
        const mainSurface = container.querySelector('[data-navigation-main]');
        if (!(mainSurface instanceof HTMLElement)) return;

        const restoredWindows = readNavigationHandoff();
        const windows = [];
        const main = initializeNavigationWindow(mainSurface, restoredWindows);
        windows.push(main);
        main.refresh();
        initializeNavigationLevels(container, mainSurface, main, restoredWindows, windows);

        // Content-to-content links also preserve the current menu window.
        const clickRoot = document.body;
        const recordHandoff = (event) => {
            if (event.defaultPrevented || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || !(event.target instanceof Element)) return;

            const link = event.target.closest('a[href]');
            if (!(link instanceof HTMLAnchorElement)
                || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;

            const target = new URL(link.href);
            if (target.origin !== window.location.origin) return;

            const snapshots = Object.fromEntries(
                windows.map((entry) => [entry.key, entry.snapshot()]).filter(([, value]) => value),
            );
            try {
                window.sessionStorage.setItem(NAVIGATION_HANDOFF_KEY, JSON.stringify({
                    destination: navigationDestination(target),
                    windows: snapshots,
                }));
            } catch {
                // Continue normal navigation when session storage is unavailable.
            }
        };
        clickRoot.addEventListener('click', recordHandoff);
        disposers.push(() => {
            clickRoot.removeEventListener('click', recordHandoff);
            windows.forEach((windowState) => windowState.destroy());
        });
    });
    return () => disposers.forEach((dispose) => dispose());
}
