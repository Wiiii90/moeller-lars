# Dev Worker 7 — General Appearance

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** Beside/Inside source rebuilt; final visual acceptance and test execution outstanding.

## Ownership and accepted decisions
- General → Appearance (Global, Title, Menu, Page and Legal), Regional Formats, Admin live Preview and their direct Public consumers.
- Frame Style variants and rendering are **deferred** until the upcoming Public Preview review; do not expand or redesign them in this assignment.
- User specifically authorized writing/updating the directly relevant regression tests on `dev`, **without executing any tests**. No CI, other branch changes or deployment.
- Do not alter existing saved artist data, add duplicate renderers or invent legal copy. Coordinate shared Admin popover changes with other Dev workers, especially Dashboard ownership.

## Implemented on dev
- More/Less Space expanded height uses the tallest mounted Appearance panel; compact mode retains one field scroller, no timers or duplicated measurement (132d16da).
- Regional Formats use consistent grouped section geometry and public Date/Number usage explanations with translations (8337112a).
- Page width 560–1920px and padding 12–320px retain viewport-safe shell geometry and existing effective artwork minimum (5787c583).
- **Replaced rejected Beside/Inside composition** (8c0aa659): original Beside title rail width was overridden by later shared `width: auto`, so Right was ineffective; the repaired variant owns an exterior full-width alignment rail fed by the same stable geometry as its stack decision, accounting for expanded Artwork paper. Inside alone reserves a defined column within the Menu, while its actual navigation owns the remainder. Hidden Title reserves neither column nor external row. The two modes use one heading and a shared narrow-screen stack. Old coupled selectors removed.
- Legal fields' explanatory text clarified, with no auto-filled artist legal material (50b4c9c3).
- Title visibility and responsive reduction native Select option state remains string-valued after a successful boolean persistence update (3350a477).
- Full-width Menu divider again uses its own sticky-header gap and viewport-wide stroke; only structurally connected modes join Menu/Page paper (e01a4acd).
- Site icon searchable Media Select now uses Filament's native fixed-positioning context in the Appearance pane so its dropdown escapes the clipped field scroller; no separate popup/JS positioning code (df6a7bb8).
- Live Preview has a stable Livewire key and preserves Alpine-measured device/page inline dimensions via `wire:ignore.self`; iframe remains `wire:ignore` and sibling favicon markup can still update (df6a7bb8).
- Regression tests updated/added at `tests/js/public-header.test.mjs`, `tests/Feature/Admin/General/GeneralSettingsPersistenceTest.php` and `tests/browser/e2e/admin/general-appearance.spec.mjs`; they cover exterior stack geometry, Title state, Preview identity through Title/Menu saves and local tab/Stage switching, and searchable picker behavior (1551c024).

## Current user rejection and follow-up
- User reports on the current Public Preview that both Beside/Inside and Left/Right are significantly misplaced. Old implementation is not accepted. This repair removes the causal width override and clarifies the two independent layout owners.
- Updated the existing Public header geometry tests and General browser regression tests to compare real Title/Menu/paper bounds for Beside/Inside Left/Right and narrow stacking (eceed7ce). Removed duplicate modeled Menu width in the header initializer: the exact rendered Menu and paper bounds now own offset and stack decisions, including Artwork where paper and Menu width differ. No tests run. The existing Dev skill requires checking the final CSS cascade and browser bounding boxes for visual alignment corrections.

## Verification and open gates
- Reviewed published GitHub source and commit file lists. **No test execution, no local/browser session, no CI and no deploy.** Correctness in a real browser remains unverified.
- In the next authorized browser pass, exercise Show/Hide, Responsive Size, Menu divider and tab switching while confirming that Preview remains visible and refreshed and the Site icon picker searches, scrolls, escapes clipping and closes correctly.
- Testing worker should review/execute the authored tests in an isolated environment when explicitly authorized. Frame Style visual behavior is deliberately left to later review.
- Final Legal text requires real operator, deployment and rights facts plus artist approval. 
