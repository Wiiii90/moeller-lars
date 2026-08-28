# Admin UI skills and consistency contract

This file is the reusable UI reference for the authenticated artist administration in `Wiiii90/moeller-lars`.

It deliberately covers **admin UI only**. It does not define the public artist-site frontend.

The goal is not to make every page identical. The goal is to make shared geometry, controls and interaction rules consistent while preserving task-specific surfaces.

## 1. Product character

The admin is an editorial tool for the artist, not a generic SaaS dashboard.

Prefer:

- clear task surfaces;
- factual information;
- restrained typography;
- stable alignment;
- shared controls;
- task-specific tables/grids/contact sheets where they make sense.

Avoid:

- decorative card walls;
- kicker/eyebrow spam;
- explanatory prose where a label or state is enough;
- one-off toolbar systems;
- workspace-specific copies of a shared primitive;
- moving controls/actions between rows depending on state.

## 2. Normal workspace stack

For a normal primary admin workspace, use this vertical sequence when applicable:

1. one heading row containing the page title and, only when meaningful, a right-aligned status;
2. shared `x-admin.metrics` immediately after the heading when factual metrics are meaningful;
3. shared `x-admin.controls` when search, filters, context actions or selection are needed;
4. the actual task surface;
5. pager/add-row/footer controls where the task requires them.

The workspace heading owns **no separator**. Do not add a decorative kicker above it.

The metric strip owns its complete visual box: top border, bottom border and vertical separators between metrics. Do not move those borders onto the heading, controls or table.

The controls row owns **no separator**. It groups controls; it is not a visual box around the task surface.

Do not interpret the stack as a requirement to add fake metrics, fake status or fake controls to a page that does not need them.

## 3. Page/action control group

The canonical action-group geometry used by existing admin workspaces should be reused when a contextual page action group is meaningful.

Structure:

```text
LABEL
Action  Action  Action
```

The small uppercase label sits **above** the buttons, not beside them on the same horizontal line.

Examples:

```text
GALLERY
Settings  Add artwork  Materials  Preview
```

```text
PAGE
Settings  Add component  Preview
```

For Home, the label is the active template name rather than the generic word Home when that is the meaningful control context.

Rules:

- use the shared admin control height;
- use the same label typography as filter/control labels;
- keep button gaps compact and stable;
- preserve action ordering between rows/states;
- if a control cannot legitimately operate in one state, prefer a disabled stable slot when removing it would make the table/action geometry jump.

## 4. Metric strip

Use the shared `x-admin.metrics` / `x-admin.metric` system.

Metrics follow the heading directly when present and own their complete visual box:

- top border;
- bottom border;
- vertical separators between metric cells.

Do not build page-specific metric card CSS unless the metric is genuinely a different visualization.

Rules:

- metrics must be factual and useful for the current workspace;
- six columns are appropriate when six meaningful metrics exist;
- fewer metrics are better than invented filler;
- labels and descriptions must remain compact;
- metric descriptions should be single-line-safe; do not let one tile become taller because its helper text wraps to two lines;
- if overflow is unavoidable, use the shared metric behavior rather than a page-local height patch;
- do not use prose such as “Public behavior” or “Template status” as fake statistics.

The current Analytics, Activity and Storage specialist workspaces each use six meaningful metrics. That is a composition decision for those workspaces, **not** a rule that every admin page must have six metrics.

Examples of useful facts:

- counts by state/type;
- storage/library size;
- public/eligible source counts;
- newest year/candidate count;
- actual referenced-media counts.

## 5. Search/filter/control row

Use shared `x-admin.controls` for the normal workspace control row.

The semantic order is:

```text
Search | filters | Reset/Filter | context actions | Selection
```

Not every workspace needs every group, but present groups keep that order. For example:

```text
Search | Use | Filter
```

or:

```text
Search | Type | Filter | Preview | Selection
```

Rules:

- search is usually live with a bounded debounce such as `wire:model.live.debounce.300ms`;
- use one control height across inputs/selects/buttons;
- filter selects follow Search;
- Reset/Filter follows the filters it controls;
- workspace/context actions follow filter controls and precede Selection;
- one visible Selection group comes last when the task surface supports selection;
- avoid chips or secondary mini-toolbars floating inside the search row;
- reset filters explicitly to the neutral state;
- search/filter state should not silently change persisted order;
- the controls row owns no top/bottom separator.

