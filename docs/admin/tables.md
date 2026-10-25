# Admin Table Contract

This document is the canonical ordering, alignment and typography contract for ordinary editorial tables and table-like hierarchies in the authenticated admin.

`docs/admin/ui.md` links here rather than duplicating ordinary-table roles or action-order rules. `resources/css/admin/table-contract.css` is the shared implementation authority for that geometry.

## Scope

Apply this contract to ordinary admin tables and table-like editorial hierarchies.

Do not force it onto task-specific visual surfaces such as Gallery contact sheets, media grids, maps, charts or other visual stages merely for uniformity.

A table only renders roles that actually exist for its task. A storage table without ordering does not gain fake Position or Drag columns.

## Canonical role order

From left to right:

```text
Position + Drag | primary identity | secondary data | Actions | Selection
```

Rules:

- Position comes before Drag.
- Position and Drag share one `Position` header when both exist.
- Position is a human-readable 1-based rank; persistence values remain an implementation detail.
- Actions are the last semantic work column.
- Selection / multi-select is the final utility column on the far right when present.
- The header selection checkbox and every row selection checkbox occupy the same trailing column.
- Omitting Position, Drag or Selection must not create placeholder columns.

## Alignment grid

Wide desktop composition uses a six-unit alignment grid as a geometry reference:

```text
1 unit = 1 / 6 of the shared workspace width
```

This grid is independent of whether the metric strip is visible. Metrics may later be user-configurable or hidden entirely without changing table geometry.

Recommended allocations are task-dependent, for example:

```text
Position + Drag   0.5 unit
Primary identity  1.5 units
Secondary data    2 units
Actions           2 units
```

These are composition tools, not mandatory filler. A table should merge or omit roles rather than invent data solely to consume six units.

`resources/css/admin/table-contract.css` owns the shared alignment-unit helpers.

## Typography and fitting

Ordinary table, hierarchy and adjacent control-bar typography is shared UI grammar. Feature/page CSS does not choose a smaller font merely because a local column is tight.

The semantic sizes for control labels/values, table headers, table body text, table identity/meta text and compact table utilities are owned by the shared admin theme/table contract. Consumers use those shared tokens or inherit the shared primitive. In particular:

- table and hierarchy headers use the shared table-header typography;
- ordinary row actions inherit `.admin-action` typography;
- search/filter/tool-bar labels and values use the shared control typography;
- table body/identity/meta roles use the corresponding shared table typography;
- responsive rules must not reduce any of these font sizes just to recover a few pixels.

When content does not fit, solve geometry first: redistribute grid tracks, remove avoidable gaps/padding, allow the canonical ellipsis behavior, remove genuinely secondary columns, or switch actions to icon-only at an explicit responsive breakpoint while preserving accessible labels. Do not create page-local `font-size` overrides or media-query font shrinkage as a fitting technique.

If a genuinely new semantic text size is needed across the admin, add or change the shared theme/token intentionally and apply it by role. Do not introduce an unexplained literal in one table or toolbar.

## Row presentation and status-marker alignment

Ordinary tables use **one canonical header row**, with identical column axes through every body row. Child rows in a single editorial hierarchy share those axes; a nested child table must not introduce a redundant independent header or floating columns. Action cells have one consistent right edge and share their slots with the table heading. If a row has fewer applicable actions, do not remove its leading action slot and shift all following icons.

Use an elastic primary identity track: ellipsize long identity/supporting content **before** it displaces essential action or selection columns. One or two **meaningful** text lines per cell may be appropriate where the secondary line adds real context, but avoid dense stacks of four or five redundant fragments. Status markers/dots in one semantic column have a stable marker axis through all rows even when supporting values (for example a commit hash) appear on a second line. Desktop row-action labels do not wrap opportunistically: use the shared intentional responsive icon-only/overflow transition. Do not introduce vertical cell borders merely to visualize a metric or column alignment that existing Admin tables express through spacing and track geometry.

Do not add vertical separators merely to make the six-unit alignment ruler visibly obvious where the accepted table presentation uses spacing rather than borders. This entire rule applies through the shared table/task primitives and is not an invitation to add page-local `font-size` or pixel-fix overrides.

