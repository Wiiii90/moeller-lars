# Admin responsive: Shell and native select

This contract preserves the corresponding task-specific responsive requirements from the previous Admin UI source contract, now [Admin UI](ADMIN-UI.md), section 21. [Responsive ownership/index](ADMIN-RESPONSIVE-CONTRACT.md) separates shared primitive rules from page-specific acceptance. This is a **documentation move**, not evidence of a fresh browser-acceptance run.

Responsive behavior is content-driven and follows semantic width states rather than device labels: **Wide**, **Compact**, **Narrow** and **Minimal**. Exact thresholds come from browser fit/acceptance; admin task components should prefer available container/workspace width, while shell-level sidebar/topbar changes may remain viewport-driven.


## Native select value readability

- The central native/select grammar in `layouts.css` and `task-surfaces.css` reserves the picker-icon rail and uses a one-line ellipsis only as the final fallback. Do not shrink type or overlay an arrow on the selected value to accommodate an undersized column.
- Give columns and toolbar filter tracks enough width for the longest ordinary selected label while the control is inline: Pages Template includes “Under Construction”; Activity Type includes “Archive / delete”. Pages gives Template the necessary width in Wide and removes that supportive column at its established Compact transition. Four-filter toolbars use the **same single filter-popover mechanism** when workspace width reaches 76rem; three-filter toolbars retain their 54rem switch. Never let longer labels force row wrapping or overlap the fixed action/Selection rail.
- Check both Light/Dark native select renderings and narrow widths after changing option labels. Do not duplicate select state or reintroduce custom select JavaScript.

## Shell/header geometry

- Header controls use one shared equation: **1rem outer edge + 2rem circular control + 1rem separation to header content = 4rem control rail**.
- Desktop/sidebar mode uses one globally centered horizontal workspace frame. Filament may center its internal page frame in the post-sidebar main area, but the visible admin workspace compensates that residual-area bias so its midpoint matches the **whole browser viewport** whenever Sidebar clearance permits. Sidebar participates only as a collision limit: as the viewport narrows, the centering shift reduces before the workspace could cross Sidebar + the canonical gutter.
- The desktop User control owns a fixed 4rem viewport/header rail: 1rem outer gap + 2rem circular control + 1rem inner safety gap. The desktop Main reserves this rail as a real right content inset so the User badge never overlaps workspace content while the viewport contracts; the User rail is not a second centering origin. The visible Selection count keeps its fixed circular size when moving onto the terminal checkbox rail.
- Desktop Header Notification is exactly as wide as the actual .admin-workspace content box, from its left to right edge, and never extends to the User rail or over the Sidebar. CSS anchor positioning (anchor-name: --admin-workspace-frame) makes the persistent Notification consume the real workspace geometry, including centered Main shifts and SPA navigation, instead of duplicating calculations in notifications.css or measuring it with JavaScript. The User badge stays outside the content frame.
- Desktop .fi-layout uses the document scrollport width (100%), never 100vw; fixed header surfaces and page content must not live in different scrollbar coordinate systems.
- In the burger shell, the desktop workspace frame is reset. Page content below the topbar uses only the canonical 1rem shell inset on both sides; Burger/User rails remain confined to the header.
- Header Notification remains between Burger/User controls and may therefore use different horizontal insets from burger-mode page content below it.