## 6. Selection and multi-actions

Use **one visible Selection control per task surface** even when parent and child selections are stored separately internally.

A single selected-items button may expose a capability matrix.

Rules:

- selected count is visible;
- invalid actions remain visible but disabled when that makes capability/state clearer;
- do not duplicate separate “selected parents” and “selected children” menus in the same toolbar;
- mixed selections must not cause ambiguous mutations;
- destructive bulk actions require the same domain safeguards as row actions;
- selection should be cleared/reprojected after mutations that invalidate positional targets.

## 7. Flat table grammar

Prefer flat tables for list-oriented editorial work instead of cards pretending to be rows.

**Media Files is the strongest current visual reference for the normal flat-table presentation.** It is not conceptually an exception; its table geometry demonstrates the shared table grammar where that grammar applies.

Shared principles:

- use shared `x-admin.table` for ordinary tables;
- one canonical header row;
- stable columns from header through every row;
- ordinary tables have no outer top or bottom border;
- table-header and row separators use normal `var(--admin-line)`;
- action cells share stable geometry;
- row identity/content should ellipsize rather than push action columns around;
- normal desktop action bars run left-to-right and are `nowrap`; responsive wrapping belongs at an intentional breakpoint;
- state indicators occupy a stable column;
- do not conditionally remove a leading action if that makes every following action shift.

Do not add wrapper borders merely because a table primitive could support them. The shared table's internal header/row lines provide the normal separation.

### Stable action order

Where a table has View and Edit, prefer a stable leading order such as:

```text
View | Edit | state action(s) | ↑ | ↓ | Delete
```

If View is unavailable for a draft/archived state and no protected preview exists, a disabled View slot is preferable to shifting every other action.

## 8. Ranked tables and Position

Ranked/ordered admin tables should expose a human-readable **Position** where that helps the editor understand order.

Use a 1-based display rank. Do not expose sparse/zero-based internal persistence values directly.

Compact forms such as `01`, `02`, `03` are acceptable.

This convention should be propagated deliberately as pages are reviewed; do not create a broad site-wide rewrite just to add Position everywhere in one worker.

Journal already uses visible Position and is a current reference for the convention.

## 9. Drag and ordering

Use native Livewire sorting only:

```text
wire:sort
wire:sort:item
wire:sort:handle
```

Do not build custom HTML5 `draggable`/dragstart/drop state machines.

Rules:

- drag handles share one visual geometry on a given table hierarchy;
- ordering is persisted by the canonical domain ordering service;
- drag is disabled when Search/filters/pagination make canonical order ambiguous;
- ↑/↓ actions remain as a keyboard/explicit fallback where the workspace already uses them;
- filtered reorder must not pretend that a filtered projection is the complete canonical sequence.

## 10. Hierarchy table grammar

Pages and Custom Page are hierarchy extensions of the shared flat-table grammar. They use the existing `admin-hierarchy` primitive rather than creating separate page-local table systems.

When child rows are part of the same editorial table, they align to the same global column system.

Rules:

- no nested child table with duplicate headers;
- do not indent an entire child table and thereby destroy global alignment;
- do **not** add a decorative child connector, vertical branch or L-line;
- normal hierarchy header/row separators use `var(--admin-line)`;
- `Status` and `Actions` headings align with their row content;
- row actions run left-to-right and `nowrap` on normal desktop geometry;
- state-action slots keep reserved width where required, while the state-action text itself is left-aligned;
- the hierarchy Actions column remains stable across parent/child/state variants;
- bottom Add rows use normal thin top and bottom separators;
- hierarchy is expressed by row structure/content, not by decorative connector graphics.

A hierarchy extension may add selection/drag/position/identity columns appropriate to its domain, but it does not replace the shared table grammar.

## 11. Home component table

Home component templates currently use a shared ordered component-table grammar without inventing an independent publish Status column for components that do not have an independent publish lifecycle.

Use the same shared table/control geometry as other applicable workspaces without forcing Custom Page-specific status semantics onto Home.

Home tools keep the normal semantic control order:

```text
Search | Type | Filter | Selection
```

Types:

- Image;
- Heading;
- Rich Text;
- Divider.

DnD is enabled only in neutral filter state. Bottom full-width `+ Add component` remains a valid add affordance even when a top context action also has Add component.

