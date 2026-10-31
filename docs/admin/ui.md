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
- invalid actions remain visible but disabled, including empty selections and domain-ineligible mixed states; the [single Tables/Hierarchy action contract](tables.md#action-order) owns the actual selected-state and sort/filter eligibility rules across Gallery, Journal, Pages, Home and Dashboard;
- do not duplicate separate “selected parents” and “selected children” menus in the same toolbar;
- mixed selections must not cause ambiguous mutations;
- destructive bulk actions require the same domain safeguards as row actions;
- selection should be cleared/reprojected after mutations that invalidate positional targets.

## 7. Table grammar

The **[single Admin Table Contract](tables.md)** owns ordinary column roles, semantic action order, Selection position, row typography, alignment, accessible controls and breakpoint-driven fitting. Editorial work uses a table where ordered rows and actual actions benefit from that representation, not a fake card list. Do not restate the same column or action matrix in this UI overview.

## 8. Ranked tables and Position

Ranked task tables expose a readable one-based **Position** when editorial order matters, according to [Position and Add Row](tables.md#position-and-add-row). Applying this convention elsewhere is a scoped feature decision, not authorization to rewrite every table or expose internal sparse position values.

## 9. Drag and ordering

Pages site-tree reordering, Custom Page Image-child placement and Home component ordering are **distinct domain operations** that share the established native Livewire drag primitive and the [Table/Hierarchy contract](tables.md#component-sequences). The Table owner preserves the user-accepted Pages root-before/middle/after and deliberate nesting gestures, empty-child target sizing, root separator and action/selection/keyboard behavior. No second pointer engine, extra drop surface, page-specific state machine or replacement layout is permitted as a shortcut. A backend reorder assertion does not substitute for a real drag/hover/drop review.

## 10. Custom Page component table and Home child hierarchy

The [Table/Hierarchy contract](tables.md#component-sequences) owns the shared six-column grammar, native ordering, selectable independent Image children, Home's separately scoped Contact/List children, component selection at narrow widths, Domain-owned publication and atomically applied mixed actions. Custom Page Contact/List options are edited **within their parent**, not as independently rendered child rows; Home retains its distinct visible child presentation. These are two consumers of shared tables, not interchangeable editorial data models.

[Public Custom Page presentation](../public/site.md) owns Image float/wrap, Contact Form width, headings, spacing and rendered content; the [central structured-component model](../architecture/structured-components.md) owns types and stored value normalization. A source-level structure or shared table is not itself user acceptance.

## 11. Home component table

Home Custom and Under Construction use the same shared table primitives as Custom Page, **not an identical set of child rows**. [Component sequences](tables.md#component-sequences) defines the current Home child/parent selection, action, ordering, disabled-state and Add Row behavior; [Notifications](notifications.md#admin-mutation-feedback-integration) defines exactly one successful message per real committed mutation and none for no-op/invalid actions. Do not convert Home's existing child workflows merely to make its DOM resemble Custom Page.

## 12. Journal table references

The **[Journal split/fold table contract](tables.md#journals-splitfold-composition)** owns the complete Blog/Exhibitions column and action matrices, distinct calendar versus publication state, essential metadata, seven fixed Blog action slots, and container-based Wide/Compact/Narrow transitions. Shared table pressure is implemented in `table-contract.css`; the Journal stylesheet only sizes and styles Journal-specific content. There is no second action rail or alternate responsive table policy here.

The **[Admin Dialog contract](dialogs.md#journal-blogexhibitions-edit-aktionen)** owns Journal Create/Edit modal lifecycle and header actions. Blog uses Basics/Text/Gallery sections; the Exhibition editor uses the flat shared responsive schema and the [Exhibition editorial contract](../content/exhibitions.md) governs its specific fields and Gallery media table. Neither a tabular Journal row nor this UI overview defines a separate persistence owner.

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
- `primitives.css`;
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

- `resources/css/admin/primitives.css` defines the single `--admin-visual-stage-height` token;
- `resources/css/admin/stage.css` consumes that height and owns shared divider geometry and `--admin-stage-orbit-size`;
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
