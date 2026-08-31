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

1. one visible page heading matching the navigation destination;
2. optional metric strip;
3. one page/action or search/filter/control row;
4. the actual task surface;
5. pager/add-row/footer controls where the task requires them.

Do not add a decorative kicker above the page heading.

Do not interpret this as a requirement to add fake metrics or a fake toolbar to a page that does not need them.

## 3. Page/action control group

The canonical action-group geometry is the pattern used by Gallery/Files and should be reused by Home/Custom/Journal when applicable.

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

```text
CUSTOM / UNDER CONSTRUCTION
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

Do not build page-specific metric card CSS unless the metric is genuinely a different visualization.

Rules:

- metrics must be factual and useful for the current workspace;
- six columns are appropriate when six meaningful metrics exist;
- fewer metrics are better than invented filler;
- labels and descriptions must remain compact;
- metric descriptions should be single-line-safe; do not let one tile become taller because its helper text wraps to two lines;
- if overflow is unavoidable, use the shared metric behavior rather than a page-local height patch;
- do not use prose such as “Public behavior” or “Template status” as fake statistics.

Examples of useful facts:

- counts by state/type;
- storage/library size;
- public/eligible source counts;
- newest year/candidate count;
- actual referenced-media counts.

## 5. Search/filter/control row

A normal searchable task surface uses labels above controls and a shared baseline.

Typical grammar:

```text
Search | Type/Status/etc. | Filter | Selection
```

Rules:

- search is usually live with a bounded debounce such as `wire:model.live.debounce.300ms`;
- use one control height across inputs/selects/buttons;
- `Filter`/Reset occupies a stable control group;
- avoid chips or secondary mini-toolbars floating inside the search row;
- avoid multiple visible selection groups for one table hierarchy;
- reset filters explicitly to the neutral state;
- search/filter state should not silently change persisted order.

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

## 7. Table grammar

Prefer flat tables for list-oriented editorial work instead of cards pretending to be rows.

Shared principles:

- one canonical header row;
- stable columns from header through every row;
- action cells share one alignment and right edge;
- avoid nested child tables with their own duplicate headers when children belong to the parent task table;
- row identity/content should ellipsize rather than push action columns around;
- normal desktop action bars should be `nowrap`; responsive wrapping belongs at an intentional breakpoint;
- state indicators occupy a stable column;
- do not conditionally remove a leading action if that makes every following action shift.

### Stable action order

Where a table has View and Edit, prefer a stable leading order such as:

```text
View | Edit | state action(s) | ↑ | ↓ | Delete
```

If View is unavailable for a draft/archived state and no protected preview exists, a disabled View slot is preferable to shifting every other action.

## 8. Ranked tables and Position

New convention: ranked/ordered admin tables should expose a human-readable **Position** where that helps the editor understand order.

Use a 1-based display rank. Do not expose sparse/zero-based internal persistence values directly.

Compact forms such as `01`, `02`, `03` are acceptable.

This convention should be propagated deliberately as pages are reviewed; do not create a broad site-wide rewrite just to add Position everywhere in one worker.

Journal already uses visible Position and is the current reference for the convention.

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
- use `.custom-page-row__drag` as the current shared drag-handle authority for the Custom/Home component-table family;
- ordering is persisted by the canonical domain ordering service;
- drag is disabled when Search/filters/pagination make canonical order ambiguous;
- ↑/↓ actions remain as a keyboard/explicit fallback where the workspace already uses them;
- filtered reorder must not pretend that a filtered projection is the complete canonical sequence.

## 10. Parent/child hierarchical tables

When child rows are part of the same editorial table, they align to the **same global columns**.

Current Custom Page reference:

Parent:

```text
[Selection] [Drag] [Component] [Content] [Status] [Actions]
```

Child:

```text
[Selection] [Drag] [Kind] [Content] [Status] [Actions]
```

Rules:

- no nested `Content | Status | Actions` child header;
- do not indent the entire child table and thereby destroy global alignment;
- hierarchy may be shown with a restrained connector line;
- connector axis aligns with the parent drag column/handle axis;
- parent and child action cells use the same sixth-column geometry;
- child rows may be more compact, but their column starts do not float.

## 11. Home component table

Home component templates currently use:

```text
[Selection] [Drag] [Component] [Content] [Actions]
```

There is no artificial Status column because Home components do not have an independent publish lifecycle.

Use the same component-table grammar as Custom where appropriate, without forcing Custom-specific status semantics onto Home.

Home tools:

```text
Search | Type | Filter | Selection
```

Types:

- Image;
- Heading;
- Rich Text;
- Divider.

DnD is enabled only in neutral filter state. Bottom full-width `+ Add component` remains a valid add affordance even when the top action group also has Add component.

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

## 13. Gallery and Media Files are accepted style references

Gallery and Media Files / Files are currently browser/product accepted and are the primary style references for the admin where their geometry applies.

A worker changing another admin page must inspect their actual current Blade/CSS/shared-primitives at the exact working base instead of approximating them from prose.

Use them as authorities for applicable shared presentation dimensions such as:

- overall workspace/content width;
- page heading placement and typography;
- metric-strip placement and geometry;
- control labels, heights and spacing;
- table/grid header and row treatment;
- action alignment;
- general density, borders and typography.

Their task surfaces remain distinct:

- Gallery is a visual Artwork/contact-sheet workflow;
- Media Files is a dense reusable media-library workflow.

Do not turn Custom, Journal, Home, Pages or General into the wrong task model merely for consistency. Reuse the **accepted shell, controls and geometry**, then keep the task-specific surface appropriate to the page.

Do not introduce a competing page-local width, card family, toolbar grammar, table grammar, metric implementation or typography system when the accepted references/shared primitives already solve that dimension.

## 14. Theme and shared-primitive enforcement

The admin theme is an implementation authority, not optional inspiration.

Using `x-admin.workspace` around a page does **not** count as theme compliance if the page then recreates controls, metrics, table geometry, actions, spacing, typography or width with page-local classes.

For shared presentation concerns, reuse the existing theme tokens and Blade primitives first. The default authorities include:

- `resources/css/admin.css` and shared `resources/css/admin/*` modules;
- `--admin-*` tokens;
- `x-admin.workspace`;
- `x-admin.metrics` / `x-admin.metric`;
- `x-admin.section`;
- `x-admin.table`;
- `x-admin.add-row` for persistent bottom-add controls directly below tables/task surfaces;
- `x-admin.toolbar`;
- `x-admin.empty-state`;
- `admin-action` and other existing shared control classes;
- accepted Gallery and Media Files implementations for concrete composition examples.

Persistent bottom-add actions directly below tables/task surfaces use `x-admin.add-row`. Do not recreate their plus mark, typography, dimensions, spacing, hover or focus behavior in page-local markup/CSS.

Without an explicit, task-specific reason, the following are source-review failures:

- inline `<style>` blocks inside admin Blade views;
- new page-local CSS variables that duplicate existing `--admin-*` tokens for color, border, spacing, width, control height or typography;
- new page-local control/button/action families that duplicate shared controls;
- new page-local metric card systems;
- new page-local table/header/row systems for ordinary editorial tables when `x-admin.table` and accepted table geometry can be reused;
- page-specific workspace/content widths that diverge from the accepted shell;
- copying shared geometry into `.pages-*`, `.general-*`, `.home-*`, `.journal-*` or similar selectors merely to make one page self-contained;
- large pixel-tuning patches whose only purpose is to imitate geometry that already exists in the theme/reference pages.

Feature-local CSS is allowed for genuinely feature-specific surfaces, for example an artwork contact sheet, media preview, drag affordance unique to a domain surface, or a task-specific visualization. It must not redefine the shared shell around that surface.

If the theme or shared primitive cannot express a needed shared pattern, fix or extend the shared authority deliberately. Do not fork it locally first and promise to centralize later.

### Required implementation order

For visual/admin work:

1. read `ui-skills.md`;
2. inspect the exact accepted Gallery/Media Files reference code relevant to the requested geometry;
3. inspect existing shared Blade primitives and theme tokens;
4. compose the target page from those authorities;
5. add feature-local CSS only for task-specific behavior that remains;
6. explain every new shared-looking selector or token in the worker handoff.

### Source-review gate

A visual worker result is not source-coherent until the reviewer checks the changed Blade/CSS for theme bypasses.

The reviewer should reject the change before reconciliation when a page recreates an existing shared primitive locally, even if the markup is functional and the worker claims visual consistency.

The handoff for visual work must state:

- which accepted reference files were inspected;
- which shared primitives/tokens were reused;
- which new CSS/classes were added;
- why each new class is genuinely task-specific rather than a duplicate of the theme.

## 15. Sections, kickers and information hierarchy

Use explicit section titles where a workspace has multiple real task surfaces.

Avoid repeated decorative layers such as:

```text
SELECTION
Current Home artwork
```

when `Current Home artwork` already communicates the section.

Likewise avoid eyebrow/kicker labels on every row/card. Information hierarchy should come from page heading, metric strip, control labels, table headers and section titles.

## 16. Dialogs and overlays

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

## 17. Rich Text editor UI

There is one central Rich Text technology: `AdminRichText` backed by Filament `MarkdownEditor`.

Canonical embedded image reference:

```markdown
![](media:123)
```

Media insertion belongs with the editor controls/action area and uses the lazy Media Files picker. Do not add a second free-standing media-upload subsystem, arbitrary external image URLs, TipTap/RichEditor or a parallel parser.

Canonical asset ALT should be reused unless a product surface explicitly supports a true occurrence-level override. Journal structured Cover/Gallery currently use Media Files ALT exclusively at runtime.

## 18. Media picker behavior

Use the central lazy `MediaAssetSelect` pattern.

Do not eagerly `pluck()` hundreds of MediaAsset options merely because a normal Select supports preload.

The picker should narrow by allowed media kind and query lazily. This is both a UI consistency and performance rule.

## 19. Performance as UI quality

A slow first click is a product bug even when local Docker amplifies it.

When browser review reports latency:

1. inspect the actual action path;
2. find repeated queries/preloads/filesystem walks/external calls;
3. separate source cause from local-runtime amplification;
4. make a source-justified fix;
5. do not dismiss the problem as “just Docker”.

Avoid adding caches blindly before identifying what is repeated or unnecessarily eager.

## 20. Empty states

Empty states should be concise and task-oriented.

Good:

```text
No matching exhibitions
Clear filters
```

Avoid paragraphs explaining obvious states.

If the empty state is caused by active filters, distinguish it from a genuinely empty dataset.

## 21. Responsive behavior

Desktop alignment is primary for these editorial tables, but responsive behavior must be intentional.

- use horizontal overflow for wide data tables when preserving column meaning is better than arbitrary wrapping;
- only collapse to stacked/mobile composition at explicit breakpoints;
- do not let one child/action toolbar wrap independently and create a pseudo-card inside a table;
- maintain control labels/action grouping when tool rows wrap.

## 22. CSS ownership

The canonical theme entrypoint is `resources/css/admin.css`; feature modules live under `resources/css/admin/`.

Before adding a selector, determine whether the rule belongs to:

1. a shared token/component;
2. a shared table/control family;
3. a genuinely feature-specific layout.

Do not fix a central geometry problem with multiple page-local pixel patches.

Important current shared/admin modules include:

- `base.css`;
- `layouts.css`;
- `forms.css`;
- `dialogs.css`;
- `gallery.css`;
- `media.css`;
- `home.css`;
- `custom-page.css`;
- `journal.css`.

Important shared Blade primitives include:

- `components/admin/workspace.blade.php`;
- `metrics.blade.php`;
- `metric.blade.php`;
- `section.blade.php`;
- `table.blade.php`;
- `add-row.blade.php`;
- `toolbar.blade.php`;
- `empty-state.blade.php`.

## 23. Browser acceptance and presentation reset

Static source review, passing focused tests and a running container do not establish visual/product acceptance.

The user's review of the current built candidate is authoritative for presentation. If the user rejects a layout, width, cards/panels, metrics treatment, toolbar, table geometry, typography or wording, that rejected presentation is not a preservation requirement merely because it already exists, passed a source review or is asserted by a temporary test.

When the user names Gallery, Media Files or another accepted current page as a visual reference, inspect the exact reference implementation and reuse its primitives/tokens for the dimensions named. “Keep it consistent” without reading the reference code is not sufficient.

If a page has survived repeated visual repair passes while retaining the same rejected structure, stop layering patches onto it. Preserve valid domain behavior, persistence, safety guards and central technologies, but rebuild the presentation layer from the accepted reference/shared grammar when necessary.

Do not create durable UI tests whose purpose is to memorialize a repair round, branch name, candidate chronology or unaccepted markup. Tests should protect stable functional/domain behavior; browser presentation becomes a durable reference after browser/product acceptance.

## 24. Browser-review checklist

For every admin slice, inspect at least:

- page/action label geometry;
- metric strip alignment/height;
- Search/filter/Selection baseline;
- table/grid header alignment;
- selection + drag geometry;
- Position where applicable;
- action order and stable slots;
- parent/child alignment;
- empty states;
- dialog size/scroll/focus/popovers;
- filtered reorder behavior;
- obvious first-click/navigation latency;
- whether an existing shared component was bypassed by a new local structure;
- whether the page matches the accepted Gallery/Media Files reference geometry where applicable;
- whether page-local CSS duplicates an existing theme token or primitive.

Browser acceptance is allowed to reject a technically correct implementation for poor/inconsistent UI. That feedback becomes the next source requirement.
