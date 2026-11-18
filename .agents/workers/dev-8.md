# Dev Worker 8 — Shared Admin dialog presentation

**Branch:** `dev`  
**Lease:** 15 (active)  
**Status:** Admin root, Sidebar, dialog and Stage scrollbar presentation corrected; legacy dialog thumb and Storage gutter styling retired. Browser acceptance pending.

## Ownership and user decisions
- Own shared Admin dialog window presentation, semantic grouping rules and the Stage-to-Toolbar completeness contract.
- Keep native Filament dialog semantics and the existing `AdminDialog`, `AdminControl`, `AdminForm::section()` and shared CSS owners. No second dialog or control family.
- Use the same restrained 0.25rem corner radius for blocking dialogs and transient Admin popovers via one shared theme token; popup scrollbars are visually hidden without disabling scroll or touching blocking-modal scroll-lock.
- Keep existing dialog backgrounds unchanged. The user's possible future color change concerns Dark Mode specifically, and is explicitly deferred.
- Show all eligible available Storage assets from the shared `MediaAssetSelect` in identifier and relationship selectors, sorted newest first without search and by filename when searching. Browser-native preview lazy loading remains; a true paged/infinite-scroll transport is not part of this change.
- Every Stage operation must have a corresponding Toolbar/Settings/Add/Manage path. Pure visualization may remain supplementary. Do not implement another domain mutation owner.
- Avoid editing domain-specific dialog schemas owned by active Dev Workers 1–7 without reviewing their concurrent changes and coordinating scope. Do not edit tests, trigger CI, deploy or change other branches.

## Inspected architecture and source findings
- Read current `AGENTS.md`, `.agents/README.md`, Dev skill, branch roster, `docs/ARCHITECTURE.md`, Admin UI, Dialog, Controls, Responsive and Browser-review contracts.
- Read current `AdminDialog` semantic adapter (Create, Edit, Command, Confirm, Viewer), global `AdminControl::register()`, `AdminForm::section()`, shared dialog/control/form styles and modal-geometry script.
- Reviewed dialog callers and schemas across General, Dashboard, Activity, Pages, Gallery, Home, Journal, Custom Page and Storage and related Resource classes.
- Shared Action dialogs consistently use the central adapter in reviewed source; Filament-owned authentication/security dialogs retain native workflow. This is source inspection, not rendered-browser proof.
- Grouping is incomplete: Home and Pages Settings are largely flat grids; Blog uses named sections while Exhibition's large schema is flatter; Storage uses grouped rights and metadata sections.
- Former source-rule violations: one persistent helperText in `JournalWorkspace::journalSettingsAction()` and five in `ListMediaAssets::mediaEditSchema()`. User explicitly requested removing these six visible descriptions; the localized fields and existing Storage `AdminHelp` hints remain intact.
- Stage/Toolbar functional parity is a new required acceptance gate. No comprehensive runtime/browser parity check has been performed.

## Change scope
- Set the shared native Filament modal window radius in `resources/css/admin/dialog-contract.css`. Do not alter vendor lifecycle/scroll lock, native close control, form border radii or dialog motion.
- Update the existing normative Admin Dialog content layout and Admin UI Stage contracts at the user's explicit request. No separate documents or policy aliases.
- Removed the six persistent helper-text descriptions as a narrowly authorized user change. No unrelated domain logic, existing optional Storage help hints, settings colors or dialog behavior was modified.
- Further semantic grouping will be handled dialog-by-dialog with the user after visual review; do not claim that all forms have been regrouped.

## Popup and Storage media-selection follow-up
- New shared presentation source: `resources/css/admin/primitives.css` provides `--admin-overlay-corner-radius`; `dialog-contract.css`, `controls.css` and `forms.css` apply it to modal windows, Filament Select/dropdown/date/color/floating panels, shared partial-date and Rich Text tool panels and Help popovers.
- `resources/css/admin/controls.css` centrally hides the visible scrollbar for popup scrolling surfaces while preserving their `overflow` and keyboard/focus handling. Ordinary modal-internal scrollbars remain visible under their separate accepted contract.
- `MediaAssetSelect::searchOptions()` no longer hard-limits 24 initial or 30 search results; `make()` raises Filament's relationship preload cap to its integer maximum. It still constrains `state=available`, preserves supplied media-type and custom query predicates, and keeps the existing preview markup with `loading="lazy"`.
- The Rich Text `RichTextMediaPicker` child, secure selection payload and image insertion/persistence flows are untouched; no parallel media-picker implementation was added.
- Server-side option pagination is not implemented in the native Filament Select; full result lists may be costly for large libraries, so benchmark with the real artist Storage dataset before claiming performance acceptance.
- Updated the two canonical documents on shared control behavior and Rich Text media selection in place.