## 12. Journal table references

Current Blog table:

```text
[Selection] [Drag] [Position] [Image] [Post] [Status] [Publication] [Actions]
```

Current Exhibitions table:

```text
[Selection] [Drag] [Position] [Exhibition] [Status] [Timing] [Schedule] [Actions]
```

For Exhibition identity, keep the secondary line concise, e.g. `Venue · City`; do not dump full street/country metadata into the collection row.

Blog/Exhibitions remain ordinary shared tables where tabular. Do not add top/bottom wrapper separator lines merely because a generic table primitive could support them.

## 13. Specialist insight workspace composition

Analytics, Activity and Storage are specialist insight/operations workspaces. They reuse the shared heading, metrics, controls and table grammar around one large task-specific visualization.

### Analytics

Use this composition:

1. heading row with `Analytics` and right-aligned Reporting status;
2. six shared traffic metrics;
3. large Geography/world-map visualization;
4. shared controls in the semantic order `Search | Filter | Analytics range`;
5. remaining report sections, using shared tables wherever the result is tabular.

The world-map surface is task-specific visualization; the heading, metrics, controls and tables are shared grammar.

### Activity

Use this composition:

1. heading row with `Activity`;
2. six shared activity metrics;
3. large 24-hour clock + calendar visualization;
4. shared controls in the semantic order `Search | Editorial area | Change type | Filter | Activity`;
5. shared Activity table.

The clock/calendar is task-specific visualization; the surrounding workspace grammar is shared.

### Storage

Use this composition:

1. heading row with `Storage` and right-aligned capacity status;
2. six shared storage metrics;
3. large storage ring/composition with **no heading above it**;
4. shared controls in the semantic order `Search | Use | Filter`;
5. `Breakdown` shared table;
6. `Details` / `Largest originals` shared table.

The storage ring/composition is task-specific visualization. Do not insert an extra section heading above it merely to match another page.

These exact six-metric compositions are specialist workspace decisions. They do not establish a universal six-metric rule for the admin.

## 14. Accepted/current visual references

Media Files / Files is the strongest current visual reference for flat table geometry. Gallery remains an accepted/current reference for the shared workspace shell around its distinct visual Artwork/contact-sheet task.

A worker changing another admin page must inspect the actual current Blade/CSS/shared primitives at the exact working base instead of approximating them from prose.

Use current references as authorities for applicable shared presentation dimensions such as:

- overall workspace/content width;
- heading placement and typography;
- metric-strip placement and geometry;
- control labels, heights, order and spacing;
- table/grid header and row treatment;
- action alignment;
- general density, borders and typography.

Their task surfaces remain distinct:

- Gallery is a visual Artwork/contact-sheet workflow;
- Media Files is a dense reusable media-library workflow.

Do not turn Custom Page, Journal, Home, Pages, General or specialist insight workspaces into the wrong task model merely for consistency. Reuse the **accepted/shared shell, controls and geometry**, then keep the task-specific surface appropriate to the page.

Do not introduce a competing page-local width, card family, toolbar grammar, table grammar, metric implementation or typography system when the shared primitives/current references already solve that dimension.

## 15. Theme and shared-primitive enforcement

The admin theme is an implementation authority, not optional inspiration.

Using `x-admin.workspace` around a page does **not** count as theme compliance if the page then recreates controls, metrics, table geometry, actions, spacing, typography or width with page-local classes.

For shared presentation concerns, reuse the existing theme tokens and Blade primitives first. The default authorities include:

- `resources/css/admin.css` and shared `resources/css/admin/*` modules;
- `--admin-*` tokens;
- `x-admin.workspace`;
- `x-admin.metrics` / `x-admin.metric`;
- `x-admin.controls`;
- `x-admin.table`;
- existing `admin-hierarchy` hierarchy primitive;
- `x-admin.section`, `x-admin.add-row`, `x-admin.empty-state` and other existing shared composition primitives where applicable;
- `admin-action` and other existing shared control classes;
- current accepted/reference implementations for concrete composition examples.

Without an explicit, task-specific reason, the following are source-review failures:

