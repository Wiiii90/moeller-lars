# Admin Table Contract

This document is the canonical ordering, alignment and typography contract for ordinary editorial tables and table-like hierarchies in the authenticated admin.

`ui-skills.md` links here rather than duplicating ordinary-table roles or action-order rules. `resources/css/admin/table-contract.css` is the shared implementation authority for that geometry.

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

Use an elastic primary identity track: ellipsize long identity/supporting content **before** it displaces essential action or selection columns. One or two **meaningful** text lines per cell may be appropriate where the secondary line adds real context, but avoid dense stacks of four or five redundant fragments. Status markers/dots in one semantic column have a stable marker axis through all rows even when supporting values (for example a commit hash) appear on a second line. Desktop row-action labels do not wrap opportunistically: use the shared intentional responsive icon-only/overflow transition.

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

Custom Page and Home (Custom / Under Construction) use the same hierarchy row
primitives: **Position / Drag / Component / Content / Actions / Selection**.
Component is a permanent editable select, even when filters disable drag
ordering or at narrow widths. Never repeat its type in Content as responsive
metadata. Content alone absorbs remaining space. Home and Custom Page
share the five-action row grammar (Move up, Move down, Edit,
Publish/Unpublish, Delete) and a nested List/Contact hierarchy. Nested rows
support reorder, editorial actions and selection through the owning service.

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
- the circular selected-count badge in that trigger is centered on the **same horizontal axis** as the trailing select-all and row checkboxes below it;
- `x-admin.controls` keeps Selection as its terminal toolbar slot, and the shared table contract reserves `--admin-table-selection-width` at the end of the Selection trigger for the count badge; page/feature CSS must not recenter or offset that badge with local margin, padding or duplicate grid rules;
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

When two meaningful columns fit in Wide but should combine under table pressure, keep one primary column and one additional detail column. The shared table-pressure owner offers `admin-table--fold-at-compact` (64rem) with `admin-table__split-only` for the extra column and `admin-table__split-label` / `admin-table__folded-only` for its counterpart in the primary column. Both Journal templates now fold at the same actual table width; Exhibition Date/Timing are separate at full width with modest supporting tracks to protect identity. Apply `admin-table__split-only` to its `<col>`, `<th>` and every `<td>` together; only the extra column yields. The folded primary column can use the normal Narrow-yield contract (54rem), with essential metadata shown in primary identity only after it disappears.

### Journal's split/fold composition

The Journal has **separate full-width columns**. The only fold transitions are actual **table-container widths**; neither the viewport width nor the metric state is a surrogate:

```text
Blog Wide:          Position | Drag | Image | Post | Status | Publication | Actions | Selection
Blog Folded:        Position | Drag | Image | Post | Publication          | Actions | Selection
Exhibitions Wide:   Position | Drag | Image | Exhibition | Date | Timing | Actions | Selection
Exhibitions Folded: Position | Drag | Image | Exhibition | Dates          | Actions | Selection
```

Blog's seven-action rail becomes icon-only at **88rem** before the Post track is squeezed. Blog keeps **Status** (editorial state) and **Publication** (scheduled/published date) in separate tracks until **64rem**, then folds both into the primary **Publication** track (11rem at that breakpoint). Exhibition **Date** (date range with optional Vernissage underneath) and **Timing** (only derived Past/Current/Upcoming) remain separate until **64rem** (9.5rem and 8rem respectively at wider widths), then fold into **Dates** in an 11rem supporting track. The folded Dates cell preserves range, optional Vernissage and timing in that order. Exhibition actions compact at **64rem**. Both types let long dates wrap without arbitrary truncation.

Blog and Exhibitions use the same Cover/feature-media preview when configured, with a neutral fallback when no preview is available. Position and Drag share the leading ordering region and Selection remains the trailing utility rail. At **54rem**, the remaining Publication/Dates column yields **before** the primary Post/Exhibition identity becomes unusable. The Post identity then provides its editorial state and date; Exhibition identity provides date range, derived timing and optional vernissage. These extra identity lines are hidden while the dedicated data column remains visible. Exhibition location metadata stays concise (e.g. `Venue · City` rather than a street/country dump).

The canonical `admin-table--fold-at-compact` / `admin-table--fold-at-actions` classes own split visibility; `resources/css/admin/journal.css` alone owns Journal track widths, preview styling and responsive identity presentation. The action `<col>` and heading inherit the same shared responsive rail width as the controls. **Actions and Selection never yield.** These are presentation rules, not a change to the stored Blog/Exhibition publication state.

**Code references:** [Journal Blade](../resources/views/filament/pages/journal-workspace.blade.php), [feature CSS](../resources/css/admin/journal.css), [shared CSS](../resources/css/admin/table-contract.css).



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
