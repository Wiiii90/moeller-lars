# Admin UI — accepted shared presentation and interaction contract

**Ownership:** This is the normative shared Admin presentation contract. [Controls](controls.md), [Dialogs](dialogs.md), [Tables](tables.md) and [Responsive](responsive.md) own their detailed requirements. Keep accepted design here, source-specific evidence in its evidence owner, and avoid worker/task state in permanent product documents.
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

**Admin and public navigation are separate projections of the same site tree.** The Filament Sidebar is the editorial navigation: it includes existing draft and published pages in their persisted order. The public menu includes only published content; a published child of an unavailable editorial parent becomes a public root entry. Preview has its separate authenticated draft exception. Skip Home affects both projections, but does not remove other draft pages from the Admin Sidebar. Historical navigation-visibility columns retained for Publication restores do not define current visibility; the canonical domain services do.

After a **successful persisted navigation change** (page creation/editing, publication, deletion, hierarchy/order or applicable Home/Pages settings), `SitePages::loadSections(navigationChanged: true)` emits Filament's existing `refresh-sidebar` event once. The native Sidebar re-queries `SiteNavigation`; filtering, paging, opening a modal, validation errors and no-ops must not trigger a refresh. This does not publish drafts and does not introduce a second JavaScript tree, polling, forced reload or duplicate state. The public counterpart is documented in the [Public implementation contract](../public/site.md).

## 3. Page/action control group

The canonical action-group geometry is the pattern used by Gallery/Storage and should be reused by Home/Custom/Journal when applicable.

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

### Metric-unit alignment grid

When a workspace has a six-column metric strip, those six columns may also act as the **reference alignment grid** for the controls and ordinary data table below it.

This is an alignment rule, not a demand for six semantic columns everywhere:

- one control/table region may span multiple metric units, for example Search may span two units;
- one metric unit may be subdivided between compact semantic columns, for example `Area | Type` or `Who | When` may each occupy half of one unit;
- several semantic columns may therefore occupy the same number of metric units as a smaller set of wider regions;
- Actions commonly occupy the final one or two metric units according to actual action density; do not reserve two units when a task only needs one;
- starts/ends should align to the metric-unit geometry where practical, but **do not draw vertical borders merely to expose that hidden grid**;
- responsive composition may leave the reference grid at an intentional breakpoint rather than squeezing labels or actions beyond usability.

The point is stable shared geometry while allowing the table schema to follow the task.

### Geometry gate: ruler + real content

For standard toolbars, tables and shared Visual Stages, use the existing shared geometry owner before feature-local CSS.

For toolbars, geometry is **real content minimum + ruler subdivision**: derive the longest legitimate control content including chrome, round that need up to the smallest shared six-unit subdivision that fits, keep Search elastic on the left, keep Actions compact, and keep Selection terminal on the shared checkbox axis. The ruler remains refinable when visible metrics collapse from six to three or two.

For tables, preserve canonical role order and the terminal Selection rail; supportive columns and action labels yield before Selection drifts or horizontal scrolling appears.

For Visual Stages, reuse the shared stage height, pane/divider geometry and semantic Wide/Narrow/Minimal composition before adding feature-local dimensions. A page must not recreate an already-owned stage axis or breakpoint. General's two-pane desktop/mobile split uses the shared `admin-visual-stage--split` divider with a metric-aligned `--admin-stage-divider-axis` and the shared `admin-visual-stage__heading` vertical rail, rather than its own pseudo-divider or top-offset. Its live preview scales the complete device inside the stage viewport, so a small bottom inset is intentional.

A screenshot-specific span, page-local Selection offset, page-local standard-toolbar grid or duplicate shared-stage geometry is a contract violation unless the shared primitive demonstrably cannot represent the task.

### General Appearance: compact controls, dialogs for additions