- inline `<style>` blocks inside admin Blade views;
- new page-local CSS variables that duplicate existing `--admin-*` tokens for color, border, spacing, width, control height or typography;
- new page-local control/button/action families that duplicate shared controls;
- new page-local metric card systems;
- new page-local table/header/row systems for ordinary editorial tables when `x-admin.table` and accepted table geometry can be reused;
- page-specific workspace/content widths that diverge from the accepted shell;
- copying shared geometry into `.pages-*`, `.general-*`, `.home-*`, `.journal-*` or similar selectors merely to make one page self-contained;
- large pixel-tuning patches whose only purpose is to imitate geometry that already exists in the theme/reference pages.

Feature-local CSS is allowed only for genuinely task-specific visualization/layout, for example an artwork contact sheet, media preview, Analytics world map, Activity clock/calendar or Storage ring/composition. It must not redefine the shared shell, metrics, controls or ordinary table geometry around that surface.

If the theme or shared primitive cannot express a needed shared pattern, fix or extend the shared authority deliberately. Do not fork it locally first and promise to centralize later.

**Shared ownership does not outrank browser-accepted presentation.** Do not broaden a shared selector so that already accepted consumers gain new borders, separators, connector lines, spacing, widths, offsets or action geometry. If centralization cannot preserve the accepted computed result, defer the centralization or scope the primitive more precisely.

### Required implementation order

For visual/admin work:

1. read `ui-skills.md`;
2. inspect the exact current accepted/reference code relevant to the requested geometry;
3. inspect existing shared Blade primitives and theme tokens;
4. compose the target page from those authorities;
5. add feature-local CSS only for task-specific visualization/layout that remains;
6. explain every new shared-looking selector or token in the worker handoff.

### Source-review gate

A visual worker result is not source-coherent until the reviewer checks the changed Blade/CSS for theme bypasses **and for blast radius on already accepted consumers**.

Reject the change before reconciliation when a page recreates an existing shared primitive locally, even if the markup is functional and the worker claims visual consistency.

Also reject a shared-theme change when it changes an already accepted consumer without an explicit current browser requirement.

The handoff for visual work must state:

- which current reference files were inspected;
- which shared primitives/tokens were reused;
- which new CSS/classes were added;
- why each new class is genuinely task-specific rather than a duplicate of the theme;
- whether any already accepted surface is expected to change visually.

## 16. Sections, kickers and information hierarchy

Use explicit section titles where a workspace has multiple real task surfaces.

Avoid repeated decorative layers such as:

```text
SELECTION
Current Home artwork
```

when `Current Home artwork` already communicates the section.

Likewise avoid eyebrow/kicker labels on every row/card. Information hierarchy should come from page heading, metric strip, control labels, table headers and real section titles.

A specialist visualization may intentionally have no heading when its accepted composition already makes its role clear, as with the Storage ring/composition.

## 17. Dialogs and overlays

Dialogs are a shared primitive.

Required behavior:

- viewport-level backdrop;
- centered/bounded modal;
- internal scrolling for long content;
- reachable header/footer/actions;
- Escape/close;
- focus trap and restoration;
- nested popovers/selects above the modal;
- responsive sizing;
- originating workspace state retained after close/save.

Do not fix one broken dialog by creating a page-local fake modal.

Large editorial dialogs should order content according to the actual editorial task, not persistence schema order.

## 18. Rich Text editor UI

There is one central Rich Text technology: `AdminRichText` backed by Filament `MarkdownEditor`.

Canonical embedded image reference:

```markdown
![](media:123)
```

Media insertion belongs with the editor controls/action area and uses the lazy Media Files picker. Do not add a second free-standing media-upload subsystem, arbitrary external image URLs, TipTap/RichEditor or a parallel parser.

Canonical asset ALT should be reused unless a product surface explicitly supports a true occurrence-level override. Journal structured Cover/Gallery currently use Media Files ALT exclusively at runtime.

## 19. Media picker behavior

Use the central lazy `MediaAssetSelect` pattern.

Do not eagerly `pluck()` hundreds of MediaAsset options merely because a normal Select supports preload.

The picker should narrow by allowed media kind and query lazily. This is both a UI consistency and performance rule.

Opening a Settings/dialog action must not eagerly materialize the entire Media library when the initial dialog state does not require it. Treat a cold opener dominated by full-library hydration/preload as a regression.

## 20. Performance as UI quality

A slow first click is a product bug even when local Docker amplifies it.

When browser review reports latency:

