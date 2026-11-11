# Dev Worker 7 — General Appearance

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** General Stage stability and Public/embedded Preview parity corrections in progress; browser acceptance outstanding.

## Ownership and accepted decisions
- General → Appearance (Global, Title, Menu, Page and Legal), Regional Formats, Admin live Preview and their direct Public consumers.
- Frame Style variants and rendering are **deferred** until the upcoming Public Preview review; do not expand or redesign them in this assignment.
- User specifically authorized writing/updating the directly relevant regression tests on `dev`, **without executing any tests**. No CI, other branch changes or deployment.
- Do not alter existing saved artist data, add duplicate renderers or invent legal copy. Coordinate shared Admin popover changes with other Dev workers, especially Dashboard ownership.

## Implemented on dev
- More/Less Space expanded height uses the tallest mounted Appearance panel; compact mode retains one field scroller, no timers or duplicated measurement (132d16da).
- Regional Formats use consistent grouped section geometry and public Date/Number usage explanations with translations (8337112a).
- Page width 560–1920px and padding 12–320px retain viewport-safe shell geometry and existing effective artwork minimum (5787c583).
- Legal fields' explanatory text clarified, with no auto-filled artist legal material (50b4c9c3).
- Title visibility and responsive reduction native Select option state remains string-valued after a successful boolean persistence update (3350a477).
- Full-width Menu divider again uses its own sticky-header gap and viewport-wide stroke; only structurally connected modes join Menu/Page paper (e01a4acd).
- Site icon searchable Media Select now uses Filament's native fixed-positioning context in the Appearance pane so its dropdown escapes the clipped field scroller; no separate popup/JS positioning code (df6a7bb8).
- Live Preview has a stable Livewire key and preserves Alpine-measured device/page inline dimensions via `wire:ignore.self`; iframe remains `wire:ignore` and sibling favicon markup can still update (df6a7bb8).
- Regression tests updated/added at `tests/js/public-header.test.mjs`, `tests/Feature/Admin/General/GeneralSettingsPersistenceTest.php` and `tests/browser/e2e/admin/general-appearance.spec.mjs`; they cover Inside fit/position geometry, Title state, Preview identity through Title/Menu saves and tab/Stage switching, and searchable picker behavior (1551c024).

## Latest user decision: retire Beside; repair Inside
- Beside has been removed from admin options, Public CSS and JavaScript, and German locale. A one-way migration updates current Working and Committed settings. The single legacy value mapping in PublicAppearance is retained only to read immutable historical publication checkpoints.
- Inside Left places artist name left of navigation, Inside Right places artist name right of navigation, changing physical grid columns rather than text alignment within one left column. At 40rem and below the title always forms an Above Menu row, honoring Responsive alignment. Wider screens stack only on real content fit.
- Preserve Above and Below Menu behavior, the existing frame controls, site icon picker and Live Preview.
- Updated the existing JavaScript, Feature and browser regression tests for the remaining positions, physical Inside Left/Right ordering and responsive Above Menu fallback (f5e6d669). Removed obsolete Beside test cases. Reconciled the canonical Public site composition contract and Dev visual-alignment gate. Tests remain unexecuted.

## Current browser findings (2026-11-11)
- User reports More space Stage/Preview height jumping on Title Show/Hide. Cause in General CSS: expanded height was tied to live Filament conditional form geometry. Replaced max-content expanded sizing with a stable full-viewport-sized stage; retained the existing single scroll owner for forms that exceed it. Removed the matching obsolete responsive size overrides.
- Public Menu fix: shared --public-content-inset previously resolved 100% differently in grid-track vs padding contexts and used the viewport even when the authored shell was capped. It is now a single viewport/shell-derived length shared by Page and Menu; visible Inside title and navigation stay inside this reading inset, reserve cyclic arrows' 44px rails, and JS calculates stacking against the stable inner reading width. Hidden Title retains its established full-width navigation. The standalone /preview and embedded iframe use the same route but embedded Desktop historically uses a fixed 1280px viewport, hence responsive differences at unequal widths; continue parity investigation without inventing a second renderer.

## Verification and open gates
- Reviewed published GitHub source and commit file lists. **No test execution, no local/browser session, no CI and no deploy.** Correctness in a real browser remains unverified.
- In the next authorized browser pass, exercise Show/Hide, Responsive Size, Menu divider and tab switching while confirming that Preview remains visible and refreshed and the Site icon picker searches, scrolls, escapes clipping and closes correctly.
- Testing worker should review/execute the authored tests in an isolated environment when explicitly authorized. Frame Style visual behavior is deliberately left to later review.
- Final Legal text requires real operator, deployment and rights facts plus artist approval. 
