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

Pages and Home share one `PageHeadingFields` schema for the optional public
content heading. Each heading field persists once through Filament's
`afterStateUpdated` callback to `SiteSectionEditorialService`: toggles
and selects on change, text on blur. The shared field owner emits the
canonical `AdminNotifier` success event only when that persisted field
actually changed. The general Edit action must not save heading fields a
second time: its modal-level `wire:change` remains solely for other fields
and is not required for heading persistence or notification. The native
X never submits data. Heading state is separate from page identity/navigation,
disabled by default, and supports an optional 160-character override,
left/center/right alignment plus none/thin/subtle/strong/dotted divider styles.


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

**List entry exception:** In Custom Pages and the Home Custom / Under Construction
templates, List entries are edited within the owning component form through
the shared `ComponentListEditor` Repeater. Repeater add/remove/reorder buttons
change a draft collection and do not reliably emit the native `change` event
that commits ordinary Edit fields. List components therefore use the canonical
`AdminDialog::command()` with an explicit **Apply changes** action, committing
the entire component and ordered entries atomically through the existing
editorial service. Other components keep the normal autosaving Edit dialog.
Neither surface exposes standalone List child rows or Add List Item table
buttons. The shared List entry editor provides Entry, Context, Published, and
Details only, with no embedded-image insertion control. Legacy date/organisation/
location context and optional links are carried into the canonical Context/Details
fields on normalization. Type conversion continues through the existing confirmed conversion
Action; the List command must not mutate on close/cancel.

**Custom Page Contact:** All three Contact options and the optional Contact heading
are edited in the owning Contact component; there are no Contact child rows or
independent child Actions in Custom Page. The three visibility toggles default
on at creation. The Contact platform switches, social/form visibility
switches, Form presentation selector, optional status text and heading are
Filament/Livewire-owned controls: each Edit field uses `live()` plus its own
`afterStateUpdated` callback (text on blur), never the modal window's native
`wire:change` event as sole persistence. Every changed field rebuilds from
fresh persisted siblings and saves through `CustomPageEditorialService`,
including nested Contact inside Image, and sends one audited notification
only on a genuine mutation. An incomplete Under-construction form setting
remains in the mounted editor until its required status text is authored;
then both values commit atomically. Contact Create still collects a draft,
not premature writes. List entries continue to use the existing Command
Apply workflow. The public Contact heading can be overridden. Contact
and Legal Disclaimer admin Content each have a heading and a single
ellipsized summary line. Home Contact child editors use the same per-field
Livewire persistence for social selection, Form presentation and status,
using `HomePresentationEditorialService`; they do not clone the Custom
Page service.

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
- when a confirmation includes a description, the shared dialog header supplies bottom breathing room after the final line, even though Filament renders that description inside the header rather than the modal body; no consumer-specific spacing rules;
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

For the Journal (Blog and Exhibitions), Custom Page and Gallery Settings dialogs:
- use the same responsive `md` two-column form grammar as Pages; compact Settings stay at `Default` width without introducing special dialog geometry;
- resolve Journal identity (title, optional navigation label, slug redirect and synchronized uncustomized listing title) through the existing `SiteSectionIdentityService`, and its non-destructive template switch through `SiteSectionEditorialService` just as Pages Edit does;
- a blank navigation label resolves to the page title, as for Custom Pages; neither validation nor presentation should require a redundant copy of the title;
- Custom Page navigation parent follows the global nested-navigation policy from Pages Settings, including domain validation; and
- do not add explanatory helper text to settings fields when the label is self-explanatory (notably URL/redirect narration and Material presets).

For Gallery and Journal editorial dialogs, the contract is explicitly shared:

- Gallery Add Artwork and Edit Artwork consume the same `ArtworkEditDialog::schema()` field owner, with create-only slug uniqueness; sections are Basics, Dimensions, Content, Media, and Presentation. The Create Action commits once; Edit keeps native change-event autosave.
- Gallery Edit header actions use Move up/down, Publish/Unpublish, and Remove (detach, not delete). The Remove and Unpublish actions must keep their existing confirmations. Publication and reordering reuse the same domain/action owners as the Gallery table.
- Gallery Materials is a `Default`-width **Command** with `Apply changes` in the header, not an autosaving Edit: Repeater additions/removals form one draft collection saved atomically through `ArtworkMaterialPresetService::sync()`. Closing with X discards changes; a no-op has no notification.
- Journal Add Post and Add Exhibition are `Large` Creates that commit once. Their Edit counterparts retain `Large` autosave and the shared `JournalEntryEditorSchema` sections. Two/six-column groups must collapse to one column on narrow screens.
- Journal Edit Post header actions follow the same semantic order and state rules as row actions: Move up/down, Publish/Unpublish/Cancel schedule, Schedule, Archive/Restore, Delete. Exhibition Edit has Move up/down, Publish/Unpublish, Delete. Schedule and Delete open their existing Command/Confirm actions; the editors must not create another persistence route or weaken publication prerequisites.