The global Background/Menu/Page and Title controls retain a concise two-column
line-based grammar with the shared ColorPicker and numeric field. Do not
surface a Filament Repeater's card chrome, default rounded Add button,
red per-row trash icons or explanatory helper paragraphs inside the Stage.
Solid paints use one inline shared ColorPicker beside their mode Select.
Gradients use the **actual shared** x-admin.table, never Filament's separately
styled table repeater. The shared AdminTableRepeater adapts Filament's
field/action lifecycle to that canonical table, with 2–12 Color/Position rows.
Use the existing AdminColorControl in the cells and x-admin.add-row below.
Never recreate the table with local radius/border/row chrome, native browser
color inputs, card repeaters, edit dialogs, helper prose or red trash actions.
The global angle control has an inline editable degree value and opens
the shared rotary picker on interaction; it does not show a persistent
dial beside the number. Do not duplicate this component within General.



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

- search is live through the shared `x-admin.search-input` primitive with the canonical 300 ms read-only debounce; do not create page-local debounce values or submit/navigate a form from search;
- use one control height across inputs/selects/buttons;
- keep control headings on one line; they must never increase the toolbar height by wrapping, and should ellipsize when a width mistake would otherwise force a second line;
- prefer one-word headings whenever the meaning stays clear: use `Search`, `Area`, `Type`, `Date`, `Time`; do not repeat the current object or row noun in headings such as `Search exhibitions`, `Editorial area` or `Change type` when the surrounding workspace already supplies that context;
- keep object-specific search context in the placeholder/accessible description rather than the visible `Search` heading;
- `Filter`/Reset occupies a stable control group;
- avoid chips or secondary mini-toolbars floating inside the search row;
- avoid multiple visible selection groups for one table hierarchy;
- reset filters explicitly to the neutral state;
- search/filter state should not silently change persisted order;
- where the metric-unit reference grid applies, a wide Search control may deliberately span two metric units while the remaining controls subdivide the remaining units; use semantic width rather than equal-width controls by habit;
- the metric ruler defines shared anchors, not an equal-width mandate for every middle control: when Search and trailing Selection are stable anchors, distribute the space between them by usability, let peer filters share the flexible room fairly, and keep compact utilities such as Reset/View only as wide as their content requires.

## 6. Selection and multi-actions

Use **one visible Selection control per task surface** even when parent and child selections are stored separately internally.

A single selected-items button may expose a capability matrix.

Rules:

- selected count is visible; Dashboard feed keeps it as a separate badge outside the bulk-action trigger so the trigger itself remains icon + label/icon-only while the badge stays on the table checkbox axis;
- when Selection controls an ordinary table or table-like hierarchy, it remains the terminal toolbar group and its circular selected-count badge is centered on the **same horizontal axis** as the trailing select-all and row checkboxes below it; Dashboard feed keeps that count visibly separate from the bulk-action trigger while preserving the same terminal checkbox axis;
- the shared table contract reserves the terminal `--admin-table-selection-width` rail inside the Selection trigger for that badge; do not add page-local margins, padding nudges or duplicate trigger grids that move the badge off the checkbox axis;
- the badge/checkbox axis is a desktop/table invariant until an intentional responsive breakpoint changes the composition; once controls or rows deliberately stack/reflow, exact cross-row pixel alignment may relax;
- visual card/contact-sheet surfaces without one trailing table Selection column, such as Gallery, do not invent a fake column or axis merely to satisfy the table rule;
- invalid actions remain visible but disabled when that makes capability/state clearer;
- do not duplicate separate “selected parents” and “selected children” menus in the same toolbar;
- mixed selections must not cause ambiguous mutations;
- destructive bulk actions require the same domain safeguards as row actions;
- selection should be cleared/reprojected after mutations that invalidate positional targets.

## 7. Table grammar

Prefer flat tables for list-oriented editorial work instead of cards pretending to be rows.

The canonical ordinary-table role order is defined together with `docs/admin/tables.md`:

```text
Position + Drag | primary identity | secondary data | Actions | Selection
```

A table renders only roles that actually exist. When Position is absent, Drag may be the leading role; when Selection is absent, Actions remain the final semantic work column. Selection, when present, is always the trailing utility column.

Shared principles:

