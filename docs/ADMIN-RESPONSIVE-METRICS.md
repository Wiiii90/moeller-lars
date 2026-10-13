# Admin responsive: Metrics

This contract preserves the corresponding task-specific responsive requirements from the former `ui-skills.md` section 21. [Responsive ownership/index](ADMIN-RESPONSIVE-CONTRACT.md) separates shared primitive rules from page-specific acceptance. This is a **documentation move**, not evidence of a fresh browser-acceptance run.

## Metrics

**Central geometry:** `.admin-metrics` owns Narrow as **three equal columns**, with dividers at 1/3 and 2/3, and Minimal as **two equal columns** with one centered divider (true 50/50). Feature code chooses semantic visibility/priority, not its own responsive spans or duplicate metric divider borders; no one-column metric lists. Analytics, Storage and Activity explicitly use full-cell columns 1/2/3 in Narrow and columns 1/2 in Minimal, rather than preserving stale spans that yield asymmetric geometry. These tracks do not force visual-stage breakpoints.

- Wide keeps the accepted single-row strip where it fits cleanly.
- Compact reduces non-essential detail before changing the grid.
- Six-metric strips normally move to a three-column intermediate grid before the smallest retained two-column grid. Track count and semantic visibility switch atomically; six visible metrics in a three-column 3+3 intermediate state is invalid.
- **Dashboard is an explicit exception:** Wide and Compact keep all six metrics; Narrow keeps exactly three semantic metrics (Visits, Published Artworks, Recent Changes); Minimal keeps exactly two (Visits, Published Artworks).
- **General uses 6 / 6 / 3 / 2** across Wide / Compact / Narrow / Minimal. Narrow retains Public email + Contact delivery + Legal; Minimal retains Public email + Contact delivery. Like Dashboard, General may enter the 2-metric Minimal state **only after the sidebar has collapsed to the Burger shell and the workspace itself is <=38rem**; a narrow desktop/sidebar workspace remains the 3-metric Narrow state. The first visible metric is determined semantically, never from its original DOM nth-child position; only the actual left visual cell loses its leading inset.
- **Pages, Gallery, Custom Page and Journal also use 6 / 6 / 3 / 2**, with semantic priorities:
  - Pages: Published + Unpublished + Galleries -> Published + Unpublished.
  - Pages Settings is an autosaving `AdminDialog::edit()`, like Dashboard Settings: Filament Toggle and Select values persist on discrete change events through `PagesSettingsDialog`, `HomeRoutingSettingsService`, `AdminSettingsService` and `SiteSectionOrderService`; the dialog stays open, has no save/commit button and closes through native X. Its toggles use the panel-wide `AdminControl` styling. With existing child pages, the irreversible flatten warning is shown **before** switching off `Allow nested pages`; the switch immediately flattens the hierarchy, and turning it on again cannot restore the former parents. The shared Home routing fields suppress redundant helper copy in Pages only; Home retains its existing descriptions.
- The Home row's `Skip Home` / `Show Home` state button is a **one-click mutation**, never a dedicated Skip Home Action modal. It toggles routing through `HomeRoutingSettingsService`, checks for an eligible destination before enabling and refreshes the Pages table. The two-slot destination shortcut on the Home row is an inline action dropdown: its menu offers “Next page in order” and eligible published top-level targets, and persists directly through the same `HomeRoutingSettingsService`; it must never open the Home settings dialog. Skip Home retains its existing registered Skip icon; Show Home uses the standard Heroicon `no-symbol` (the crossed-circle/ban sign). **All semantic row-action icons** render at Filament `IconSize::Small` (1rem, fixed inline/flex geometry) in the one shared `row-action` component, so changing an action's state or SVG shape cannot enlarge a table row. Do not restore the removed custom Show Home SVG or add per-action icon sizing. The Home destination popover uses the common `admin-selection__menu` **surface**, but its owner is an ordinary table-row action (`admin-pages__redirect-target`), never the `admin-selection__anchor` toolbar container with its 2.5rem min-height; toggling Skip Home must not increase the Home row height. Target options are shared with the existing Home settings schema, not duplicated.
  - Gallery: Artworks + Published + Visits -> Artworks + Published.
  - Custom Page: Components + Visits + Views -> Components + Visits.
  - Blog: Published + Scheduled + Draft -> Published + Draft.
  - Exhibitions: Published + Current + Upcoming -> Current + Upcoming.
  - Analytics: Visits + Unique visitors + Tracked actions -> Visits + Unique visitors.
  - Storage: Original storage + Remaining + Files -> Original storage + Remaining.
  - Activity: Changes + Pending + Commits -> Changes + Pending.
  - Home Hero Artwork: Visits + Eligible Artworks + Candidate Group -> Visits + Eligible Artworks.
  - Home Under Construction / Custom: Components + Images + Media References -> Components + Media References.
  - Home follows Dashboard's state gating: the Narrow set is exact thirds across the full metric strip; the two-metric Minimal set is exact 50/50 and may engage only when the Burger shell is active and the workspace is <=38rem.
- Dashboard uses four effective presentation states only: Wide, Compact, Narrow and Minimal. The burger/mobile shell is not an additional Dashboard state; shell collapse may force Narrow but must never create a parallel responsive composition.
- Never reduce a metric strip to a one-column list.
- Minimal may omit a metric strip entirely only where that feature's own contract allows it; this does not override the Dashboard 6/6/3/2 rule.
- Metric importance is semantic; do not hide arbitrary nth children merely to fit.

