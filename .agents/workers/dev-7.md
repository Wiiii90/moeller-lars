# Dev Worker 7 — General Appearance preview corrections

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** Active; expanded Appearance height corrected in source (browser review pending).

## Assigned scope
- Appearance / Global: review frame style combinations and their selection presentation; correct site icon Storage picker popover clipping.
- Appearance / Title: restore hide/show and responsive-size persistence/preview; reconcile beside/inside/above menu placement, alignment, and narrow-screen behavior against existing accepted contract.
- Appearance / Page: review and reasonably expand content width and padding bounds without breaking menu/title or viewport geometry.
- Appearance / Menu: correct full-width divider crossing the content region and unstable live preview.
- Appearance dialog: restore a stable height based on the tallest of Global, Title, Menu, Page tabs when expanded ("More space") and preserve Preview during tab/control changes.
- Regional Formats: consistent section spacing and discoverable date/number usage hints.
- Appearance / Legal: review field purpose and artist-facing content instructions; do not fabricate legal advice.

## Boundaries
- Work directly on `dev`, coordinate shared Admin/Public controls with active workers. Do not edit tests or general documentation.
- No unsolicited Continuous Integration, deployment, release or other branch writes; preserve artist data.
- Browser behavior unverified without actual review.

## Current progress
- Replaced active-tab-dependent expanded height with the tallest mounted Appearance panel as the intrinsic grid sizing owner; compact mode retains active-tab-only scroll. No duplicated measurement or remount.
- Regional Formats now reuse Contact/Legal field geometry, with public date/number purpose help and German translations. Content width bounds widened from 640–1440 to 560–1920 pixels, and padding bounds from 24–180 to 12–320 pixels using existing viewport-safe shell and effective minimum-artwork-width logic. Browser behavior remains unverified; title/menu, persistence, picker and Legal issues remain under investigation.

- Replaced Beside menu's shared Inside grid with a true external title rail on the Global background; stack decision now checks the real outside available width rather than Menu content width. This changes previously incorrect source-level outside-stack expectations; Testing must reconcile its tests. Browser acceptance pending.

- Refined all five existing Legal field help texts with field-specific editorial obligations and limitations; localized legal labels and guidance to German. No default legal copy or fabricated operator information was saved.

- Kept persisted Title visibility and responsive-size toggle Select states in their native string-option representation after successful writes; public model remains boolean. This is a source-level correction to a confirmed state-type mismatch, not proof of the reported browser failure's entire cause.

- Source inspection identifies the site-icon picker clipping boundary: searchable Filament dropdown inside the General pane's fixed overflow/scroll ancestors. Do not switch to a non-searchable native Select or disable the sole scroll owner as a workaround. The safe shared popup-layer solution still needs browser and framework-level verification; Dev Worker 3 also owns related Dashboard popover consistency.
- Full-width Menu divider draws through the sticky header's `::after` already, but the reported stacking/artwork overlap is not reproducible from source alone. Do not apply speculative z-index changes.
- Frame-style controls currently encode five composite variants in a single persisted field. A cleaner independent-contour/backing selection needs a deliberate unified domain/storage design, not appended parallel fields.
- Existing `tests/js/public-header.test.mjs` Beside assertions describe the old inside-sized rail and must be reconciled by Testing for the new exterior geometry. Dev does not modify tests.

## Next
- Browser-check title Hide/Show and responsive reduction selection persistence and Preview stability; the known Select string-option vs model-boolean state mismatch was corrected after autosave. Investigate any remaining Preview loss, full-width divider and picker clipping. Actual Legal text still requires verified operator/deployment facts and artist approval.
