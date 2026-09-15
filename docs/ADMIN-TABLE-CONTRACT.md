# Admin Table Contract

This document is the canonical ordering, alignment and typography contract for ordinary editorial tables and table-like hierarchies in the authenticated admin.

It complements `ui-skills.md`; both documents use the same current role order and action-order rules. `resources/css/admin/table-contract.css` is the shared implementation authority for that geometry.

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

## Control-to-table boundary

When a search/filter/control row directly precedes a table or hierarchy header, there is one separator only. The table/header boundary owns it.

Do not add a second bottom border to the controls row and do not compensate for duplicate separators with page-local margins, overlays or matching colors. The shared table contract removes that duplicate boundary for canonical task controls.

## Position and Add Row

The Position indicator and the leading `+` square of `x-admin.add-row` share one canonical square geometry and leading inset.

This gives ranked tables a stable left axis from header through rows to the persistent add action.

Do not compensate this alignment with page-local margins or padding.

## Action order

Use a stable semantic order when the actions exist:

```text
Move up | Move down | Edit / View | state / workflow | other task actions | Delete
```

Not every table has every action.

Rules:

- movement comes first because it belongs to ordering;
- Delete is last;
- state/workflow actions keep stable slots where row-state variation would otherwise make controls jump;
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
- when sibling toolbar groups use visible role labels such as `TYPE`, `STATUS`, `FILTER` and `PAGES`, the bulk group uses the same `SELECTION` label so the toolbar hierarchy stays consistent;
- the checkbox column itself does not receive redundant visible `Selection` text; an accessible label on the select-all checkbox is sufficient;
- destructive bulk behavior retains the same domain safeguards as row actions.

## Responsive direction

The contract is deliberately prepared for progressive reduction rather than horizontal chaos.

Target behavior for later responsive work:

- wide: six-unit composition, full important data and action labels;
- medium: four-unit composition, secondary/nonessential data may be removed, actions can become icon-only;
- narrow/phone: two-unit composition, identity and essential state/actions only, with editing delegated to the Edit dialog where appropriate.

Responsive reduction must preserve semantic DOM/action order. Do not visually reorder cells with CSS while leaving keyboard/screen-reader order behind.

Metrics and table geometry may respond at related breakpoints, but neither is implementation-dependent on the other.

## Implementation authority

Shared implementation lives in:

- `resources/css/admin/table-contract.css`
- `resources/css/admin/task-surfaces.css`
- `resources/css/admin/data-workspace.css`
- `x-admin.table`
- `x-admin.controls`
- `x-admin.add-row`
- shared `.admin-position`, `.admin-drag-handle`, `.admin-row-actions`, `.admin-table__selection` primitives

Feature CSS may size genuinely task-specific content columns, but it must not redefine the canonical role order, typography roles, Position/Add Row axis, action order, single control-to-table boundary or trailing Selection convention.