- one canonical header row;
- stable columns from header through every row;
- action cells share one alignment and right edge;
- avoid nested child tables with their own duplicate headers when children belong to the parent task table;
- row identity/content should ellipsize rather than push action columns around;
- normal desktop action bars should be `nowrap`; responsive wrapping belongs at an intentional breakpoint;
- state indicators occupy a stable column;
- do not conditionally remove a leading action if that makes every following action shift;
- use the metric-unit reference grid where it improves cross-surface alignment; semantic columns may span or subdivide those units rather than being forced to equal widths;
- compact rows may use **one or two meaningful lines per cell** when the second line adds useful support information; do not force every field onto one line, but also do not stack four or five redundant fragments into a single cell;
- status dots/markers in the same state column must keep a stable marker axis across rows, even when supporting text such as a commit hash sits on a second line;
- do not add vertical cell borders solely to make column/metric alignment visible when accepted admin tables do not use them.

### Stable action order

For ordinary editorial tables, use the shared semantic action order when those actions exist:

```text
Move up | Move down | Edit / View | state / workflow | other task actions | Delete
```

Not every table has every action. Movement comes first because it belongs to ordering, Delete remains last, and state/workflow actions keep stable slots where row-state variation would otherwise make controls jump.

For tables whose canonical leading action is Details rather than Edit/View, keep **Details first on every row** and append contextual actions such as Undo/Restore/Revert after it. Do not right-pack a lone Details action differently from rows that happen to have more actions.

## 8. Ranked tables and Position

New convention: ranked/ordered admin tables should expose a human-readable **Position** where that helps the editor understand order.

Use a 1-based display rank. Do not expose sparse/zero-based internal persistence values directly.

Compact forms such as `01`, `02`, `03` are acceptable.

This convention should be propagated deliberately as pages are reviewed; do not create a broad site-wide rewrite just to add Position everywhere in one worker.

Journal already uses visible Position and is the current reference for the convention.

## 9. Drag and ordering


**Behavior is authoritative; sortable group names are an implementation
detail.** The claim that putting every root and child list into one
`wire:sort:group` restores previously accepted Pages behavior was not
established by browser acceptance and must not be treated as a contract.
In particular, nested empty child sortables can compete with root drop
placement. Do not preserve, reinstate, or remove an implementation solely
because it is described as more native or simpler.

**Browser-accepted Pages drag-and-drop (21 October 2026):** The user
confirmed that both root ordering and deliberate child placement work with
the correction introduced by `f7b79d49112b80ac2385377e934a9c1579785548`.
The user subsequently also accepted the simple root-insertion separator
presentation introduced by `5b53e9fe9aa0f71dc25806aaaea13141dfeb4e8d`
as **good enough in the real browser**. Preserve this combined behavior and
appearance as the accepted baseline; do not redesign it during cleanup.
The repair uses the existing Livewire/Alpine Sortable owner and the existing
shared `wire:sort:group="site-pages"` with parent-specific group IDs.
For **empty** children only, `site-pages.blade.php` sets
`wire:sort:config="{ emptyInsertThreshold: 0 }"` because SortableJS
otherwise gives invisible empty lists a five-pixel proximity capture zone
that can steal root-to-root drops. The corresponding
`.admin-pages__empty-child-rows` in `table-contract.css` is an actual
0.75rem-high, bounded child destination inside its root row. Its negative
block-start margin compensates for its own height, so root sibling geometry
does not jump; the leading Position/Drag region is outside the child target.
`body.sorting` enables pointer hits only during a native drag.
**Do not replace this with separate visible right-side drop surfaces,
global pointer handlers, a second drag controller, or unrestricted empty
sortable proximity detection.** Before changing this structure, reproduce
root-before/middle/last and nest-under-last behavior in the real browser.
The accepted drop preview uses **only the existing horizontal separator
style** to indicate a root-level insertion. When a page is nested under a
parent, no additional line separates that parent from its child. The visual
signal follows the native `.sortable-ghost` placeholder among direct root
siblings; it does not change hit geometry, sortable membership, placement
or row size. **No colored side rail, visible target surface, label, added
spacing, or custom pointer handler.** The user explicitly accepted this
minimal presentation on 21 October 2026; neither it nor the functioning
drag gestures should be replaced without a new explicit request.

Pages and Custom Pages both support hierarchy, but they have **different
domain owners**: Pages nests site sections under pages; Custom Page nests
eligible components under an Image. Reusing table/drag styling must not
silently change either editor's drop semantics.

