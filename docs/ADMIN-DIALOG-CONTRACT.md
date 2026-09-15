# Admin dialog contract

Admin dialogs are native Filament Action modals with one shared presentation and lifecycle contract. The implementation lives in `App\Filament\Support\Dialogs\AdminDialog` and `resources/css/admin/dialog-contract.css`.

Do not create page-local modal families, custom close behavior or footer button systems.

## Dialog types

Exactly five semantic dialog types are supported:

### Edit

Edits operate on an existing record or settings object.

- changed values persist through the canonical discrete autosave path;
- no Save/Apply/Cancel footer exists;
- the native Filament `X` closes the dialog and does not trigger another write;
- text does not save per keystroke and no debounce timer is used;
- where the current edit session has reversible receipts, an Undo changes action may appear immediately left of `X`.

Use `AdminDialog::edit()`.

### Create

Creates collect a complete new object before persistence.

- opening the dialog must not create an empty/placeholder record;
- the commit/check action creates the record atomically;
- `X` cancels the uncommitted create task.

Use `AdminDialog::create()`.

### Command

Commands collect parameters for a discrete existing-domain action such as Move, Schedule or Attach.

- no mutation occurs until commit;
- the commit/check action executes the command atomically;
- `X` cancels.

Use `AdminDialog::command()`.

### Confirm

Confirmation dialogs guard a concrete action without editable form state.

- use the mini width;
- the header confirmation icon executes the action;
- destructive confirmations use the danger treatment and canonical Delete icon;
- `X` cancels;
- no bottom action row.

Use `AdminDialog::confirm()`.

### Viewer

Viewers display detail/preview content without form persistence.

- no submit/cancel footer;
- contextual actions may appear immediately left of `X`;
- `X` closes the viewer.

Use `AdminDialog::viewer()`.

## Widths

Dialogs align to the same six-unit desktop workspace used by metrics and canonical tables. Use `AdminDialogSize` rather than page-local width values:

- `Mini`: 1/6 of the 80rem desktop workspace (`13.333rem`) — confirmations only;
- `Small`: 2/6 (`26.667rem`) — normal create/edit/command settings tasks;
- `Default`: 3/6 (`40rem`) — normal viewers or editors needing more horizontal content;
- `Large`: 4/6 (`53.333rem`) — genuinely large editorial forms/viewers.

All sizes are capped by the viewport gutter.

## Chrome and actions

The native Filament close control is always the final top-right control. Filament continues to own modal state, focus trapping and Escape behavior.

Contextual actions are native Filament Actions lifted into the shared header rail:

- `X` is always at the far right;
- contextual actions sit immediately to its left in semantic order;
- use `admin-dialog__header-action` and canonical `AdminIcon` entries;
- commit actions use the primary treatment;
- destructive actions use the danger treatment;
- never use local SVGs or emoji;
- never add a redundant bottom action row.

## Edit persistence and Undo

Edit autosave must follow `ADMIN-CONTROL-CONTRACT.md`: discrete semantic changes only, never timer/debounce persistence.

Undo must extend the existing Activity/Audit receipt architecture. Do not create an independent dialog snapshot/rollback system. An Undo action may only be shown for mutations that have safe current receipts; applying Undo creates inverse editorial actions through the canonical `AdminUndoService` path.

The framework helper `AdminDialog::editCommit()` exists only as a migration bridge for existing atomic edit workflows whose domain semantics cannot safely be converted in the same source pass. It must not be used for new dialogs and must be removed from each flow once that flow has canonical autosave/receipt coverage.

## Controls

Dialog schemas use the canonical controls from `ADMIN-CONTROL-CONTRACT.md`. A dialog does not get a separate form design language.

## Responsive behavior

Width modifiers are desktop maxima, not fixed mobile widths. On narrow viewports the shared contract reduces the viewport gutter and lets the dialog fit the available screen. Long content scrolls inside the native modal behavior; page-local horizontal compensation is forbidden.
