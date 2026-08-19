function navigationCanOverflow() {
    return !window.matchMedia('(max-width: 550px)').matches;
}

function submenuFor(toggle) {
    const id = toggle.getAttribute('aria-controls');
    if (!id) {
        return null;
    }

    const submenu = document.getElementById(id);
    return submenu instanceof HTMLElement ? submenu : null;
}

function positionSubmenu(toggle, submenu) {
    if (!navigationCanOverflow()) {
        submenu.style.removeProperty('--navigation-submenu-top');
        submenu.style.removeProperty('--navigation-submenu-left');
        return;
    }

    const item = toggle.closest('[data-navigation-item]');
    if (!(item instanceof HTMLElement)) {
        return;
    }

    const rect = item.getBoundingClientRect();
    const viewportPadding = 8;
    const width = submenu.offsetWidth;
    const left = Math.min(
        Math.max(viewportPadding, rect.left),
        Math.max(viewportPadding, window.innerWidth - width - viewportPadding),
    );

    submenu.style.setProperty('--navigation-submenu-top', `${Math.round(rect.bottom)}px`);
    submenu.style.setProperty('--navigation-submenu-left', `${Math.round(left)}px`);
}

function closeSubmenu(toggle, restoreFocus = false) {
    const submenu = submenuFor(toggle);
    if (!submenu) {
        return;
    }

    toggle.setAttribute('aria-expanded', 'false');
    submenu.hidden = true;
    submenu.style.removeProperty('--navigation-submenu-top');
    submenu.style.removeProperty('--navigation-submenu-left');

    if (restoreFocus) {
        toggle.focus();
    }
}

function openSubmenu(toggle) {
    const submenu = submenuFor(toggle);
    if (!submenu) {
        return;
    }

    toggle.setAttribute('aria-expanded', 'true');
    submenu.hidden = false;
    positionSubmenu(toggle, submenu);
}

function initializeSubmenus(container) {
    const toggles = Array.from(container.querySelectorAll('[data-navigation-submenu-toggle]'))
        .filter((toggle) => toggle instanceof HTMLButtonElement);

    const closeOthers = (current = null) => {
        toggles.forEach((toggle) => {
            if (toggle !== current) {
                closeSubmenu(toggle);
            }
        });
    };

    toggles.forEach((toggle) => {
        const submenu = submenuFor(toggle);
        if (!submenu) {
            return;
        }

        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            closeOthers(toggle);
            open ? closeSubmenu(toggle) : openSubmenu(toggle);
        });

        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                closeOthers(toggle);
                openSubmenu(toggle);
                const firstLink = submenu.querySelector('a');
                if (firstLink instanceof HTMLAnchorElement) {
                    firstLink.focus();
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmenu(toggle, true);
            }
        });

        submenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmenu(toggle, true);
                return;
            }

            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) {
                return;
            }

            const links = Array.from(submenu.querySelectorAll('a'))
                .filter((link) => link instanceof HTMLAnchorElement);
            const current = links.indexOf(document.activeElement);
            if (current === -1 || links.length === 0) {
                return;
            }

            event.preventDefault();
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            links[(current + offset + links.length) % links.length].focus();
        });

        const item = toggle.closest('[data-navigation-item]');
        if (item instanceof HTMLElement) {
            item.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!item.contains(document.activeElement)) {
                        closeSubmenu(toggle);
                    }
                }, 0);
            });
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!(event.target instanceof Node) || container.contains(event.target)) {
            return;
        }
        closeOthers();
    });

    window.addEventListener('resize', () => {
        toggles.forEach((toggle) => {
            const submenu = submenuFor(toggle);
            if (submenu && toggle.getAttribute('aria-expanded') === 'true') {
                positionSubmenu(toggle, submenu);
            }
        });
    }, { passive: true });

    return closeOthers;
}

export function initializePublicNavigation() {
    document.querySelectorAll('[data-site-navigation]').forEach((container) => {
        const scroller = container.querySelector('[data-site-navigation-scroll]');
        const previous = container.querySelector('[data-direction="previous"]');
        const next = container.querySelector('[data-direction="next"]');

        if (!(scroller instanceof HTMLElement)
            || !(previous instanceof HTMLButtonElement)
            || !(next instanceof HTMLButtonElement)) {
            return;
        }

        const closeSubmenus = initializeSubmenus(container);
        let dragging = false;
        let dragged = false;
        let dragStartX = 0;
        let dragStartScroll = 0;

        const update = () => {
            const overflow = navigationCanOverflow() && scroller.scrollWidth > scroller.clientWidth + 2;
            container.classList.toggle('is-overflowing', overflow);
            previous.hidden = !overflow;
            next.hidden = !overflow;

            if (!overflow) {
                scroller.scrollLeft = 0;
                previous.disabled = true;
                next.disabled = true;
                return;
            }

            const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
            previous.disabled = scroller.scrollLeft <= 1;
            next.disabled = scroller.scrollLeft >= maxScroll - 1;
        };

        const scrollByPage = (direction) => {
            closeSubmenus();
            scroller.scrollBy({
                left: direction * Math.max(180, scroller.clientWidth * .55),
                behavior: 'smooth',
            });
        };

        previous.addEventListener('click', () => scrollByPage(-1));
        next.addEventListener('click', () => scrollByPage(1));
        scroller.addEventListener('scroll', () => {
            closeSubmenus();
            update();
        }, { passive: true });

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse'
                || !container.classList.contains('is-overflowing')
                || (event.target instanceof Element && event.target.closest('[data-navigation-submenu-toggle]'))) {
                return;
            }

            dragging = true;
            dragged = false;
            dragStartX = event.clientX;
            dragStartScroll = scroller.scrollLeft;
            container.classList.add('is-dragging');
            closeSubmenus();
            scroller.setPointerCapture(event.pointerId);
        });

        scroller.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            const delta = event.clientX - dragStartX;
            if (Math.abs(delta) > 4) {
                dragged = true;
            }
            scroller.scrollLeft = dragStartScroll - delta;
        });

        const finishDrag = (event) => {
            if (!dragging) {
                return;
            }

            dragging = false;
            container.classList.remove('is-dragging');
            if (scroller.hasPointerCapture(event.pointerId)) {
                scroller.releasePointerCapture(event.pointerId);
            }
            update();
        };

        scroller.addEventListener('pointerup', finishDrag);
        scroller.addEventListener('pointercancel', finishDrag);
        scroller.addEventListener('click', (event) => {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            dragged = false;
        }, true);

        if ('ResizeObserver' in window) {
            new ResizeObserver(update).observe(scroller);
        } else {
            window.addEventListener('resize', update, { passive: true });
        }

        update();
    });
}
