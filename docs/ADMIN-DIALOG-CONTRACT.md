# Admin dialog contract

Admin dialogs are native Filament Action modals with one shared presentation and lifecycle contract. Project-owned dialog semantics live in `App\Filament\Support\Dialogs\AdminDialog`; shared frame/chrome live in `resources/css/admin/dialog-contract.css` and `resources/js/admin-dialog-geometry.js`. Framework-owned blocking subdialogs (for example Filament MFA setup/recovery flows) keep their vendor workflow semantics but adopt the same measured admin content frame and globally registered control grammar. Do not copy those vendor workflows into a second project implementation.

Do not create page-local modal families, custom close behavior or footer button systems. Do not use Livewire `wire:confirm` or browser-native confirmation prompts in the admin; confirmations go through `AdminDialog::confirm()`.

## Placement and naming

Keep application-level dialog terminology separate from Filament's native modal API:

- shared dialog infrastructure lives under `app/Filament/Support/Dialogs`;
- page and resource code consumes that shared infrastructure instead of creating local modal families;
- concerns whose primary responsibility is exposing dialog actions use the `...Dialogs` suffix; `...Modals` is legacy application terminology and must not be introduced;
- `PublicationStateControl` is Livewire state/event infrastructure, not a dialog, and therefore lives under `app/Livewire/Admin`;
- its Filament `BODY_END` mount wrapper is `resources/views/filament/partials/publication-state-control-hook.blade.php`, while the Livewire component view remains `resources/views/livewire/admin/publication-state-control.blade.php`.

References to Filament's own `modal*` methods or native modal behavior are expected where the framework API requires them. Application-level semantic names use dialog terminology.

## Dialog types

Exactly five semantic dialog types are supported:

### Edit

Edits operate on an existing record or settings object.

- editorial edit dialogs use the `Large` width by default;
- `Small` is for deliberately compact utility editors with one or a few simple controls;
- `Default` is the real medium edit width for bounded forms that need more room than `Small` but do not benefit from the four-unit editorial surface;
- explicit `Small` / `Default` / `Large` choices are preserved by the shared adapter; feature code must not simulate width with page-local CSS;
- changed values persist through the canonical discrete autosave path;
- no Save/Apply/Cancel footer exists;
- the native Filament `X` closes the dialog and does not trigger another write;
- text does not save per keystroke and no debounce timer is used;
- where the current edit session has reversible receipts, an Undo changes action may appear immediately left of `X`;
- required validation remains active, but Edit dialogs do not render red required-star decoration because persisted required fields enter the editor with a value;
- required selects do not expose a selectable null/"Select an option" branch. Optional semantic null values such as `Top level` remain explicit options/placeholders where the domain requires them.

Use `AdminDialog::edit()`.

### Create

Creates collect a complete new object before persistence.

- opening the dialog must not create an empty/placeholder record;
- compact create tasks may use `Small`;
- multi-field editorial create tasks use `Large` and the same responsive form composition as edit dialogs;
- the commit/check action creates the record atomically;
- `X` cancels the uncommitted create task.

Use `AdminDialog::create()`.

### Command

Commands collect parameters for a discrete existing-domain action such as Move, Schedule or Attach.

- no mutation occurs until commit;
- the commit/check action executes the command atomically;
- `X` cancels;
- one- or two-control commands normally use `Small`.

Use `AdminDialog::command()`.

### Confirm

Confirmation dialogs guard a concrete action without editable form state.

- all confirmations use the shared `Small` width; feature code does not override confirmation width;
- confirmation is an application-level semantic over a normal Filament Action modal; `requiresConfirmation()` is not used;
- the optional dialog condition controls whether the modal opens; when false, Filament executes the Action directly instead of opening a modal;
- the header confirmation icon executes the action;
- destructive confirmations use the danger treatment and canonical Delete icon;
- `X` cancels;
- no bottom action row.

Use `AdminDialog::confirm()`.

### Viewer

Viewers display detail/preview content without form persistence.

- no submit/cancel footer;
- contextual actions may appear immediately left of `X`;
- image/media viewers normally use `Large`;
- compact text-only details may use `Default`;
- `X` closes the viewer.

Use `AdminDialog::viewer()`.

## Widths

Dialogs align to the same six-unit desktop workspace used by the admin summary and table geometry. Use `AdminDialogSize` rather than page-local width values:

- `Small`: 2/6 (`26.667rem`) — compact commands and deliberately small utility forms;
- `Default`: 3/6 (`40rem`) — medium forms plus intermediate read-only/detail surfaces;
- `Large`: 4/6 (`53.333rem`) — genuinely wide editorial edit/create forms and media/detail viewers.

All sizes are desktop maxima. The measured admin content frame and its canonical inline padding provide the actual viewport-safe bound.

## Content layout

Width and content composition are separate concerns. A `Large` dialog must use its horizontal space instead of becoming a wide single-column scroll tunnel.

For multi-field `Large` create/edit dialogs:

- compose normal controls as a responsive two-column grid on desktop;
- collapse to one column on narrow viewports;
- long text, rich-text editors, repeaters, uploads/media pickers and content that genuinely needs width span both columns;
- keep related fields adjacent where practical (for example title/slug, start/end, width/height);
- do not remove, hide or truncate form fields merely to make a dialog shorter.

For media/detail viewers:

- keep the primary visual first;
- place related metadata/detail sections side by side beneath the visual when two meaningful groups exist;
- collapse those sections to one column on narrow viewports;
- text/detail viewers use the shared `admin-detail-dialog` / `admin-detail-dialog__meta` presentation owned by `dialog-contract.css`; feature CSS owns only domain-specific inner content;
- every Viewer keeps the shared visible dialog heading. If the heading already carries the record identity, do not repeat that same title/change as the first content field; detail content starts with genuinely additional metadata.

Compact summary/fact cells are optional inside dialogs. They are a layout device, not a requirement and not necessarily numerical metrics. Render only meaningful facts, and use exactly as many cells as the content and available width justify; never manufacture or pad a dialog to a fixed metric count.

## Chrome and actions

The native Filament close control is always the final top-right control. Filament continues to own modal state, focus trapping and Escape behavior.

Contextual actions are native Filament Actions lifted into the shared header rail. The shared adapter explicitly keeps modal and footer-action alignment at `Start`. Confirmation dialogs deliberately remain normal Filament modals so Filament cannot introduce a second confirmation-specific width, alignment, warning-icon, alert-role or footer-layout branch underneath the shared adapter.


- the heading starts at the canonical content inset and occupies the same first-row vertical axis as the header actions;
- `X` is always at the far right;
- contextual actions sit immediately to its left in semantic order;
- header reserve is derived from the number of actual header actions. Do not reserve a fixed empty action rail;
- task headings are concise noun/action phrases, normally one or two words (`Edit file`, `Delete page?`, `Page settings`). Viewer headings may use the record title when identity is the point of the dialog;
- descriptions are exceptional: keep them only for a material consequence, ambiguity or safety rule that the controls themselves do not communicate. Do not restate the heading or narrate obvious form behavior;
- use `admin-dialog__header-action` and canonical `AdminIcon` entries;
- commit actions use the primary treatment;
- destructive actions use the danger treatment;
- never use local SVGs or emoji;
- never add a redundant bottom action row.

## Edit persistence and Undo

Edit autosave must follow `ADMIN-CONTROL-CONTRACT.md`: discrete semantic changes only, never timer/debounce persistence.

`AdminDialog::edit()` attaches the shared native `wire:change` commit hook. Edit-dialog hosts that persist through Filament Actions use `InteractsWithAdminEditDialogAutosave`, which runs the existing Action lifecycle inside its database transaction and suppresses duplicate commits for an unchanged mounted action state. There is no atomic-submit compatibility helper or second edit persistence path.

Undo must extend the existing Activity/Audit receipt architecture. Do not create an independent dialog snapshot/rollback system. An Undo action may only be shown for mutations that have safe current receipts; applying Undo creates inverse editorial actions through the canonical `AdminUndoService` path.

## Controls

Dialog schemas use the canonical controls from `ADMIN-CONTROL-CONTRACT.md`. A dialog does not get a separate form design language. The panel-wide `AdminControl::register()` adapter means ordinary Filament TextInput/Select/Checkbox/Toggle/etc. instances still receive the canonical wrapper and geometry; specialized editors continue to use their named canonical control components.

## Motion

Admin dialog motion is shared rather than page-local. Standard task dialogs open and close on a short top-right-origin path aligned with the native close control:

- enter: 220ms, from scale(.985) with a slight up/right offset into the resting position;
- leave: 200ms, back toward that same top-right point;
- overlay: 260ms, so the dialog clears before the dimming fully disappears;
- reduced-motion preferences collapse the transform animation to an effectively immediate transition.

Do not add page-specific modal transforms, animation timings or alternate close trajectories.
## Scrolling and responsive behavior

Width modifiers are desktop maxima, not fixed mobile widths. On narrow viewports the shared contract reduces the viewport gutter and lets the dialog fit the available screen. Long content scrolls inside the native modal behavior; page-local horizontal compensation is forbidden.

Dialog-internal scrolling remains visibly discoverable. The shared contract styles the internal scrollbar as a narrow, low-contrast thumb with a transparent track; it must remain wheel, trackpad, touch and keyboard scrollable. Do not hide dialog scrollbars by default.

Opening or closing a blocking admin task dialog keeps Filament as the sole lifecycle and document-scroll-lock owner: modal state, background lock, focus trapping, Escape, native X, animation, destroy and lock cleanup remain framework-owned. The shared dialog-geometry companion may only measure the current Main frame, apply placement variables and reset dialog-internal scroll on open. It must not call `window.scrollTo()`, change document overflow/padding, install global wheel/touch/key scroll interception, preserve a second lock counter or add a modal-lock class. The outer modal container is positioning geometry only; `.fi-modal-content` is the sole dialog-internal scroller. Page-local scroll-lock workarounds are forbidden.