Required Pages behavior when ordering is enabled and unfiltered:

- Root pages can be moved before, between and after other root pages, **not
  merely after the final root**. Dropping for root ordering never silently
  reparents the page, even when the destination root has an empty child list.
- Eligible pages can be deliberately nested under the first, an intermediate
  **or the last** eligible root. The target for nesting must not compete
  ambiguously with a normal root-reorder drop.
- A child can be returned to root level; children can be reordered within
  their parent and moved between eligible parents as supported by the
  canonical two-level hierarchy.
- Home stays the first non-draggable root. Filtering and incomplete
  pagination still disable ambiguous ordering, not other page controls.
- Persistence, Sidebar refresh, Activity/audit and exactly-once success
  feedback remain owned by the existing editor/domain mutation path.

Custom Page's Image children are independent full component rows using
the same row grammar as root components: Position, Drag, editable type,
Content, Move Up/Down, Edit, publication, Delete and Selection. Eligible
Text, List (without media), Contact details and Legal Disclaimer children
can be placed under Image components and returned/moved according to the
existing domain rules. Image Edit does not subsume child editing.

Native `wire:sort`, `wire:sort:item`, and `wire:sort:handle` remain the
existing preferred sibling-order controls. No new custom HTML5 drag state
machine or second parallel sorting engine is authorized by this document.
If the native primitive cannot unambiguously express both reorder and
parent-change intent, **stop and establish the owner and real browser
behavior** before changing the architecture. In particular, do not alter
Pages sorting while simplifying Custom Page nesting, or vice versa, without
explicit cross-feature authorization and a complete gesture review.

Browser acceptance must exercise root-to-root before/middle/last,
root-to-child including the last parent, child-to-root, child-to-child,
and Custom Page Image-child placement when shared code is touched.
Testing only the backend sort action, or a single end-of-list drag, is not
evidence that the competing drop targets behave correctly.

Rules:

- when both exist, Position precedes Drag and both belong to the leading ordering region;
- drag handles share one visual geometry on a given table hierarchy;
- use `.admin-drag-handle` as the current shared drag-handle authority for the Custom/Home component-table family;
- ordering is persisted by the canonical domain ordering service;
- drag is disabled when Search/filters/pagination make canonical order ambiguous;
- Custom Page Image children are independent draggable/actionable rows; List entries and Contact options inside their parent forms are **not** independent rows; a single non-reorderable parent does not expose an inert drag handle;
- ↑/↓ actions remain as a keyboard/explicit fallback where the workspace already uses them;
- filtered reorder must not pretend that a filtered projection is the complete canonical sequence.

## 10. Custom Page component table and Home child hierarchy

Custom Page is a **flat sequence of components**:

```text
[Position + Drag] [Component dropdown] [Content] [Actions] [Selection]
```

The Component selector is essential and remains editable at all supported widths. Content is separate from Component, never a duplicate type label. Blog-style child rows are not part of Custom Page: List entries use the owning List `Apply changes` Repeater dialog; Contact is a single component with editable Heading, Public Email, Social Media Links and Contact Form switches, platform selection, form presentation, and status text. All three Contact switches default on when creating. No Add/Edit/Sort/Publish/Delete Contact child actions or child-selection axes are emitted by Custom Page.

Custom Page Contact Content shows only two lines: the authored heading (default `Contact`) and one ellipsized summary of enabled options. Legal Disclaimer Content shows `General legal disclaimer` followed by one ellipsized line of the actual General legal text, without redundant third-line metadata. The same semantic layout applies at every responsive width.

Public Image nesting belongs to a decorative Image's ordered `children`, displayed as ordinary independently editable child rows using the SAME full component row template as top-level components. Image Edit contains Image properties ONLY. Child Edit, type, order, publication and deletion stay on the child row. Only Text, List without media, Contact *details* (email/social, no form), and Legal Disclaimer may be nested. Public text follows a native float around its owning Image and then beneath it—no two-column grid or viewport breakpoint. A following top-level Contact Form is full width, with Name, Email and Message stacked at every size. All Custom Page component headings reuse one serif typographic grammar aligned to the public page heading's family; prose, list titles and contact/disclaimer body use one body scale.

