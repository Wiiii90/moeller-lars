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

## Backdrop, measured frame and focus placement

The **blocking backdrop** covers the complete application viewport, including the Topbar and Sidebar. Light and Dark may use different scrim opacity, but **not** different modal/backdrop geometry. The actual dialog window is bounded independently to the current admin content frame **below the topbar**, including the frame's canonical inline padding, and remains horizontally centered in the available measured frame.

The dialog's position must derive from the **actual post-sidebar Main/document scrollport**, including its native scrollbar width. Never translate the `.fi-main` ancestor or use viewport-width (`vw`) compensation to guess the dialog center: transformed ancestors would capture Filament's fixed modal layer and make its positioning depend on document scroll. Adding/removing a classic vertical scrollbar must not shift the modal or create asymmetric side space.

Use the existing single `x-modal-opened` measurement owned by `admin-dialog-geometry.js` and persistent CSS variables, not an additional per-page layout process. Conditional form fields may alter height, not the top position. The dialog remains keyboard-reachable: native Escape, focus trap and restoration, nested popovers/select lists above the modal, scrollable lengthy content, and restoration of the original workspace state after close all remain required. These are part of the shared Filament modal lifecycle, not a reason for a second overlay implementation.

## Native full-viewport backdrop and nested picker boundary

The Filament **backdrop hit surface is full viewport**, including Topbar, Sidebar and the space outside the centered Admin content frame. Clicking anywhere on the actual backdrop is the native click-away action; the dialog's own framed scrollport and any legitimate nested popover must not be treated as backdrop. `AdminDialog`/Filament retain modal ownership, Escape, focus trapping, lifecycle and X. The shared backdrop/layout styles provide geometry, not another global click listener or nested modal manager.

The Rich Text Storage select/link/caption panels open **inside the same Edit dialog** with independent, shared popup dismissal; clicking outside the popup closes that popup without destroying the owning editor or committing a fake success. The visual image Select must use its native Filament selection-close lifecycle, not its editor shell as a large click-away region. Source rechecked at `dev@74e629274ac2`; the precise mobile/focus interaction still requires a real accepted browser run.

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
buttons. Type conversion continues through the existing confirmed conversion
Action; the List command must not mutate on close/cancel.

The shared List entries expose **Entry, Context, Published and Details**, not separate
Date/Organisation/Location/URL inputs or an embedded-image insert control.
`CustomPageSetting::listItemContext()` and `listItemDetails()` preserve
legacy authored data by folding old Context fields and optional HTTP(S)
links into the new representation.

