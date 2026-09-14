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

The native Filament close control is the final control at the top right. Contextual actions sit immediately to its left and use canonical `AdminIcon` entries. Apply `admin-dialog--header-actions` when the dialog has actions beyond close/cancel.

The common rule is:

- keep the close `X` at the far right; closing also cancels an uncommitted task;
- place contextual actions immediately before it in semantic order, with the action nearest the `X` being the right-most action;
- use the shared circular `admin-dialog__header-action` chrome;
- use central canonical icons, never local SVGs or emoji;
- keep destructive actions nearest the close control when present;
- use a commit/check action in the header for tasks that require explicit confirmation or form submission;
- do not add a redundant bottom action row.

Confirmation prompts use `admin-dialog--mini`: commit/check confirms, `X` cancels. Small settings tasks normally use `admin-dialog--small`; ordinary detail/edit dialogs use `admin-dialog--default`.

## Content

Dialog-internal metadata should follow the same alignment rhythm as the owning workspace. Dashboard feed details, for example, use a three-cell metadata row inside the default 3/6 dialog before the message body.

Do not invent placeholder facts merely to fill a grid cell. Content may use fewer cells when a record type has fewer meaningful metadata fields.

## Responsive behavior

The width modifiers are desktop maxima, not fixed mobile widths. On narrow viewports the shared contract reduces the viewport gutter and lets the dialog fit the available screen. Page-local horizontal compensation is not part of the dialog contract.