Pages is the canonical page-level editor for Name, public route/slug, Parent, Position and optional page heading. Both Add and Edit expose the same heading vocabulary; the actual SiteSection owns the persisted heading. Home Edit shows its editable Name alongside the fixed root path, top-level parent and first position, plus the same Heading fields. These fixed root invariants must be shown rather than silently pretending that Home can have a custom slug or parent. Gallery/Journal/Custom Page may later surface the same settings in their own workspaces without duplicating data owners.

List entries have `Entry`, one `Context` field (date, year, organisation or other free context), `Published`, and `Details`. No separate Date, Organisation, Location, URL or Insert image controls in the List entry editor. Old Date/Organisation/Location values fold into Context and old links into Details through the model's normalizer without silent content loss. Existing Home List components use the same List editor. Parent selections and publication actions apply only to parent components.

Home's existing distinct Contact-child hierarchy is a separate workflow; keep its shared column geometry and selection semantics. Do not change Home when simplifying Custom Page.

## 11. Home component table

Home Custom and Under Construction share the accepted Custom Page hierarchy:

```text
[Position] [Drag] [Component] [Content] [Actions] [Selection]
```

The permanent Component selector exposes Image, Heading, Rich Text, Divider,
List, Contact and Legal Disclaimer. Divider subtype (Thin/Subtle/Strong/Dotted)
belongs to its Add/Edit schema. Content must not repeat the component type.
The five action roles are Move up, Move down, Edit,
Publish/Unpublish and Delete. Published state belongs to each component and
its List/Contact children, not an artificial Status column. All available
Actions in nested Edit dialogs mirror the corresponding row semantics.

List and Contact children share the canonical parent/child hierarchy,
stable selection targets, explicit ordering, and publication semantics.
Mixed parent/child selection must be applied through the transactional
Home editorial owner, not separate partial writes. Type selection remains
interactive at narrow widths even when drag/reorder is disabled.
Search/Type filters disable reordered projections, never the type selector.
The bottom shared Add component row remains available.

**Mutation feedback:** Home parent and child mutations use the canonical
domain change result and exactly one `AdminNotifier` success notification
per real change, visible in the header and durable mailbox. This includes
nested editing, sorting, publishing and deleting, plus selected-item actions.
No-op/invalid mutations create no success Notification. Review source,
Livewire tests and browser behavior rather than inferring delivery from an
audit event.

## 12. Journal table references

The Journal has separate data columns at full width, and folds them as the table becomes tighter:

```text
Blog · Wide:            [Position] [Drag] [Image] [Post] [Date] [Status] [Actions] [Selection]
Blog · Folded:          [Position] [Drag] [Image] [Post] [Date]          [Actions] [Selection]
Exhibitions · Wide:     [Position] [Drag] [Image] [Exhibition] [Date] [Status] [Actions] [Selection]
Exhibitions · Folded:   [Position] [Drag] [Image] [Exhibition] [Dates]       [Actions] [Selection]
```

Blog's seven-action rail, Date, and Status all remain expanded at maximum workspace width. At 72rem actual table width, the shared table-pressure rule simultaneously folds Status into Date and switches all seven action labels to icons. There is no earlier fold that hides Status at full width. Blog Date reserves 7rem, Status 6rem, and Post identity absorbs the remaining width. The Blog Actions rail uses the exact canonical seven-slot grid, as Pages and General do for their action sets: every icon axis is identical across all rows, including Publish/Unpublish and Archive/Restore. The publication-state slot reuses the central `--admin-row-action-slot-state-width`; the long Cancel schedule action displays the short label `Cancel` while its tooltip and accessible name remain `Cancel schedule`. This removes the obsolete 9rem publication track without sacrificing icon alignment. The shared 72rem boundary continues to switch all seven slots to the compact icon grid. Exhibition Date and Status remain separate down to 64rem, then merge into Dates. Date appears before Status. In Blog, Date shows the scheduled/published date (or a dash if unavailable) and Status shows the editorial state. In Exhibitions, Date and Status also merge into Dates at 64rem; their separate wide widths are 12.5rem and 8rem, with 12.5rem for folded Dates. Exhibition Date uses only structured Starts/Ends values, with a single unbroken range `dd.mm.yyyy – dd.mm.yyyy`. A single available date occupies the same one line; end-only legacy records retain their available date. The supporting Date track is wide enough to prevent clipping before it yields at Narrow. The public-facing free-text display override is not shown in this admin table. Optional Vernissage appears on one unbroken line under the date range as `Vernissage: dd.mm.yyyy, HH:mm`; legacy opening text is used only when structured Vernissage is absent. The line ellipsizes when it cannot fit rather than wrapping. Exhibition Status shows the derived state (Past/Current/Upcoming), not the publication state. The Date cell keeps the full range on one line; Vernissage follows underneath without truncating its content. These are width-based table transitions, not viewport thresholds.

