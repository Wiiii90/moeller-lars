# Admin responsive: Visual Stages

This contract preserves the corresponding task-specific responsive requirements from the previous Admin UI source contract, now [Admin UI](ADMIN-UI.md), section 21. [Responsive ownership/index](ADMIN-RESPONSIVE-CONTRACT.md) separates shared primitive rules from page-specific acceptance. This is a **documentation move**, not evidence of a fresh browser-acceptance run.

## Visual Stages

**Stage geometry is centrally owned by [stage.css](../resources/css/admin/stage.css):** the stage height uses `--admin-visual-stage-height` and common orbit graphics use `--admin-stage-orbit-size`. Both remain canonical across responsive states; do not redefine those tokens in a breakpoint or add page-local alternative stage-height/orbit-size tokens without an explicit accepted contract change. Change component visibility/composition, not the central stage height. This does not authorize generic narrow vertical stacking.

Shared desktop stage geometry does not imply shared narrow composition.

- Never automatically stack the existing desktop panes vertically as the generic responsive solution.
- Every stage defines an intentional Narrow/Minimal composition for its own task.
- Preserve required operations and domain state; optional/redundant charts, distribution visuals and parallel previews may disappear.
- A narrow stage may become one focused surface and may use a small local presentation-only selector when equivalent views still need to be reachable.
- Sidebar/burger collapse is monotonic for Pages, Gallery, Custom Page and Journal too: removing the sidebar must never make metrics, columns, labels or Gallery card density jump back to a wider state.
- Analytics/Storage/Activity burger/sidebar collapse is monotonic as well: it must not reintroduce Geography, the Storage triptych, Activity Clock, wider table columns or denser Storage cards.
- General keeps the parallel Live Preview in Wide/Compact. Narrow/Minimal omit it and expand the single Appearance controls pane across the full stage. Page width and Content padding are ordinary General Appearance fields in that same parent form/persistence path; they must not live in a nested Livewire bridge or a detached pseudo-footer. The bounded Appearance pane scrolls when necessary but keeps its scrollbar chrome hidden. General has one container-owned Narrow composition (up to 64rem workspace) rather than a second viewport/Burger copy; the preview therefore cannot reappear or partially revert when the sidebar collapses. Live Preview fitting responds immediately to its ResizeObserver and must not animate width/height between observer ticks.
- General status metrics and the Appearance Stage share the same schema-backed stage block so only the canonical stage margin separates them; Filament schema gaps must not add a second vertical offset.
- General Site icon reserves a stable field footprint whether empty or selected; media thumbnail appearance must not move the controls below it.
- Analytics preserves its desktop Map (2 cells) + Geography (1 cell) composition through Narrow. Only Minimal removes Geography and lets Map occupy the full shared stage.
- Storage preserves Upload + Capacity + Distribution as three equal stage cells through Narrow. Only Minimal becomes the compact full-width Upload + Used/Remaining/Allowance + Refresh/Reclaim composition; donut and Distribution disappear there.
- Activity preserves Calendar + Clock + Next Publication as three stage cells through Narrow. Only Minimal becomes publication-first: Next Publication owns the full shared stage while Calendar + Clock yield; Pending/Preflight/current-live context and Review/Reset/Commit remain visible/reachable.
- **Dashboard keeps Storage | Activity | Analytics as three simultaneous stage cells through Wide, Compact and Narrow. Only Minimal may switch to the local Storage / Activity / Analytics selector and show one stage cell at a time.** The one-cell Dashboard stage must not engage before the burger/sidebar shell has collapsed; shell collapse alone must not trigger it either.
- Dashboard stage captions/facts remain visible and one-line through Narrow. Use deliberate compact labels/copy when needed; primary values and facts must not fall back to ellipsis. The Dashboard stage owns a smooth container-relative height; footer wrapping must never change graphic vertical position.
- Presentation-only switching stays local (CSS/Alpine); Livewire/Laravel do not track resize state.

Browser review must continuously resize through transition regions, not only check named device presets.

