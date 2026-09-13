# Admin dialog contract

Admin dialogs align to the same six-unit desktop workspace used by metrics and canonical tables. The shared implementation lives in `resources/css/admin/dialog-contract.css`.

## Widths

Use exactly one width modifier on the Filament modal window:

- `admin-dialog--mini`: 1/6 of the 80rem desktop workspace (`13.333rem`).
- `admin-dialog--small`: 2/6 (`26.667rem`).
- `admin-dialog--default`: 3/6 (`40rem`). This is the normal detail/edit dialog size.
- `admin-dialog--large`: 4/6 (`53.333rem`) for genuinely larger editorial tasks.

All sizes are capped by the current viewport gutter. Do not create page-local modal widths when one of these four roles fits.

## Chrome and actions

Inspect/detail dialogs use the native Filament close control as the final control at the top right. Contextual actions sit immediately to its left and use canonical `AdminIcon` entries. Apply `admin-dialog--header-actions` when this chrome is required.

For inspect/detail dialogs:

- keep the close `X` at the far right;
- render contextual actions as icon-only controls immediately before it;
- use central canonical icons, never local SVGs;
- keep destructive actions last and require confirmation where appropriate;
- do not add a redundant bottom button row when close/header actions are sufficient.

Form dialogs may retain explicit submit/cancel controls when the task actually requires form submission. The header-action rule is not a reason to hide necessary form semantics.

## Content

Dialog-internal metadata should follow the same alignment rhythm as the owning workspace. Dashboard feed details, for example, use a three-cell metadata row inside the default 3/6 dialog before the message body.

Do not invent placeholder facts merely to fill a grid cell. Content may use fewer cells when a record type has fewer meaningful metadata fields.

## Responsive behavior

The width modifiers are desktop maxima, not fixed mobile widths. On narrow viewports the shared contract reduces the viewport gutter and lets the dialog fit the available screen. Page-local horizontal compensation is not part of the dialog contract.
