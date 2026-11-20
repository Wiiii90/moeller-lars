# Dev Worker 8 — Shared Admin dialogs and media selection

**Branch:** `dev`
**Lease:** 30 (active; replacement registered 2026-11-21)
**State:** The shared Clear icon has been changed from Heroicons Backspace to one centrally registered eraser glyph. Browser appearance review is pending; no modal behavior or persistence was modified.

## Assigned ownership and canonical sources
- Central Filament Action modal lifecycle: `app/Filament/Support/Dialogs/AdminDialog.php`, `resources/js/admin-dialog-geometry.js` and `resources/css/admin/dialog-contract.css`. Preserve native focus, close/Escape, form validation, Create submission, Edit autosave, overlay stacking, scroll lock and nested picker interactions.
- Shared Create/toolbar icon grammar: `AdminIcon`, `AdminDialog::create()`, shared toolbar/row action components and their direct consumers.
- Shared partial-date/calendar inputs: `AdminPartialDateControl`, `admin-partial-date-control.blade.php`, `AdminControl` and `controls.css`. Media selection remains the central `MediaAssetSelect` / paginated `MediaAssetPicker` path.
- Coordinate shared controls and styling with other active Dev workers. Keep direct changes scoped to the dialog owner; no tests, general documentation, Continuous Integration or deployment without authorization.

## Accepted product decisions inherited from previous Dev Worker 8
- **Create forms:** Add/Create launchers use the plain canonical Plus icon before their label; their header Submit has a plain Check glyph inside the existing circular icon button, at the far right. The native X is immediately to its left, with optional Clear farther left. Submit validates and creates once; X/Escape/backdrop discards uncommitted Create state. Edit remains autosaving and retains its own action contract. Distinct Command/Confirm actions are not falsely reclassified as Create.
- **Shared Add affordances:** Search/toolbars use the canonical plain Plus for Add actions; the common Add-row fallback uses `AdminIcon::Add`, not a text plus. No extra circle or page-local icon copies.
- **Dialog frame:** The user rejected manual resizing; it and its grip, keyboard resize and width/height overrides were removed. Fine-pointer desktop users can deliberately drag a dialog by the header or use Alt+arrow when its header is focused. Untouched dialogs keep Filament's native horizontal starting geometry and are vertically centered inside the usable Admin Main frame, not anchored at its top. Preserve tall-form scrolling, viewport re-clamping only for moved dialogs, and reset offsets after closing. A lightly tinted header has one full-width thin bottom border.
- **Date controls:** Starts/Ends partial-date fields use the shared field-label decoration, same row/icon grammar as native Vernissage date-time, and only one open date popover at a time. Open at final measured trigger coordinates before becoming visible, avoiding top-left first-paint flash. Native Filament DateTimePicker and autosave remain authoritative. Shared calendar suffix applies only when a control has a date portion.
- **Media:** The common media picker loads an initial 30-file page on opening and server-paginates later scrolling/search, retaining selected-value resolution. Do not restore eager media loading.
- Do not introduce persistent helper prose below dialog controls; use the shared contextual AdminHelp when essential. Preserve the accepted Admin scrollbar/scroll-lock owner.

## Current change: Clear icon (2026-11-21)
- User chose an eraser for Clear after comparing the already-used Backspace and Paint Brush symbols. The existing Filament-owned Blade icon registry in `AdminPanelProvider` already maps `resources/svg/admin/*.svg` to the `admin-*` icon namespace; reuse that owner without installing a second icon library or adding dialog-local markup.
- Added `resources/svg/admin/eraser.svg` with a 24-unit, currentColor, 1.5-stroke outline matching existing custom icon treatment. Repointed only `AdminIcon::Clear` from `heroicon-o-backspace` to `admin-eraser`; `AdminDialog::committedTask()` retains the same native Filament Action and Clear callback.
- Static checks: inspected the shared Blade icon registry, central enum and Create dialog call site; existing custom glyph format and icon naming reused. Actual browser size, baseline, color and accessibility behavior have not been visually reviewed. No tests, Preview or deployment were run.

## Current verification boundary
- Previous holder reported central source changes and isolated JavaScript simulations/static checks, **not** browser acceptance. Current replacement changed only the centrally mapped Clear glyph as described above.
- Still unverified in a real browser: initial modal centering, moved-window clamping and keyboard drag; create button ordering and actual submit/cancel semantics in compact/wide dialogs; background-scroll restoration; light/dark header alignment; Starts/Ends switching and absence of initial flash; Vernissage icon/row alignment and actual date autosave; media picker nested interactions. Do not infer acceptance from source inspection.
- For future requests, reread fresh `dev` HEAD, current lease and the governing `docs/admin/dialogs.md` and scoped architecture before editing. Inspect overlapping consumer changes and report source-only versus runtime/browser evidence precisely.

## Next
Review the actual Clear eraser alongside Create Submit/X in a local browser at wide/narrow sizes and light/dark themes. Continue only on an explicit scoped dialog request; preserve concurrent workers' accepted behavior.