Blog and Exhibitions use the same canonical Cover/feature-media preview when configured; an empty preview is the neutral fallback. Position and Drag share the leading ordering region; Selection remains trailing. At Narrow (54rem), the merged Date/Dates column yields before the primary identity can be squeezed. Post identity displays the editorial state and date; Exhibition identity displays the *structured* date range, derived status and optional vernissage. These metadata lines must remain hidden while a dedicated column is still visible: the shared `admin-responsive-meta` hiding selector must outrank the identity cell's `small { display: block; }` styling, and Journal only reveals the metadata under Narrow. Actions and Selection never yield. Split/fold visibility stays in the shared table-pressure contract; Journal-specific track sizing and responsive metadata live only in `journal.css`.

Both Journal identity cells present a one-line title and one-line secondary summary with overflow ellipsis, using the canonical `admin-table__identity--ellipsis-meta` modifier. Exhibition uses `Venue · City` (never full street/country metadata). Blog uses the authored excerpt or a plain-text body preview when no excerpt exists; it normalizes whitespace and limits that preview to 140 characters in addition to CSS ellipsis. At Narrow, separate responsive state/date metadata may appear as additional individually unwrapped lines. Blog has seven fixed semantic action slots: slot 4 Publish/Unpublish/Cancel schedule (disabled Publish for archived entries), slot 5 Schedule, slot 6 Archive/Restore, and slot 7 Delete. Restore never moves to slot 4.

**Editorial dialog reference:** Blog/Exhibitions Add and Edit share `JournalEntryEditorSchema`, with responsive Basics/Venue/Dates/Map groups. Their Edit headers use the same `AdminRowAction` roles and existing domain mutations as the table rows; Delete and Schedule remain canonical modal tasks. Gallery Add Artwork and Edit Artwork use one `ArtworkEditDialog` schema with a create-only uniqueness rule, and Gallery Edit shows Move, Publication, and Remove/Detach above the form. Materials is an atomic `Apply changes` Command because its Repeater does not have reliable per-item Edit autosave. All top-right actions use the central `AdminDialog` presentation, not page-local CSS.

## 13. Gallery and Storage are accepted style references

Gallery and Storage are currently browser/product accepted and are the primary style references for the admin where their geometry applies.

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
- Storage is a dense reusable media-library workflow.

Do not turn Custom, Journal, Home, Pages or General into the wrong task model merely for consistency. Reuse the **accepted shell, controls and geometry**, then keep the task-specific surface appropriate to the page.

Do not introduce a competing page-local width, card family, toolbar grammar, table grammar, metric implementation or typography system when the accepted references/shared primitives already solve that dimension.

### Reference conflicts

Two surfaces being current/accepted references does not mean conflicting values are automatically both authoritative for the same shared dimension.

If two relevant reference surfaces use different geometry, tokens or behavior for the **same** shared dimension:

- name the conflict explicitly;
- name the affected dimension, such as control gap, label size, breakpoint or action alignment;
- inspect the shared primitive/theme and current source that may already own that dimension;
- if no unambiguous authority exists, do not standardize that dimension by choosing one reference arbitrarily;
- return the authority decision to the orchestrator/user, then implement against the decided authority.

Do not turn transient pixel values from a current browser pass into a durable rule merely to resolve the conflict.

