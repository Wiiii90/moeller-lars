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

For Visual Stages, reuse the shared stage height, pane/divider geometry and semantic Wide/Narrow/Minimal composition before adding feature-local dimensions. A page must not recreate an already-owned stage axis or breakpoint.

A screenshot-specific span, page-local Selection offset, page-local standard-toolbar grid or duplicate shared-stage geometry is a contract violation unless the shared primitive demonstrably cannot represent the task.


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

- selected count is visible, and the trailing count/select-all/row-checkbox alignment is governed **only** by the [canonical Selection contract](docs/ADMIN-TABLE-CONTRACT.md#selection). It also owns the accepted **Dashboard feed exception** (count as a separate sibling outside the action trigger) and intentional responsive relaxations. Do not duplicate these geometry rules or add page-local count offsets;
- visual card/contact-sheet surfaces without one trailing table Selection column, such as Gallery, do not invent a fake column or axis merely to satisfy a tabular rule;
- invalid actions remain visible but disabled when that makes capability/state clearer;
- do not duplicate separate “selected parents” and “selected children” menus in the same toolbar;
- mixed selections must not cause ambiguous mutations;
- destructive bulk actions require the same domain safeguards as row actions;
- selection should be cleared/reprojected after mutations that invalidate positional targets.

## 7. Table grammar

The canonical ordinary editorial-table geometry, semantics, action order, responsive content priorities, marker axes and fit rules live in [ADMIN-TABLE-CONTRACT.md](docs/ADMIN-TABLE-CONTRACT.md). Prefer actual tables for tabular editorial work; do not emulate rows with cards. Only render the task's real roles. Read the shared table contract and current implementation instead of duplicating its role/action specification here.

## 8. Ranked tables and Position

[ADMIN-TABLE-CONTRACT.md](docs/ADMIN-TABLE-CONTRACT.md#canonical-role-order) owns the human-readable 1-based Position convention and its alignment with Drag/Add Row. Apply Position deliberately when the task has meaningful canonical order; do not create a site-wide cosmetic rewrite merely to expose it. Journal is the current ranked-table implementation reference.

## 9. Drag and ordering

The [canonical Table contract](docs/ADMIN-TABLE-CONTRACT.md#component-sequences) owns **native cross-level Livewire sorting**: root and child destinations share one `wire:sort:group` per editor, with `group-id=root|parent` determining the target hierarchy. The previous `admin-hierarchy-drag.js` controller and its special drop-target overlays are deleted (`dev@a316e59b`); do not reintroduce a second drag owner. `HierarchyParentField` remains the alternative Edit action for Parent selection.

The [canonical Table contract](docs/ADMIN-TABLE-CONTRACT.md#position-and-add-row) owns Position's display semantics, Add Row alignment and shared ordering axes. Its [Component sequence section](docs/ADMIN-TABLE-CONTRACT.md#component-sequences) owns native `wire:sort`/`wire:sort:item`/`wire:sort:handle`, sibling-scope eligibility, the existing Move up/down fallback, filtering/pagination restrictions and the shared `admin-drag-handle` geometry. Use the existing domain ordering owner. Do **not** build a separate HTML5 drag/drop state machine or duplicate those task rules here.

## 10. Custom Page parent rows and Home child hierarchy

The [canonical Table contract](docs/ADMIN-TABLE-CONTRACT.md#component-sequences) owns the shared columns, interactive Component selector, action/selection axes and distinct child presentations.

Custom Page decorative Images may own ordered single-level prose children displayed and edited as **independent component rows** through the same shared component-row Blade and action grammar; Image Edit owns Image settings only. Only Text, List without media, Contact details (email/social, **not** form), and the single Legal Disclaimer can be nested. Public children wrap natively around the image, continuing below it; following top-level Contact including form fields remains full-width. The [Public contract](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md#custom-pages-cv-and-contact) owns the presentation rule, [Structured Components](docs/architecture/structured-components.md) describes persistence, and the [dialog contract](docs/ADMIN-DIALOG-CONTRACT.md) owns editor interactions. Pages Add/Edit uses the same page heading vocabulary for SiteSection, including Home identity with immutable root route/parent/first position; do not introduce parallel persistence. See [Public contract](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md) for the public content grammar. **Custom Page has only component parent rows**: List entries and Contact options are edited in their respective parent dialogs. **Home retains its separately owned child rows and child selection.** Inspect the actual [Custom Page](resources/views/filament/pages/partials/custom-page-workspace-sequence.blade.php) and [Home](resources/views/filament/pages/partials/home-component-sequence.blade.php) Blade before changing either surface. Do not introduce feature-local table/row primitives.

## 11. Home component table

Home Custom and Under Construction reuse the **shared parent columns and controls** from Custom Pages, but still expose their own Contact-child hierarchy and mixed Selection. The distinction is owned in [ADMIN-TABLE-CONTRACT.md](docs/ADMIN-TABLE-CONTRACT.md#component-sequences). See [Structured Components](docs/architecture/structured-components.md) for the persisted Heading/Rich Text `text` distinction and [ADMIN-DIALOG-CONTRACT.md](docs/ADMIN-DIALOG-CONTRACT.md) for Edit/conversion. Notification count, no-op and header/mailbox feedback are owned by [ADMIN-NOTIFICATION-CONTRACT.md](docs/ADMIN-NOTIFICATION-CONTRACT.md#admin-mutation-feedback-integration), not by a Home-specific per-action claim.

## 12. Journal table references

Journal uses the canonical split/fold behavior in [ADMIN-TABLE-CONTRACT.md](docs/ADMIN-TABLE-CONTRACT.md#journals-splitfold-composition). That contract is the **single owner** of the Blog Date/Status and Exhibition Date/Status column priority, action-rail pressure and responsive identity metadata. Check the actual Journal Blade, `journal.css` and shared `table-contract.css` before changing the behavior; do not redefine these breakpoints in page-local CSS.

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

### Visual worker prompt contract

For browser/visual repairs, when the desired structure is already known, the worker prompt must describe the composition concretely rather than invite a fresh design interpretation.

State, where applicable:

- the order and slots of controls/content/actions;
- which elements remain in the same row, stage, table or task surface;
- what must be removed rather than restyled;
- which shared primitives/tokens/reference structures must be used;
- the breakpoint or condition under which responsive restructuring may begin;
- which neighboring surfaces must remain unchanged.

Do not give a worker free visual discretion over browser feedback that is already unambiguous. Implementation discretion is for technical realization inside the confirmed composition, not for reinterpreting the accepted/rejected layout.

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

### Required implementation order

For visual/admin work:

1. read `ui-skills.md`;
2. inspect the exact accepted Gallery/Storage reference code relevant to the requested geometry;
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

All admin dialogs use the canonical [ADMIN-DIALOG-CONTRACT.md](docs/ADMIN-DIALOG-CONTRACT.md): native Filament Action lifecycle and scroll lock, shared measured content frame, dialog types/sizes, header action rail, semantic autosave, form controls and responsive scrolling. The physical modal geometry and the version-pinned Filament scroll-lock patch belong to that one owner, **not** to page-specific CSS or an independent JavaScript lock.

For current Journal Blog/Exhibition and Gallery Artwork Create/Edit layouts, header mutations and the atomically committed Gallery Materials Repeater, consult the [single Gallery/Journal dialog owner](docs/ADMIN-DIALOG-CONTRACT.md#journal-blogexhibitions-edit-aktionen) and its Gallery section; do not restate per-action rules here.

For a task involving a dialog, inspect the actual shared `AdminDialog` adapter, modal/geometry CSS and the relevant Livewire/Filament Action before implementation. The dialog contract takes priority over obsolete working-round descriptions; preserve current browser-accepted behavior and do not create second modal/confirmation systems.

## 17. Rich Text editor UI

The shared [ADMIN-CONTROL-CONTRACT.md](docs/ADMIN-CONTROL-CONTRACT.md#rich-text-and-reusable-media-selection) owns the `AdminRichText` / Filament `MarkdownEditor` integration, the canonical Storage `media:<id>` reference and the **explicit validated HTTP(S) external-image option**. Consult `SafeRichTextRenderer` / `SafeLinkPolicy` rather than forbidding a currently supported URL input or inventing a second parser.

## 18. Media picker behavior

See the same [canonical control owner](docs/ADMIN-CONTROL-CONTRACT.md#rich-text-and-reusable-media-selection) for `MediaAssetSelect`, allowed type/state filters, lazy search and the shared search debounce. Do not preload the Storage library or create feature-specific media pickers.

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

Responsive behavior uses task-specific Wide / Compact / Narrow / Minimal transitions and is documented by responsibility in the [Admin responsive contract index](docs/ADMIN-RESPONSIVE-CONTRACT.md). This index links shell, metrics, toolbars, table/selection and Visual Stage requirements, while the shared Table/Control/Dialog contracts remain implementation authorities. Do not recreate page-local responsiveness from copied thresholds; inspect the relevant contract and CSS owner.

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

Static source review, passing focused tests and a running container do not establish visual/product acceptance.

The user's review of the current built candidate is authoritative for presentation. If the user rejects a layout, width, cards/panels, metrics treatment, toolbar, table geometry, typography or wording, that rejected presentation is not a preservation requirement merely because it already exists, passed a source review or is asserted by a temporary test.

When the user names Gallery, Storage or another accepted current page as a visual reference, inspect the exact reference implementation and reuse its primitives/tokens for the dimensions named. “Keep it consistent” without reading the reference code is not sufficient.

If a page has survived repeated visual repair passes while retaining the same rejected structure, stop layering patches onto it. Preserve valid domain behavior, persistence, safety guards and central technologies, but rebuild the presentation layer from the accepted reference/shared grammar when necessary.

Do not create durable UI tests whose purpose is to memorialize a repair round, branch name, candidate chronology or unaccepted markup. Tests should protect stable functional/domain behavior; browser presentation becomes a durable reference after browser/product acceptance.

## 24. Browser-review checklist

For every admin slice, inspect at least:

- page/action label geometry;
- metric strip alignment/height;
- Search/filter/Selection baseline;
- selected-count badge aligned with the trailing table/hierarchy checkbox axis where Selection is tabular;
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
- whether the page matches the accepted Gallery/Storage reference geometry where applicable;
- whether page-local CSS duplicates an existing theme token or primitive.

Browser acceptance is allowed to reject a technically correct implementation for poor/inconsistent UI. That feedback becomes the next source requirement.

## 25. Presentation cleanup gate

Browser/product acceptance does not by itself complete UI work. After the accepted presentation is established and shared presentation dimensions are reconciled, audit source paths created or superseded by that change.

Remove only safely-proven obsolete UI source: superseded page/view paths, dead Blade views/partials, unused CSS/selectors, obsolete presentation aliases/compatibility paths, duplicate presentation paths, stale implementation-specific UI tests, and imports/classes made unused. Reference-search every candidate before deletion and preserve still-required compatibility/domain behavior.

Run final UI/source verification against the cleaned tree. Presentation acceptance and presentation-source cleanup are separate gates; passing one does not imply the other.

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

See `docs/ADMIN-BROWSER-WORKFLOW.md` for the direct/worker browser-reconciliation loop around these contracts.
