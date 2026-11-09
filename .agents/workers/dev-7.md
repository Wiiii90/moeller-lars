# Dev Worker 7 — General Appearance preview corrections

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** Active; General Appearance source corrections committed; browser verification pending.

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

- Kept Title visibility and responsive-size Selects in their string option type after write (public model remains boolean). Live Preview is now keyed, with `wire:ignore.self` on the Alpine-sized device and page: Livewire updates no longer reset their measured inline dimensions to the 1px CSS defaults. Its iframe remains `wire:ignore`, while its displayed favicon can still be updated from server state.

- Site-icon picker now uses Filament 5's native `fi-fixed-positioning-context` strategy for the existing searchable dropdown, escaping the Appearance scroll owner's clipping while keeping Floating UI positioning. No alternate picker or additional listener introduced.
- The Full-width Menu divider had lost its sticky-owned gap in `cf867e4e28`, and Public Blade incorrectly marked that gapped mode as connected. Restored one header-owned gap with the viewport-wide stroke above it and limited connected-paper mode to genuinely joined `fine`/`menu`. Browser verification pending.
- Frame-style controls currently encode five composite variants in a single persisted field. A cleaner independent-contour/backing selection needs a deliberate unified domain/storage design, not appended parallel fields.
- Existing `tests/js/public-header.test.mjs` Beside assertions describe the old inside-sized rail and must be reconciled by Testing for the new exterior geometry. Dev does not modify tests.

## Next
- Add targeted regression tests (user explicitly authorized Dev test-source changes, execution forbidden), then browser-check Preview through consecutive autosaves and tab changes; verify site-icon dropdown escapes its scroll pane and remains keyboard/search accessible. Retain Frame Styles for separate later review. Legal author text requires verified operator facts and approval.