For media/detail viewers:

- keep the primary visual first;
- place related metadata/detail sections side by side beneath the visual when two meaningful groups exist;
- collapse those sections to one column on narrow viewports;
- text/detail viewers use the shared `admin-detail-dialog` / `admin-detail-dialog__meta` presentation owned by `dialog-contract.css`; feature CSS owns only domain-specific inner content;
- every Viewer keeps the shared visible dialog heading. If the heading already carries the record identity, do not repeat that same title/change as the first content field; detail content starts with genuinely additional metadata.

Compact summary/fact cells are optional inside dialogs. They are a layout device, not a requirement and not necessarily numerical metrics. Render only meaningful facts, and use exactly as many cells as the content and available width justify; never manufacture or pad a dialog to a fixed metric count.

The shared `admin-detail-dialog__meta` uses a three-column default for
existing detail viewers. When a Viewer has **exactly four meaningful facts**,
apply the centrally owned `admin-detail-dialog__meta--four` variant.
It uses four equal cells at the existing `Large` dialog width, two at reduced
content width and one on narrow screens; all borders, cell padding and
responsive breakpoints are defined in `dialog-contract.css`. No individual
Viewer may synthesize its own grid or expand beyond its assigned
`AdminDialogSize`.

The Dashboard feed Viewer uses `Large` and the four-fact variant:
Announcements/Changelogs show Type, Date, Sender, Status (their source
has no meaningful time-of-day); Notifications show Type, Date, Time, Status;
Contact shows Type, Date, Time, Sender, while delivery/read information
remains in its separate factual context line. A Reference link belongs to
its existing action/link field rather than becoming a fifth fact cell.

## Chrome and actions

The native Filament close control is always the final top-right control. Filament continues to own modal state, focus trapping and Escape behavior.

Non-destructive Edit header actions (Move up/down, Publish/Unpublish,
Archive/Restore and equivalent state changes) must never dismiss the parent
Edit dialog. The shared `AdminDialog::headerAction` owns their Filament
`after()` Halt lifecycle and forces a complete background workspace render;
feature code persists through the same row domain action, then refreshes
the mounted editor by its current stable target. The previous position,
state and disabled edge controls must update immediately; move buttons
disable at the first/last sibling, and repeated boundary clicks do not
produce audit records or success feedback. Switching to an explicit
confirmation/command may temporarily replace the editor, but successful
Gallery Unpublish and Journal scheduling launched from an Edit header
return to the updated editor. Standalone row actions retain their usual
completion semantics. Actual Delete and native X close as before.
No global modal event hijack, extra timer, duplicate action executor or
independent page-local dialog lifecycle may implement these rules.

Contextual actions are native Filament Actions lifted into the shared header rail. The shared adapter explicitly keeps modal and footer-action alignment at `Start`. Confirmation dialogs deliberately remain normal Filament modals so Filament cannot introduce a second confirmation-specific width, alignment, warning-icon, alert-role or footer-layout branch underneath the shared adapter.


- the heading starts at the canonical content inset and occupies the same first-row vertical axis as the header actions;
- `X` is always at the far right;
- contextual actions sit immediately to its left in semantic order;
- header reserve is derived from the number of actual header actions. Do not reserve a fixed empty action rail;
- task headings are concise noun/action phrases, normally one or two words (`Edit file`, `Delete page?`, `Page settings`). Viewer headings may use the record title when identity is the point of the dialog;
- descriptions are exceptional: keep them only for a material consequence, ambiguity or safety rule that the controls themselves do not communicate. Do not restate the heading or narrate obvious form behavior;
- use `AdminDialog::headerAction()` with the canonical `AdminRowAction` role for row-mirroring Edit/Viewer icons; domain commands and their confirmation semantics remain with their existing owners;
- keep available contextual Edit actions in the same semantic order as the table (Move up, Move down, publication/workflow, Delete); do not duplicate the Edit action inside its own dialog or add inapplicable actions as placeholders;
- changing a row's position while its Edit dialog is open must refresh the mounted target, never keep an outdated array index;
- use `admin-dialog__header-action` and canonical `AdminIcon` entries;
- commit actions use the primary treatment;
- destructive actions use the danger treatment;
- never use local SVGs or emoji;
- never add a redundant bottom action row.

## Edit persistence and Undo

Edit autosave must follow `ADMIN-CONTROL-CONTRACT.md`: discrete semantic changes only, never timer/debounce persistence.