## Public scrollbar geometry (2026-11-18)
- User requested eliminating Public header/content/navigation shifts when desktop scrollbars appear/disappear, **without** permanent reserved gutters or extra scrollbar systems. Only Public is in scope now; defer all Admin scrollbar/dialog lifecycle changes.
- Diagnosed actual Public root in `resources/css/app.css`, which owns document scroll and sticky header. Dynamic classic scrollbar width changes the root content width; `public-navigation.js` then legitimately refits menu arrows on the changed rail width. The map and fullscreen viewer separately used `scrollbar-gutter: stable` on the locked root.
- Corrected the native Public root: hide only its visible scrollbar using `scrollbar-width: none` and `::-webkit-scrollbar`, retaining root scrolling. This removes gutter reflow without adding a scroll state, custom overlay, body-width compensation, Javascript event handler or fixed padding. No visible thumb is available for mouse dragging; wheel, touch, keyboard and anchor scrolling must be checked in the browser.
- Consolidated modal root overflow locking for the native exhibition map and fullscreen Artwork Viewer in `app.css` and removed their now-obsolete gutter reservation. Eliminated the redundant Preview-touch-only scrollbar styling and old viewer lock from `public-presentation.css`. The rest of the Public layout, menu window algorithm, Artwork Viewer and modal lifecycles are untouched.
- Direct consumers: public routes (Home, Gallery, Artwork, Exhibitions, Blog, Custom Pages), narrow/desktop and embedded Public Preview, native exhibition-map dialog, fullscreen Artwork Viewer. No Admin asset was modified.
- Browser acceptance to verify with two same-width Public routes of different lengths and an image-heavy route: identical Main/Menu/chevron x coordinates on overflow transition; native keyboard/wheel/touch scrolling and sticky header; no blank scrollbar gutter; opening/closing fullscreen Viewer and exhibition map at original scroll position; Preview desktop vs touch iframe; no sideways overflow and no jumping on Livewire navigation. No such browser run was available in this connector-only source task.
- A documentation-only follow-up for the Docs owner is needed: `docs/public/site.md` still describes a visible native root scrollbar and reserved Viewer gutter. Dev is not authorized to rewrite general manuals as part of this change; hand off after user browser review.

## Admin scrollbar stability (2026-11-18)
- The user accepts hidden native scrollbars on Public for now, requests the same for Admin with special attention to dialog stability and removal of obsolete code. Public scrollbar preference controls are deferred, not implemented.
- Reviewed shared Admin layout, dialog, Stage, Media styles, dialog positioning JavaScript, the pinned/version-guarded Filament scroll-lock patch and its existing targeted regression checks.
- The Admin root `html.fi` and Sidebar `.fi-sidebar-nav` now hide scrollbar chrome without changing native document/sidebar scroll ownership or creating a permanent gutter. Existing auto-gutter reset remains authoritative.
- `dialog-contract.css` replaces obsolete thin scrollbar thumb/track decoration with a hidden native modal-content scrollbar; wheel, touch and keyboard scrolling remain intended, with Filament still owning focus, Escape, background lock and restoration.
- Storage deletion reference scroll areas no longer reserve a stable scrollbar gutter. Shared Visual Stage panes hide their own scrollbar chrome; preexisting Gallery/Home/Storage feature scroll styles, table geometry and transient popover behavior were left unchanged.
- The Filament Support patch is **retained intentionally**, including nested and long/short document locks, until real browser testing confirms any alteration is safe. It is not a redundant second scroll implementation and was not modified or replaced by event listeners, body padding or fixed layout offsets.
- Required browser acceptance (not run): long and short pages, scroll at nonzero position, dialog open/change/autosave/close/animation, nested Select/Rich Text, backdrop wheel block, Sidebar and Stage inner scrolling, Storage references, Firefox and Chromium. Check unchanged `documentElement.clientWidth`, modal coordinates and exact restored `scrollY`.
- The existing role-wide contract and Admin Dialog documentation still describe *visible* scrollbars. Today's newer explicit user instruction overrides this presentation detail. Docs/Testing should reconcile after browser review; Dev does not edit general docs or tests.
- User-selectable Public scrollbar modes are intentionally unresolved.

## Verification and next actions
- Source review: confirmed the central dialog window radius remains `.25rem` in `dialog-contract.css`, and checked current affected dialog source. Existing modal header actions intentionally remain round; no change requested to action buttons.
- Verify published changed files at the committed HEAD.
- Local build, screenshot/browser review, large-library performance, popup scrolling/keyboard behavior, Rich Text image insertion and user visual acceptance remain unverified.
- Review the small-radius dialog in Light/Dark and native account/security dialogs before changing any further shared appearance.
- Discuss a distinct restrained dialog surface color rather than silently forcing one before user selection.