**Custom Page Contact is a single parent editor.** Optional Heading
(default display `Contact`), Public Email, Social Media Links and
Contact Form are edited with the existing native-change Edit autosave;
all three toggles default on for Create. Dependent social-platform,
form-mode and status fields must preserve previously authored values
when hidden by a disabled toggle. Contact and Legal Disclaimer each
project a heading plus **one ellipsized content summary**; there are no
standalone Contact child rows, separate child Actions or child Selection
on Custom Page. Home's remaining child-row presentation is a distinct
editorial surface; use [the Table owner](tables.md#component-sequences)
for the current distinction.


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

Fields in editorial dialogs should follow the **actual editing task and related controls**, not the persistence schema's property order.

For multi-field `Large` create/edit dialogs:

- compose normal controls as a responsive two-column grid on desktop;
- collapse to one column on narrow viewports;
- long text, rich-text editors, repeaters, uploads/media pickers and content that genuinely needs width span both columns;
- keep related fields adjacent where practical (for example title/slug, start/end, width/height);
- do not remove, hide or truncate form fields merely to make a dialog shorter.

For the Journal (Blog and Exhibitions), Custom Page and Gallery Settings dialogs:

- use the same responsive `md` two-column form grammar as Pages; compact Settings stay at `Default` width without introducing special dialog geometry;
- resolve Journal identity (title, optional navigation label, slug redirect and synchronized uncustomized listing title) through the existing `SiteSectionIdentityService`, and its non-destructive template switch through `SiteSectionEditorialService` just as Pages Edit does;
- a blank navigation label resolves to the page title, and a previous label that merely repeated the previous title follows a title change; a separately customized navigation label remains independent;
- Custom Page navigation parent follows the global nested-navigation policy from Pages Settings, including domain validation; and
- do not add explanatory helper text to settings fields when the label is self-explanatory (notably URL/redirect narration and Material presets).

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
- use `AdminDialog::headerAction()` with the canonical `AdminRowAction` role for row-mirroring Edit/Viewer icons; domain commands and their confirmation semantics remain with their existing owners;
- keep available contextual Edit actions in the same semantic order as the table (Move up, Move down, publication/workflow, Delete); do not duplicate the Edit action inside its own dialog or add inapplicable actions as placeholders;
- changing a row's position while its Edit dialog is open must refresh the mounted target, never keep an outdated array index;
- use `admin-dialog__header-action` and canonical `AdminIcon` entries;
- commit actions use the primary treatment;
- destructive actions use the danger treatment;
- never use local SVGs or emoji;
- never add a redundant bottom action row.

## Shared mounted-action persistence boundary (current dev)

The ordinary Edit action, eligible custom Selects and non-native date picker changes now share the **same** `AdminDialog::edit()` mounted-action persistence boundary. The shared `AdminControl` adapter bridges only controls that need it; Filament Select's native change/close lifecycle is otherwise untouched. This eliminates page-local second save actions and preserves the mounted modal while the transaction succeeds. No-op, failed validation and closing via X or click-away must never produce duplicate success/audit/Notification records. Exhibition media table edits and visibility icons use their own canonical domain change detection and the same audited editorial transaction policy. These are source-reviewed at `dev@671d8214e060`, not a new browser pass.

## Edit persistence and Undo

Edit autosave must follow `docs/admin/controls.md`: discrete semantic changes only, never timer/debounce persistence.

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
`docs/admin/notifications.md` and `docs/operations/testing.md`.

Undo must extend the existing Activity/Audit receipt architecture. Do not create an independent dialog snapshot/rollback system. An Undo action may only be shown for mutations that have safe current receipts; applying Undo creates inverse editorial actions through the canonical `AdminUndoService` path.

## Pages and General Social Media dialog identities

These examples apply the existing shared Create/Edit Action lifecycle; they do not introduce a page-specific persistence engine.

- **General · Add profile:** `General::addSocialLinkAction()` uses a `Small` `AdminDialog::create()` with required Platform, Profile URL and Position. It creates only on the default checkmark commit; the Create form retains required markers.
- **General · Edit profile:** `General::editSocialLinkAction()` uses a `Small` `AdminDialog::edit()` for those fields, without a Save/Commit footer or red required markers. Each valid semantic change follows the shared autosave path; an invalid change must leave the last persisted profile intact. **The mounted editor is identified by the unique persisted platform**, not by its mutable row index. After platform change the owner updates this identity; Move up/down remounts the action against its newly located record. The Social table's `wire:key` also uses the stable platform value. Never substitute a positional row identity or create a second social editor.
- **Pages · Settings:** `ManagesSitePagesSettings::pagesSettingsAction()` uses the shared `AdminDialog::edit()` and `PagesSettingsDialog` (Default size). Its Toggle/Select fields persist through discrete `afterStateUpdated`/semantic change events using the one canonical `PagesSettingsDialog`, `HomeRoutingSettingsService`, `AdminSettingsService` and `SiteSectionOrderService` transaction path; no independent Save/Commit footer or redundant persistence. Native `X` closes without an extra write. Turning **Allow nested pages** off when children exist requires the accepted warning before the irreversible flatten operation; re-enabling nesting does not reconstruct previous parent relations. The Pages variant omits redundant Home routing helper copy while Home retains its own descriptions. See [Pages settings implementation](../../app/Filament/Support/PagesSettingsDialog.php) and [mounting Action](../../app/Filament/Pages/Concerns/ManagesSitePagesSettings.php); actual browser acceptances were not re-executed in this documentation pass.
- **Pages · Add page:** `ManagesSitePageCreateDialog::addPageAction()` is a full Create dialog; its submit uses the canonical `AdminIcon::Pages` instead of the generic checkmark. Other Create tasks retain their normal shared confirmation icon.
- **Pages · Edit page:** `ManagesSitePageEditDialog::editPageAction()` uses `AdminDialog::edit()`. Its save comparison operates on the **latest persisted Site Section**, not the original modal form-fill snapshot. Stable Site Section IDs protect mounted Edit actions when row order changes. **Offene quellbelegte Identity-Abweichung:** Bei getrenntem Seitentitel und individuell gesetztem Navigation Label wird das Präsentationslabel als Edit-`name` verwendet und kann beim Speichern den echten Titel überschreiben; siehe [Pages-Identity-Audit](../architecture/ui-surfaces.md#pages--edit-titel-navigation-label-und-no-op-statischer-negativpfad). Dies ist kein als korrekt abgenommenes Verhalten.

**Source:** [General actions](../../app/Filament/Pages/General.php), [Social Blade stable keys](../../resources/views/filament/schemas/components/general-social-links.blade.php), [Pages Create](../../app/Filament/Pages/Concerns/ManagesSitePageCreateDialog.php) and [Pages Edit](../../app/Filament/Pages/Concerns/ManagesSitePageEditDialog.php). Actual browser interaction was not re-executed for this documentation change.

## Journal Blog/Exhibitions Edit-Aktionen

[`JournalWorkspace::editPostAction()`](../../app/Filament/Pages/JournalWorkspace.php) und `editExhibitionAction()` verwenden nun dieselbe `journalEditHeaderActions()`-Projektion, die für jede Aktion eine **persistierte Entry-ID** trägt und bestehende Row-/Domain-Mutationen wiederverwendet. Nach Move up/down wird der aktuelle Edit-Dialog auf diese ID remountet, statt einen veralteten Tabellenindex beizubehalten. Die Reihenfolge folgt den fachlich verfügbaren Row-Rollen:

- **Blog:** Move up/down, zustandsabhängig Publish/Unpublish/Cancel Schedule, Schedule, Archive/Restore und Delete. Veröffentlicht oder geplant blockiert Delete; archiviert blockiert Publication; geplant blockiert Archive. Zeitplanung verwendet die bestehende Schedule-Action statt eines zweiten Dialogs.
- **Exhibitions:** Move up/down, Publish/Unpublish und Delete; veröffentlicht blockiert Delete. Domain-Commands bleiben in `JournalWorkspace`, Edit-Autosave ausschließlich im nativen Edit-Action-Owner.

Blog- und Exhibition-Create-Actions bleiben `Large`-Create-Tasks mit genau einem expliziten Commit. Schedule und Delete wechseln zu den vorhandenen Command-/Confirm-Dialogen, statt direkt im offenen Edit-Formular eine zweite Persistenzroute aufzubauen.

**Aktuelle Schema-Zuständigkeit (Quellabgleich `dev@dee05e4d`):** [`JournalEntryEditorSchema::entry()`](../../app/Filament/Support/JournalEntryEditorSchema.php) baut **Blog** mit bestehenden Basics-/Text-/Gallery-Sektionen auf, **Exhibitions** dagegen als **flaches** responsives Formular: Identität, Beginn/Ende, Cover, Viewer, Organizer/Ort, Rich Text, Gallery und Map. Es gibt **keine** zusätzlichen Basics-/Venue-/Dates-/Map-Section-Wrapper für Exhibitions. Beide benutzen dieselben nativen Filament-Create/Edit-Action-Lebenszyklen; fachliche Sichtbarkeitsfelder, Reihenfolge und Gallery-Tabelle gehören in [Exhibition Editorial](../content/exhibitions.md). Ein Quellvergleich belegt keine abgeschlossene Browser- oder konkurrierende Zwei-Fenster-Prüfung.

## Gallery Artwork edit/create und Material-Presets

Die Gallery verwendet [`ArtworkEditDialog::schema()`](../../app/Filament/Support/ArtworkEditDialog.php) für **beide** Artwork-Formulare. [`GalleryWorkspaceFormSupport::artworkCreateFormSchema()`](../../app/Filament/Pages/Concerns/GalleryWorkspaceFormSupport.php) ruft denselben Schema-Owner mit `creating: true` auf; die Create-Variante prüft Slug-Eindeutigkeit vor dem Anlegen. Edit nutzt dieselbe fachlich gruppierte Basics-/Dimensions-/Content-/Media-/Presentation-Formstruktur. Die Create-/Edit-Actions bleiben unterschiedliche Lebenszyklen (Create mit explizitem Commit, Edit mit native Discrete-Change-Autosave); die gemeinsame Felddefinition ist kein zweiter Persistenz-Owner.

Der Gallery-Edit-Header verwendet [`artworkEditHeaderActions()`](../../app/Filament/Pages/Concerns/GalleryWorkspaceArtworkDialogs.php): Move up/down remountet gegen die persistierte Artwork-ID, Publish/Unpublish benutzt denselben fachlichen Publication-Pfad wie die Row-Actions (Unpublish über die bestehende Bestätigung), und **Remove bedeutet Detach aus der Gallery**, nicht Löschen des Artwork-Datensatzes oder seiner Mediendateien. Remove ist bei veröffentlichtem Artwork deaktiviert. Erfolgsmeldung/Refresh werden am existierenden Mutation-Owner veranlasst; rein statisches Lesen ist **keine** Bestätigung des vollständigen Livewire-Header-Action-Flows.

Die Materials-Settings-`presets` sind eine **Repeater-Collection**. [`materialPresetsAction()`](../../app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php) verwendet deshalb `AdminDialog::command(..., 'Apply changes', Default)`: Reorder/Add/Delete innerhalb dieser Liste sind Entwurfszustand bis zum expliziten Commit, **nicht** einzelne Edit-`wire:change`-Persistenzen. `X` verwirft den noch nicht committed Repeater-Entwurf. No-op und Validierungsfehler dürfen keine unzutreffende Erfolgsbenachrichtigung erhalten. Für diesen Docs-Abgleich wurde kein Gallery-Browser- oder Featuretest ausgeführt. Die frühere separate Architecture-Testsuite ist kein aktueller Test-Owner auf `dev`; die [Testing-Strategie](../operations/testing.md) besitzt die weitere Verifikation.

## Exhibition map autosave

Die Exhibition-Karte verwendet denselben `AdminDialog::edit()`-Änderungspfad wie andere Felder, ohne zweite Save-Action, Timer oder konkurrierenden `->live()`-Transport. Das Ein- und Ausschalten, atomare Geocoding-Fehler, Koordinaten-Projektion und die Rücksetzung eines abgelehnten Toggles werden in der **einzigen** [Exhibition-Map-Architektur](../architecture/exhibition-map.md) erläutert. Dieses Dialogdokument besitzt nur den gemeinsamen Formular-/Modal-Lifecycle.

## Controls

Dialog schemas use the canonical controls from `docs/admin/controls.md`. A dialog does not get a separate form design language. The panel-wide `AdminControl::register()` adapter means ordinary Filament TextInput/Select/Checkbox/Toggle/etc. instances still receive the canonical wrapper and geometry; specialized editors continue to use their named canonical control components.

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

**Accepted regression evidence and upgrade gate (2026-10-11):**
- Preserve the long-page, short-page and nested-dialog cases in
  [`tests/js/filament-scroll-lock-patch.test.mjs`](../../tests/js/filament-scroll-lock-patch.test.mjs)
  and the currently authored browser cases in
  [`dialog-lifecycle.spec.mjs`](../../tests/browser/e2e/admin/dialog-lifecycle.spec.mjs) and [`dialog-scroll-lock.spec.mjs`](../../tests/browser/e2e/admin/dialog-scroll-lock.spec.mjs).
  Existing browser acceptance is a protected input; reading or running a
  source test alone does not repeat that acceptance.
- `composer.json` applies [`scripts/patch-filament-scroll-lock.php`](../../scripts/patch-filament-scroll-lock.php)
  before `filament:upgrade` publishes vendor assets. The production Docker
  image must contain the patched build output. Filament Support upgrades
  require deliberate review of the patch's version guard and lifecycle.
  The failed-fast version check must not be silently removed.
- Do not replace the accepted long/short behavior with upstream
  `overflow: hidden`, `scrollbar-gutter: stable`, permanent body padding,
  synthetic short-page scrollbars or independent application lock counters.
  Validate no scrollbar flash through the actual close/leave animation.

Opening or closing a blocking admin task dialog keeps Filament as the sole lifecycle and document-scroll-lock owner. The pinned `filament/support` v5.7.6 bundle is corrected **at build time** by `scripts/patch-filament-scroll-lock.php` before `filament:upgrade` publishes the asset; the runtime retains one Filament lock counter. The lock preserves a preexisting native vertical scrollbar without reserving space on short pages. Its existing Filament lock counter has two legitimate states based on the document at first acquire: on a scrollable page, the body is frozen while root scrollbar width and scroll position are retained; on a short page, only root overflow is hidden, because there is no background document scroll range to freeze. In particular **never set body position, min-height, or a forced scrollbar on short pages**: detaching and restoring the body produces a brief scrollbar flash during modal close. All touched inline styles and the original scroll position are restored on final unlock. The patch fails loudly for an unreviewed Filament version and never adds a second Alpine modal or application-level global scroll interceptor.

Filament owns: modal state, background lock, focus trapping, Escape, native X, animation, destroy and lock cleanup remain framework-owned. The shared dialog-geometry companion may only measure the current Main frame, apply placement variables to the persistent HTML root and reset dialog-internal scroll on the actual `x-modal-opened` transition. The Filament modal's frame CSS is declarative and cannot disappear when Livewire replaces the modal element after a field change. Its native backdrop blocks wheel/touch overscroll chaining into the still-visible document scrollbar. It must not remeasure on Filament's `open-modal` signal, which can be repeated when a mounted Action updates without closing. It must not call `window.scrollTo()`, change document overflow/padding, install global wheel/touch/key scroll interception, preserve a second lock counter or add a modal-lock class. The native window remains in Filament's grid row 2, but the shared admin dialog frame fixes row 1 at zero and lets the final row absorb unused height. Conditional fields therefore change the window height without changing its top position. The outer modal container is positioning geometry only; `.fi-modal-content` is the sole dialog-internal scroller. Page-local scroll-lock workarounds are forbidden.

## Header Action continuity in current editors

At reviewed `dev@434975e1bd09`, non-destructive editor header Actions (such as Publish/Unpublish and Move) retain the active Filament edit modal when supported, and refresh its editorial projection instead of silently closing an unfinished editor. Gallery explicit Upload/Edit and artwork Unpublish have their own navigation/projection semantics; the published primary asset may not be silently detached. The framework retains sole modal, focus and scroll-lock ownership. Source inspection is not browser acceptance; regressions for nested Contact autosave, dialog closure, stale editor identity and no-op Audit/Notification remain eligible for direct-`dev` tests.