## Control-to-table boundary

When a search/filter/control row directly precedes a table or hierarchy header, there is one separator only. The table/header boundary owns it.

Do not add a second bottom border to the controls row and do not compensate for duplicate separators with page-local margins, overlays or matching colors. The shared table contract removes that duplicate boundary for canonical task controls.

The shared table empty state is **content**, not another framed surface. Inside `x-admin.table` or its hierarchy, `x-admin.empty-state` adds **no top or bottom borders**: the table header owns the top separator and the next `x-admin.add-row` or pager owns the lower transition. This applies equally to truly empty and filtered-empty tables (Custom Page, Pages, Dashboard, Home, Journal, General, Activity and Analytics). Outside tables, standalone empty states may keep their own borders; when such a state directly precedes `x-admin.add-row` (for example empty Storage), the add row suppresses its duplicate top border. These boundaries are implemented centrally in `resources/css/admin/data-workspace.css`, not with feature-local border or spacing overrides.

## Position and Add Row

A Position rank is a **1-based editorial display value**, not the sparse/zero-based stored order. Compact labels such as `01`, `02`, `03` are valid when legible. Add Position intentionally to reviewed ordered tables, not as an unrequested global presentation rewrite.

The Position indicator and the leading `+` square of `x-admin.add-row` share one canonical square geometry and leading inset.

This gives ranked tables a stable left axis from header through rows to the persistent add action.

Do not compensate this alignment with page-local margins or padding.

## Component sequences