See [Admin browser workflow](browser-review.md#visual-change-review) for how to translate accepted reference geometry into task assignments and source review.

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
- accepted Gallery and Storage implementations for concrete composition examples.

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

Shared component/theme implementation must be reviewed against the [browser and source workflow](browser-review.md#visual-change-review). The central CSS and Blade ownership rules above remain binding.

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

**[Admin Dialogs](dialogs.md)** owns the shared Filament Action modal lifecycle: native backdrop, focus/Escape/X, confirmation semantics, header-action rail, Edit change/save path, widths and responsive content frame. The **framework alone** owns the document scroll lock; the accepted long-/short-page behavior is preserved through the existing version-guarded build patch, never a page-local JavaScript lock. Do not create second dialogs, page-specific transforms, native `wire:confirm` prompts or fixed-width compensations.

The UI-level invariant is **one coherent dialog language** across workspaces: same compact title/action row, measured frame, accessible controls and stable position while conditional fields change. Choose `Small`, `Default` or `Large` by actual task content, not by the owning page. The detailed layout, animation, lifecycle and verification gates belong exclusively to the [Dialog owner](dialogs.md), while fields keep the [Control contract](controls.md).

## 17. Rich Text editor UI

The shared `AdminRichText` uses one editor and the existing Edit mutation boundary across Journal, Home and Custom Pages. Image positioning, captions, inline links, color/spacing, safe output and legacy Markdown conversion are specified **once** in [Editorial Rich Text](../content/rich-text.md). Dialog placement belongs to [Dialogs](dialogs.md); canonical media identity and ALT rules belong to [Media](../media.md). No page-specific editor, nested Action popup or second save path.

## 18. Media picker behavior

Use the shared `MediaAssetSelect` for media selection across Admin consumers. The accepted Storage contact-sheet presentation, bounded default/search results and native Filament keyboard/selection lifecycle are owned by [Controls](controls.md#rich-text-and-reusable-media-selection). For an editor popup, [Rich Text](../content/rich-text.md#image-media-and-layout) specifies panel close/escape/click-away and the single underlying asset reference. Never preload an unbounded media catalog or introduce a second picker solely for one page.

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

The shared [Admin responsive composition](responsive.md) owns accepted Wide/Compact/Narrow/Minimal behavior for selects, the shell, metrics, toolbars, tables, Selection and visual stages. Component pressure is distinct from the viewport and shared implementations stay centralized.

## 22. CSS ownership

The canonical theme entrypoint is `resources/css/admin.css`; feature modules live under `resources/css/admin/`. The admin shell emits **only this one stylesheet entry**. Feature CSS must not also be linked as parallel Vite entries, and JavaScript runtimes must not import presentation CSS that changes initial layout after first paint.

Before adding a selector, determine whether the rule belongs to:

1. a shared token/component;
2. a shared table/control family;
3. a genuinely feature-specific layout.

Do not fix a central geometry problem with multiple page-local pixel patches.

Important current shared/admin modules include:

- `base.css`;
- `layouts.css`;
- `forms.css`;
- `data-workspace.css`;
- `task-surfaces.css`;
- `table-contract.css`;
- `stage.css`;
- `typography.css`;
- `dialogs.css`;
- `gallery.css`;
- `media.css`;
- `home.css`;
- `general.css`;
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

A source review, a running container or passing focused tests is not browser/product acceptance. The accepted Gallery/Storage visual grammar must be tested against the **current built candidate**. Rejected presentations are not durable requirements. The [browser review workflow](browser-review.md#visual-change-review) owns the review and source-cleanup process.

## 24. Browser-review checklist

Use the [cross-workspace inspection checklist](browser-review.md#visual-change-review), not a second page-local checklist.

## 25. Presentation cleanup gate

After user acceptance, remove only **reference-proven obsolete** UI source. Browser acceptance and source-cleanup qualification are distinct steps; follow the [browser workflow](browser-review.md#visual-change-review).

## 26. Shared Visual Stage and semantic icon contract

Large admin visualization/editorial surfaces use one shared outer geometry.

Authorities:

- `resources/css/admin.css` owns `--admin-visual-stage-height`;
- `resources/css/admin/stage.css` consumes that height and owns shared divider geometry;
- `resources/css/admin/data-workspace.css` owns the stage-to-follow-up rhythm;
- feature modules own only their internal composition.

Rules:

- there is one desktop stage-height authority; do not redeclare the token later in another module; Stage visuals may have a fixed target size but must shrink from their own pane/container bounds, never continuously from viewport-width units such as vw;
- do not make individual stages shorter or taller with page-local height overrides;
- if the accepted global stage should change height, change the shared token once;
- vertical divider top/bottom breathing uses the shared divider inset instead of page-local pixel tuning;
- a page may use a different internal column layout while keeping the same outer height and follow-up rhythm;
- variable Stage content that can exceed its pane must scroll or clip inside that pane; it must not increase the shared Stage height, append a pseudo-section below the Stage, or silently discard meaningful rows merely to fit;
- pane actions/help may use a reserved action rail while variable content remains bounded independently. Storage keeps Media Distribution scrollable inside its own pane and places Refresh / `Free storage` beneath the Capacity visual;
- schema-backed Filament pages use the shared `admin-visual-stage-block` / `admin-visual-stage-followup` mechanism so framework grid gaps do not shift their post-stage separator;
- General may own its internal desktop/mobile matrix divider, but it does not own a separate outer stage height or a compensating post-stage margin.

If a browser pass shows one stage or separator at a different vertical position, first determine whether the shared token/primitive is being bypassed. Do not immediately add a local correction.

Shared admin navigation/action semantics use `App\Filament\Support\AdminIcon`. Prefer semantic catalog entries over scattered Heroicon literals for meanings already represented by the catalog, and keep distinct meanings visually distinguishable rather than reusing one glyph for unrelated concepts.

See [Admin browser workflow](browser-review.md) for the direct/worker browser-reconciliation loop around these contracts.

### Gradient tables and dynamic Appearance Stage height

The [Admin control contract](controls.md#public-appearance-controls-source-to-dev2ad31502ea4e) owns the shared ColorPicker, gradient-stop validation and table adapter; the [table contract](tables.md#general-appearance-color-stop-table) owns Add/Delete row presentation. [General Appearance Stage and Live Preview](responsive.md#general-appearance-stage-and-live-preview) owns active-tab-only More Space height and Preview sizing/lifecycle. These rules apply equally to Background, Title, Menu, Page and Divider color; no local alternate table, state or measurement system is introduced.

## 27. Administration language and regional display

**Source basis:** selected implementation paths on `dev@74e629274ac28828d9ce9e9c881ab87e7490f2d3`; source/contract review, not newly executed tests or browser acceptance.

The authenticated Filament SPA supports per-user `admin_language` values:
`en` (English) and `de` (Deutsch). This is independent of the same user's
`admin_region` preference (de-DE/en-GB/en-US) for dates and numbers.
Both are managed in Account. The top-right user-badge dropdown retains
its existing Theme → Account → Logout order, with the new Language action
inserted directly after Account. Its label is simply "Language" (or "Sprache"
in German), without displaying the current value. Both entry points update
the same users.admin_language preference.
Changing Language requires a full SPA page reload, because a pre-existing
Livewire/Alpine tree contains already rendered server-side labels.

`SetAdminLocale` is a persistent authenticated admin middleware, including
Livewire updates. Public routes MUST NOT inherit a signed-in editor's language
or region. Public date and numeric formats are controlled in General via
`regional_format`; the public language is unchanged (English).

The default source language for artist-admin UI is English.
Laravel translations live in `lang/de.json` and German validation/auth
messages in `lang/de`. All own visible labels, headings, option labels,
errors, tooltips, navigation, dialogs, and Blade text should use Laravel
translation at **render time**, not be stored translated in canonical
publication/audit data. Where information originates from artist content,
never translate or mutate it.

Filament package translations are used for framework-owned controls.
New artist-facing strings need an English source and German translation
in the same change; do not add a fake placeholder language switch.
Do not rely on APP_LOCALE=de as a substitute for personal preferences,
and do not change public language when an admin changes theirs.

End-to-end review must include new sessions, navigation, action modals,
date/number controls, validation, storage, journal, gallery, custom pages,
general, dashboard and Livewire navigations. The code has not been
browser-accepted simply because translations and middleware exist.
