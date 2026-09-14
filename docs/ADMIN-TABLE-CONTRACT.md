# Admin Table Contract

This document is the canonical ordering and alignment contract for ordinary editorial tables and table-like hierarchies in the authenticated admin.

It complements `ui-skills.md`. Where older table examples in `ui-skills.md` still show Selection first or Drag before Position, this document and `resources/css/admin/table-contract.css` define the current contract.

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
- compact responsive modes may hide action labels while retaining the same DOM/action order, icon, accessible label and keyboard order.

## Selection

Selection is a trailing utility, not row identity.

When bulk selection exists:

- the row checkbox is the far-right column;
- the select-all checkbox is directly above it;
- the control-bar multi-action trigger remains the corresponding bulk-action affordance;
- a self-describing trigger such as `Selected 3` does not receive a redundant visible `Selection` label above it;
- the checkbox column does not receive redundant visible `Selection` text; an accessible label on the select-all checkbox is sufficient;
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
- `x-admin.table`
- `x-admin.add-row`
- shared `.admin-position`, `.admin-drag-handle`, `.admin-row-actions`, `.admin-table__selection` primitives

Feature CSS may size genuinely task-specific content columns, but it must not redefine the canonical role order, Position/Add Row axis, action order, single control-to-table boundary or trailing Selection convention.