`AdminDialog::edit()` routes discrete `wire:change` events through Filament's native `callMountedAction()` lifecycle (form validation, Action hooks and its database transaction). Its shared `after()` hook calls Filament's `Action::halt()` after successful persistence, committing the transaction while leaving the Edit modal mounted. This avoids a second project-owned Action executor. The native `X` only closes/unmounts the modal; the domain service remains responsible for comparing persisted values and recording actual changes in Activity/Audit. No separate `Save` or `Save and exit` path is added.

The native Filament `X` executes only `unmountAction()`. A text input's final browser `change` on blur remains an ordinary autosave event; suppressing that event would discard the last edit. Livewire 4.4 queues non-async Actions on the same component while another Action request is active, so edit persistence and native close require no second client-side close handler, custom lock, timeout or focus-interception path. The shared Edit `after()` hook only keeps the modal open. **Neither**
`AdminDialog::edit()`, a Domain save, nor the Audit context automatically
produces a Notification. The existing Action owner or an intentionally
shared mutation-feedback boundary must call `AdminNotifier` after verifying
an actual change. Dynamically visible fields using `afterStateUpdated`
may use that field boundary instead of the form Action, but not both.
The shared adapter does not install a second success/failure channel.

An Edit regression must prove the complete observable lifecycle: change
persists, Activity is accurate, one durable mailbox Notification and one
immediate `admin-header-notification` event are created on real change,
the dialog remains mounted, and close/reopen shows the value. No-op,
validation failure and a duplicate modal-level event cannot create a
success Notification. Test both form-level and field-level callbacks. See
`ADMIN-NOTIFICATION-CONTRACT.md` and `TESTING.md`.

Undo must extend the existing Activity/Audit receipt architecture. Do not create an independent dialog snapshot/rollback system. An Undo action may only be shown for mutations that have safe current receipts; applying Undo creates inverse editorial actions through the canonical `AdminUndoService` path.

## Exhibition map autosave

The Exhibition Map uses the ordinary Edit dialog and its semantic `change`
commit, not a separate confirmation or save path. The global Toggle bridge
is owned by `AdminControl`; its `map_enabled` instance is the same standard
field as `gallery_enabled`. `ExhibitionMapAddress` defines the single
eligibility rule for UI and domain: **Country plus City or Postal code**.
A map that is already on can always be switched off. The editorial service
geocodes only when the saved address changes or coordinates are missing and
rejects invalid or unavailable locations atomically.

Map preview coordinates are read-only form state: the Exhibition Edit Action
writes committed latitude/longitude/geocoded_at back to the mounted schema
after the domain returns, without replacing other editor fields. Do not
reintroduce Alpine `stale` flags, global events, extra Save buttons or
asynchronous geocoding timers. A partial address and a temporarily
unavailable geocoder must not publish an incorrect map.

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

Opening or closing a blocking admin task dialog keeps Filament as the sole lifecycle and document-scroll-lock owner. The pinned `filament/support` v5.7.6 bundle is corrected **at build time** by `scripts/patch-filament-scroll-lock.php` before `filament:upgrade` publishes the asset; the runtime retains one Filament lock counter. The lock preserves a preexisting native vertical scrollbar without reserving space on short pages. Its existing Filament lock counter has two legitimate states based on the document at first acquire: on a scrollable page, the body is frozen while root scrollbar width and scroll position are retained; on a short page, only root overflow is hidden, because there is no background document scroll range to freeze. In particular **never set body position, min-height, or a forced scrollbar on short pages**: detaching and restoring the body produces a brief scrollbar flash during modal close. All touched inline styles and the original scroll position are restored on final unlock. The patch fails loudly for an unreviewed Filament version and never adds a second Alpine modal or application-level global scroll interceptor.

Filament owns: modal state, background lock, focus trapping, Escape, native X, animation, destroy and lock cleanup remain framework-owned. The shared dialog-geometry companion may only measure the current Main frame, apply placement variables to the persistent HTML root and reset dialog-internal scroll on the actual `x-modal-opened` transition. The Filament modal's frame CSS is declarative and cannot disappear when Livewire replaces the modal element after a field change. Its native backdrop blocks wheel/touch overscroll chaining into the still-visible document scrollbar. It must not remeasure on Filament's `open-modal` signal, which can be repeated when a mounted Action updates without closing. It must not call `window.scrollTo()`, change document overflow/padding, install global wheel/touch/key scroll interception, preserve a second lock counter or add a modal-lock class. The native window remains in Filament's grid row 2, but the shared admin dialog frame fixes row 1 at zero and lets the final row absorb unused height. Conditional fields therefore change the window height without changing its top position. The outer modal container is positioning geometry only; `.fi-modal-content` is the sole dialog-internal scroller. Page-local scroll-lock workarounds are forbidden.