Custom Page and Home share the parent columns **Position / Drag / Component / Content / Actions / Selection** and the same shared table primitives. However, their current child **presentation** differs: on **Custom Page**, Contact is ONE parent row. All Contact options (Public Email, Social Media Links, Contact Form, optional custom heading, social-platform selection, form mode and status text) are edited in the parent dialog. List entries likewise live in the List parent editor. The Custom Page table renders no nested **Contact-option/List-item** rows. Decorative Image **component children** use the SAME complete six-column row template as top-level blocks, including Type, Content, actions, drag and a currently rendered Selection checkbox; Image Edit owns only Image properties, not a second child Repeater. The [source trace](../architecture/ui-surfaces.md#custom-page-image-children-komponentenaktionen-und-selektionsgrenze) records the source-level `dev@8d38922e` Child-target, Select-All and mixed-bulk integration. Do not mark it accepted until mixed parent/child reordering remains identity-safe and multi-target deletion runs atomically under a single outer transaction. Contact Content presents the configured heading (fallback Contact) and the enabled items in a concise secondary line; Legal Disclaimer shows the General text in a one-line excerpt beneath its identity.

**Home Custom / Under Construction still has visible child rows and separate child targets in its current Blade.** Its child rows retain the same table axes and their sibling-scope ordering, not a second nested table design. Do not describe the new Custom Page parent-only presentation as already implemented for Home.

The **Component** column stays interactive at all supported widths, including when Search, filtering or pagination disables drag/reorder. Component and Content are distinct tracks across Wide/Compact/Narrow/Minimal. Do not duplicate the Component label in Content or hide its selector to create artificial responsive room. Parent actions follow **Move up, Move down, Edit, Publish/Unpublish, Delete**, with domain guards controlling availability; Home child actions retain their existing scope and order. The persisted `published` flags are not an independent Status table column.

**Cross-feature preservation rule (dev@74e629274ac2):** Pages root-level **reordering** and deliberate **child placement** are two different gestures sharing native Livewire sorting but with different intended destinations. The user has already accepted the distinction in the current Pages review; a future source refactor must compare first/middle/last root groups, empty children and return-to-root with the actual rendered drop targets, not merely assert that `sortSection()` stores a new index. Custom Page Image-child drag is a **different consumer** of the shared sorting grammar; an unrelated fix may not silently change Pages. Verify the accepted gestures in the current rendered hierarchy and review the relevant [browser workflow](browser-review.md). A source-level sort-index assertion cannot establish drag-target or nested placement behavior.

The named sortable group stays `site-pages` in Pages and `custom-components` in Custom Pages; root and Image/Page receiving groups use the same per-editor group name with different parent IDs. Image children retain independently editable/selectable table rows and the canonical six-column axes. Do not revive nested child repeaters inside Image Edit, floating “Move out” controls or redundant child counters as alternatives to the native hierarchy.

- **Native cross-level sorting is now the sole Drag owner (`dev@a316e59b`).** Root and allowed child containers of each editor share ONE native `wire:sort:group` name and use distinct `wire:sort:group-id=root|parent` values. Pages dispatch `sortSection` to the canonical `SiteSectionOrderService`; Custom Page root/child groups dispatch `sortComponent`/`sortImageChild` through a single transactional `CustomPageEditorialService::placeComponent`. Native sorting **may explicitly reparent** when the receiving group changes, and it otherwise reorders siblings. The previous `admin-hierarchy-drag.js` pointer engine and special nest/root drop overlays were deleted. Do not recreate them. A decorative Image with no children must retain a reachable native child group; prohibited child types, attached List-media and active Contact Forms remain domain-validated. `HierarchyParentField` Edit remains an alternative to drag. Preserve publication and list Context, and make audit/notifications reflect real persistence/no-op. See [source trace](../architecture/ui-surfaces.md#native-hierarchie-placement-pages-und-custom-pages) and [structured model](../architecture/structured-components.md).
- Enable a native drag handle when the canonical ordering projection is complete and unfiltered **even if a source group contains only one item**: that item can still move to another eligible root/child group. For Custom Page, `CustomPageWorkspaceProjectionState::componentReorderEnabled()` gates Search, type filter, pagination and incomplete page; the Blade then binds `wire:sort` and `wire:sort:handle` independent of sibling count. Keep empty decorative Image child groups reachable as drop destinations. By contrast, **Move up/down buttons are disabled at actual sibling boundaries**. Pages and Home retain their own source-validated gating. Use the shared `admin-drag-handle`; do not create a second drag engine.
- Component type selection is independent of drag eligibility. Custom Page conversion uses existing `AdminDialog::confirm()` and `CustomPageEditorialService::convertBlock()`; do not create a second mutation route.
- Home's seven editor choices include Heading/Rich Text that both persist as `type=text`; Divider supports Thin/Subtle/Strong/Dotted. See [structured-components](../architecture/structured-components.md).
- Selection is atomic and scope-aware. Custom Page's domain bulk owner accepts parent **and** Image-child targets; as of `dev@883ff5e0`, moves process children before parent reordering and both mixed Move and mixed Delete have an outer DB transaction. Actual rollback/audit/no-op/browser behavior and stable identities remain unverified. Home retains separately owned mixed child selection. Never split an existing mixed Home mutation into partial writes. The bottom **Add component** row remains the shared Add Row primitive.
- Notifications follow [the canonical Notification contract](notifications.md#admin-mutation-feedback-integration): count follows verified Activity, and no-op/invalid actions never report a false success.

**Implementation sources:** [Custom Page rows](../../resources/views/filament/pages/partials/custom-page-workspace-sequence.blade.php), [Home rows](../../resources/views/filament/pages/partials/home-component-sequence.blade.php), [Custom Page editorial owner](../../app/Domain/Content/CustomPageEditorialService.php), [Home editorial owner](../../app/Domain/Content/HomePresentationEditorialService.php). This is source-derived, not a new browser acceptance.

## Action order

Use a stable semantic order when the actions exist:

```text
Move up | Move down | Edit / View | state / workflow | other task actions | Delete
```

Not every table has every action.

Rules:

- movement comes first because it belongs to ordering;
- Delete is last;
- state/workflow actions keep stable slots where row-state variation would otherwise make controls jump. Reserve only the longest real label (including icon and padding), not arbitrary spare width: shorter labels necessarily leave some whitespace so their icon axis and subsequent Delete axis stay aligned across rows;
- the Actions heading, table column and stable button rail derive their widths from the same semantic recipe; static rails must not reserve extra unused space before the first button;
- desktop may use icon + label when space supports it;
- compact responsive modes may hide action labels while retaining the same DOM/action order, icon, accessible label and keyboard order;
- action labels are never made smaller on one feature to force them into their slots.

Operational/history tables may have a different canonical leading action. When `Details` is that action, keep `Details` first on every row and append contextual actions such as Undo, Restore or Revert after it rather than shifting the leading slot.

## Selection

Selection is a trailing utility, not row identity.

When bulk selection exists:

- the row checkbox is the far-right column;
- the select-all checkbox is directly above it;
- the control-bar multi-action trigger remains the corresponding bulk-action affordance;
- the circular selected-count badge is centered on the **same horizontal axis** as the trailing select-all and row checkboxes below it; its usual placement is in the Selection trigger;
- **Accepted Dashboard feed exception:** [dashboard-feed.blade.php](../../resources/views/filament/pages/partials/dashboard-feed.blade.php) renders the `Selected` trigger with icon/label **without an internal count badge** and a separate sibling `span.admin-selection__count` immediately after the trigger's anchor, inside the one terminal Selection group. This distinct composition keeps the **same badge/checkbox axis**; moving the count into the button is not a required normalization;
- `x-admin.controls` keeps Selection as its terminal toolbar slot, and the shared table contract reserves `--admin-table-selection-width` in the ordinary Selection trigger or, for Dashboard feed, in its terminal Selection-group count slot. Page/feature CSS must not recenter or offset that axis with local margin, padding or duplicate grid rules;
- this is a hard cross-surface axis: while the surface remains a table, redistribution of Search, filters, data columns or Actions may never move the Selection centerline; the toolbar count circle, select-all checkbox and every row checkbox stay exactly aligned;
- when sibling toolbar groups use visible role labels such as `TYPE`, `STATUS`, `FILTER` and `PAGES`, the bulk group uses the same `SELECTION` label so the toolbar hierarchy stays consistent;
- the checkbox column itself does not receive redundant visible `Selection` text; an accessible label on the select-all checkbox is sufficient;
- destructive bulk behavior retains the same domain safeguards as row actions.
- the header checkbox operates on the current visible set: from **none or some**, select all visible; from **all**, clear all visible. Turning it off does not restore an earlier subset. Filtering/paging must never show a selected header for zero selected rows;
- the header's native `checked` and `indeterminate` properties and accessible `aria-checked` state come from the same reactive visible-ID/selected-ID projection. Do not cache these states separately in server booleans or static Blade attributes; individual `wire:model.live` checkboxes have Livewire as their sole checked-state owner.

This count-to-checkbox axis applies to ordinary tables and table-like hierarchies, including views that preserve the same toolbar while switching between table modes. Activity and Commits are concrete examples: both keep the same terminal Selection rail while their multi-action menu exposes only actions valid for the active history table and current selection. A contact-sheet/card surface such as Gallery, which has no single trailing table Selection column, does not invent a fake axis merely to satisfy this rule.

At an intentional responsive breakpoint where controls or the table stack/reflow into a different composition, exact cross-row pixel alignment may relax. Before that breakpoint the desktop/table axis is invariant.

## Responsive direction

Tables use the shared semantic width states **Wide**, **Compact**, **Narrow** and **Minimal**, but transition according to the table container's actual available width rather than a device label or a metric breakpoint.

Supportive columns declare their pressure priority with the shared table classes rather than feature-local hide rules:

- `.admin-table__yield--compact` disappears at Compact pressure;
- `.admin-table__yield--narrow` disappears at Narrow pressure;
- `.admin-table__yield--minimal` disappears only at Minimal pressure.

Apply the same declaration to the matching `<col>`, header cell and body cells so one semantic column changes atomically. Feature CSS may still size surviving content columns, but must not duplicate the visibility breakpoint. Actions and the terminal Selection rail never use a yield class.

- Wide: full important data and action labels.
- Compact: supportive data may merge into primary cells; actions may become icon-only.
- Narrow: optional columns disappear; identity, essential state/actions and Selection remain.
- Minimal: retain the smallest semantically complete table; lower-priority actions may move into a `…` overflow action.

Ordinary tables must fit without horizontal scrolling. Do not reserve horizontal-scrollbar space and do not hide scrollbars while leaving overflow underneath. If a table would overflow, reduce supportive/optional columns or action chrome before that point.

Shared pagination is part of the same table surface and remains exactly one row at every supported width: page-size control left, range centered, Previous/Next right. It may tighten gaps and button padding, but it does not stack into a second row.

Selection is invariant while the surface remains a table: the toolbar count/trigger, select-all checkbox and row checkboxes stay on the same terminal rail at every state. Other content yields before that rail drifts.

Responsive reduction must preserve semantic DOM/action order. Do not visually reorder cells with CSS while leaving keyboard/screen-reader order behind, and do not reduce shared semantic font sizes as a fitting technique.

Toolbars are the exception to structural row reflow: the canonical toolbar remains exactly one row at every responsive state. Width pressure is handled by track redistribution, filter compaction and complete-label → icon-only/overflow action transitions, never by creating a second toolbar row or by clipping labels into fragments.

Metrics, tables, toolbars and stages may transition at different widths because each responds to its own available container width.

## Split/fold columns

Two meaningful supporting columns may combine under **actual table-container pressure**. The central `table-contract.css` owns `admin-table--fold-dense` (**72rem**, Blog) and `admin-table--fold-at-compact` (**64rem**, Exhibitions). Apply `admin-table__split-only` consistently to the secondary `<col>`, header and every body cell, while the primary cell uses `admin-table__split-label` and `admin-table__folded-only`. Do not substitute viewport/metric breakpoints or duplicate these hide rules in Journal CSS.

### Journal's split/fold composition

```text
Blog Wide:          Position | Drag | Image | Post | Date | Status | Actions | Selection
Blog Folded:        Position | Drag | Image | Post | Date          | Actions | Selection
Exhibitions Wide:   Position | Drag | Image | Exhibition | Date | Status | Actions | Selection
Exhibitions Folded: Position | Drag | Image | Exhibition | Dates | Actions | Selection
```

**Blog:** At the maximum **80rem** workspace width, Date, Status and all seven readable Action labels remain separate. At **72rem actual table width**, **one central container rule** simultaneously folds Status beneath Date and compacts the Action labels to icons. The shared `table-contract.css` owns both changes; page-local action widths cannot override them. Date reserves **7rem**, separate Status **6rem**, and Post identity takes the remaining width. Blog Date shows scheduled/published date or a neutral dash. Post title and secondary summary are separately one-line with ellipsis; the latter uses the authored excerpt, otherwise a whitespace-normalized body preview limited to **140 characters** in the PHP projection.

**Seven fixed action slots:** slot 4 is Publish/Unpublish/Cancel schedule, slot 5 Schedule, slot 6 Archive/**Restore**, and slot 7 Delete. Archived entries disable inapplicable Publish/Schedule actions; **Restore always occupies Archive slot 6, never Publish slot 4**. The Blog Actions rail remains a **stable seven-track grid** with identical icon axes across rows and publication states, including Archive/Restore. The fourth publication slot now uses the canonical **6rem** `--admin-row-action-slot-state-width` instead of the obsolete **9rem** Cancel-schedule track; its visible label is **Cancel**, while tooltip and accessible name remain **Cancel schedule**. This fixes empty gaps without label-dependent icon movement. At **72rem**, the same shared owner switches the seven tracks to equally sized icon-only slots. Delete stays on its trailing axis; there is no extra Actions column or page-local spacer.

**Exhibitions:** Structured `starts_on`/`ends_on`, not the public free-text date override, produce Date. A range is exactly **one unbroken line** (`dd.mm.yyyy – dd.mm.yyyy`); a lone date (including end-only legacy data) also occupies one line. Optional Vernissage follows on its **own single line** as `Vernissage: dd.mm.yyyy, HH:mm` for structured `vernissage_at`, otherwise using authored `opening_text`. The Vernissage line can ellipsize under pressure; the structured range must remain visible without wrapping in its date cell. At **64rem** the separate derived **Past/Current/Upcoming** Status folds beneath Dates and Exhibition actions become icon-only. The Date track is **12.5rem** in both split and folded states, with **8rem** for the separate Status track. The Exhibition title and concise `Venue · City` summary occupy two individually non-wrapping, ellipsized lines.

**Both:** The Cover/feature-media preview uses the same canonical media presentation and a neutral empty state. Position + Drag lead; Selection remains trailing. At **54rem** the Date/Dates column yields before essential Post/Exhibition identity is squeezed; only then does the primary identity expose the date/status/optional Vernissage as additional individually non-wrapping metadata lines. The centralized responsive hide rule outranks feature-local `small { display: block; }` while the dedicated data column remains visible. The shared `admin-table__identity--ellipsis-meta` modifier in `data-workspace.css` owns identity/title/secondary-summary ellipsis. Journal CSS owns its content-track sizes, preview and feature presentation. **Actions and Selection never yield.** None of these display rules changes persisted publication state.

**Source owners:** [Journal projection](../../app/Filament/Pages/JournalWorkspace.php), [Blade](../../resources/views/filament/pages/journal-workspace.blade.php), [Journal CSS](../../resources/css/admin/journal.css), [shared table-pressure CSS](../../resources/css/admin/table-contract.css), [shared identity CSS](../../resources/css/admin/data-workspace.css).

## Pages Home row: routing actions

The Home row in Pages remains an ordinary **table-row action owner**, not an extra Settings dialog or a bulk Selection control. The visible `Skip Home` / `Show Home` state button is one-click through `ManagesHomePagePresentation::toggleSkipHome()`: enabling checks for an eligible published destination, writes only through `HomeRoutingSettingsService`, and refreshes the Pages projection; a validation error reports a warning rather than a false success. It never mounts a separate Skip Home Action dialog.

When Skip Home is active, the Home row exposes an inline destination dropdown with `Next page in order` or another eligible published top-level target. `changeSkipHomeTarget()` validates and commits through **the same Home routing service**; target options come from the shared `HomeRoutingDialog` rather than a second query/validation scheme. It does not open Home Settings. The established Skip icon remains authoritative; `Show Home` uses the registered crossed-circle/no-symbol semantic action. All ordinary row-action icons use the shared Filament Small icon geometry (1rem); changing the visible semantic icon must not change row height.

The destination dropdown borrows the canonical `admin-selection__menu` **popup surface**, but its owner remains the `admin-pages__redirect-target` **row action**, never the taller `admin-selection__anchor` toolbar trigger. Do not use the latter's 2.5rem minimum control height or create a duplicate row/select system. This is a task-specific table behavior; Skip Home routing semantics remain owned by the [Public contract](../public/site.md), and shared dialog persistence by the [Dialog contract](dialogs.md).

**Source:** [Home row Blade](../../resources/views/filament/pages/partials/site-section-row.blade.php), [row Actions](../../app/Filament/Pages/Concerns/ManagesHomePagePresentation.php), [Home routing service](../../app/Domain/Content/HomeRoutingSettingsService.php), [shared target options](../../app/Filament/Support/HomeRoutingDialog.php).

## Implementation authority

Shared implementation lives in:

- `resources/css/admin/table-contract.css`
- `resources/css/admin/task-surfaces.css`
- `resources/css/admin/data-workspace.css`
- `x-admin.table`
- `x-admin.controls`
- `x-admin.add-row`
- shared `.admin-position`, `.admin-drag-handle`, `.admin-row-actions`, `.admin-table__selection` primitives

Feature CSS may size genuinely task-specific content columns, but it must not redefine the canonical role order, typography roles, Position/Add Row axis, action order, single control-to-table boundary, trailing Selection convention or selected-count/checkbox axis.

## General Appearance color-stop table

General's three global public paint surfaces use the shared compact native Color/Position table. Solid shows one color; Linear/Radial/Conic validate two to twelve stops, shared native color inputs, numeric stop positions and standard gray table add/remove actions. There is no parallel bespoke red delete icon, new paint-picker framework or separate color-row rendering contract. The rotary angle popup is a shared `AdminAngleControl` beside an editable numeric degree value. This belongs to General's field presentation; other table role/selection/reorder invariants in this document remain unchanged. Refined on `dev@0a7952e12b49`: the single inline table has no second hidden stop form or obsolete dialog-only gradient view; domain validation remains authoritative for invalid positions and editable endpoints. The associated feature assertions were read, not run. No Filament/browser confirmation from the docs workstream.

