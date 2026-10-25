# Admin responsive design: contract index

Admin responsiveness is driven by actual content pressure and **Wide / Compact / Narrow / Minimal** semantic states, rather than device names. Task components should respond to their own available container/workspace width; Topbar/Sidebar transitions may remain viewport-driven. These requirements were previously embedded in the 28 KB responsive chapter of the root admin UI skill.

## Task-based responsive contracts

| Owner | Scope |
| --- | --- |
| [Shell and select values](ADMIN-RESPONSIVE-SHELL.md) | Native select readability, Topbar, Sidebar, centered workspace geometry |
| [Metrics](ADMIN-RESPONSIVE-METRICS.md) | Dashboard/Page/Journals/Storage/Analytics metric state matrices |
| [Toolbars](ADMIN-RESPONSIVE-TOOLBARS.md) | Search/filter/action/Selection one-row pressure and representative pages |
| [Tables and Selection](ADMIN-RESPONSIVE-TABLES.md) | Ordinary table fit and each page's semantic column yielding |
| [Visual Stages](ADMIN-RESPONSIVE-STAGES.md) | Task-specific stage/pane arrangements and minimal compositions |

## Cross-surface responsive invariants

Responsive transitions are **monotone** for the actual content: when Sidebar changes to Burger, reclaimed space may **not** promote Dashboard, General, Pages, Gallery, Custom Page, Journal, Analytics, Storage or Activity to wider metric/toolbar/table/card/stage presentation after those components reached a narrower legitimate pressure state. Shell viewport and task container widths are distinct.

Use existing CSS/Alpine state for resize and presentation; do not send browser-width/resize changes to Livewire/Laravel, invent timers or polling synchronization. Preserve accepted wide/desktop composition until reviewed fitting evidence warrants change. Historical legacy-public breakpoint values are migration evidence only. A source review does not establish browser acceptance.

## Shared implementation ownership versus accepted task-specific behavior

These split documents preserve page-specific responsive details, but **do not create a second CSS/control implementation authority**. Shared controls, filter panels and toolbar geometry belong to [ADMIN-CONTROL-CONTRACT.md](ADMIN-CONTROL-CONTRACT.md); shared table role order, split/fold classes, action/checkbox alignment and pagination belong to [ADMIN-TABLE-CONTRACT.md](ADMIN-TABLE-CONTRACT.md); modal sizing and scroll lock belong to [ADMIN-DIALOG-CONTRACT.md](ADMIN-DIALOG-CONTRACT.md). The responsive documents record **which semantic content stays/merges/yields on each page**, not license to duplicate the corresponding primitive in feature CSS.

There are no new widths or responsive states in this initial split. Follow-up consolidation must compare each individual rule against its canonical shared owner and current code **before** deleting it; source review and browser evidence are separate checks. [Admin UI](ADMIN-UI.md) remains the stable entrypoint for the rest of the admin grammar.