1. inspect the actual action path;
2. find repeated queries/preloads/filesystem walks/external calls;
3. separate source cause from local-runtime amplification;
4. make a source-justified fix;
5. do not dismiss the problem as “just Docker”.

Avoid adding caches blindly before identifying what is repeated or unnecessarily eager.

## 21. Empty states

Empty states should be concise and task-oriented.

Good:

```text
No matching exhibitions
Clear filters
```

Avoid paragraphs explaining obvious states.

If the empty state is caused by active filters, distinguish it from a genuinely empty dataset.

## 22. Responsive behavior

Desktop alignment is primary for these editorial tables, but responsive behavior must be intentional.

- use horizontal overflow for wide data tables when preserving column meaning is better than arbitrary wrapping;
- only collapse to stacked/mobile composition at explicit breakpoints;
- do not let one child/action toolbar wrap independently and create a pseudo-card inside a table;
- maintain control labels/action grouping when control rows wrap;
- preserve stable hierarchy action geometry until an intentional responsive breakpoint changes the presentation.

## 23. CSS ownership

The canonical theme entrypoint is `resources/css/admin.css`; feature modules live under `resources/css/admin/`.

Before adding a selector, determine whether the rule belongs to:

1. a shared token/component;
2. a shared workspace/metrics/controls/table/hierarchy family;
3. a genuinely feature-specific visualization/layout.

Do not fix a central geometry problem with multiple page-local pixel patches.

Important current shared/admin modules include:

- `base.css`;
- `layouts.css`;
- `forms.css`;
- `dialogs.css`;
- `data-workspace.css` / shared task-surface rules where present;
- feature modules such as `gallery.css`, `media.css`, `home.css`, `custom-page.css`, `journal.css`, `analytics.css` and `activity.css` only for their genuine feature-specific needs.

Important shared Blade primitives include:

- `components/admin/workspace.blade.php`;
- `metrics.blade.php`;
- `metric.blade.php`;
- `controls.blade.php`;
- `table.blade.php`;
- `add-row.blade.php`;
- `section.blade.php`;
- `empty-state.blade.php`.

The existing `admin-hierarchy` primitive owns hierarchy-specific shared presentation for Pages and Custom Page.

## 24. Browser acceptance and presentation reset

Static source review, passing focused tests and a running container do not establish visual/product acceptance.

The user's review of the current built candidate is authoritative for presentation. If the user rejects a layout, width, cards/panels, metrics treatment, control row, table geometry, typography or wording, that rejected presentation is not a preservation requirement merely because it already exists, passed a source review or is asserted by a temporary test.

When the user names Media Files, Gallery or another current accepted page as a visual reference, inspect the exact reference implementation and reuse its primitives/tokens for the dimensions named. “Keep it consistent” without reading the reference code is not sufficient.

Once a surface has been browser/product accepted for the current cycle, reconciliation/centralization must not change its computed presentation unless the user explicitly reopens that question.

If a page has survived repeated visual repair passes while retaining the same rejected structure, stop layering patches onto it. Preserve valid domain behavior, persistence, safety guards and central technologies, but rebuild the presentation layer from the accepted reference/shared grammar when necessary.

Do not create durable UI tests whose purpose is to memorialize a repair round, branch name, candidate chronology or unaccepted markup. Tests should protect stable functional/domain behavior; browser presentation becomes a durable reference after browser/product acceptance.

## 25. Browser-review checklist

For every admin slice, inspect at least:

- heading title/status geometry and absence of a heading separator;
- metric-strip alignment/height and complete metric-owned border box;
- shared control order `Search -> filters -> Reset/Filter -> context actions -> Selection` and absence of a controls separator;
- ordinary table outer-border behavior and `var(--admin-line)` header/row separators;
- selection + drag geometry;
- Position where applicable;
- action order, `nowrap` behavior and stable slots;
- hierarchy alignment, stable Actions column and absence of decorative child connector/L-line;
- bottom Add-row thin top/bottom separators where applicable;
- specialist visualization composition for Analytics, Activity and Storage;
- empty states;
- dialog size/scroll/focus/popovers;
- filtered reorder behavior;
- obvious first-click/navigation latency;
- whether an existing shared component was bypassed by a new local structure;
- whether page-local CSS duplicates an existing theme token or primitive.

Browser acceptance is allowed to reject a technically correct implementation for poor/inconsistent UI. That feedback becomes the next source requirement.
